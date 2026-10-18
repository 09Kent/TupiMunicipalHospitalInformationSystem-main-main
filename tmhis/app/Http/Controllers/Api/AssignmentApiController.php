<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\DoctorAssignment;
use App\Models\NurseAssignment;
use App\Models\NurseTask;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\User;
use App\Models\SystemAuditLog;

class AssignmentApiController extends Controller
{
    /**
     * Assign / Reassign Doctor to Patient
     * Fulfills Requirement 7: Doctor Assignment
     */
    public function assignDoctor(Request $request): JsonResponse
    {
        $patientId = (int)$request->input('patient_id');
        $doctorId = (int)$request->input('doctor_id');
        $departmentId = $request->input('department_id') ? (int)$request->input('department_id') : null;
        $notes = trim((string)$request->input('notes', ''));

        if ($patientId <= 0 || $doctorId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Valid Patient ID and Doctor ID are required for physician assignment.'
            ], 422);
        }

        $patient = Patient::find($patientId);
        if (!$patient) {
            return response()->json(['success' => false, 'message' => 'Patient not found.'], 404);
        }

        $doctor = Doctor::find($doctorId);
        if (!$doctor) {
            return response()->json(['success' => false, 'message' => 'Physician profile not found.'], 404);
        }

        $user = auth()->user();
        $assignedBy = $user ? $user->UserID : null;
        $assignedByName = $user ? ($user->FullName ?: $user->Username) : 'Staff Member';

        // Check if there is an existing doctor assigned today
        $currentApp = DB::table('appointments')
            ->where('PatientID', $patientId)
            ->whereIn('Status', ['Scheduled', 'Confirmed', 'Pending', 'In Progress'])
            ->orderBy('AppointmentID', 'desc')
            ->first();

        $previousDoctorId = $currentApp ? $currentApp->DoctorID : null;
        if ($previousDoctorId === $doctorId) {
            // Check if already assigned
            $existingAssign = DoctorAssignment::where('PatientID', $patientId)
                ->where('DoctorID', $doctorId)
                ->whereIn('Status', ['Assigned', 'In Consultation'])
                ->first();
            if ($existingAssign) {
                return response()->json([
                    'success' => true,
                    'message' => 'Doctor is already assigned to this patient.',
                    'assignment' => $existingAssign
                ]);
            }
        }

        DB::beginTransaction();
        try {
            // 1. Create Doctor Assignment Record
            $assignment = DoctorAssignment::create([
                'PatientID'        => $patientId,
                'DoctorID'         => $doctorId,
                'PreviousDoctorID' => ($previousDoctorId && $previousDoctorId !== $doctorId) ? $previousDoctorId : null,
                'DepartmentID'     => $departmentId ?: $doctor->DepartmentID,
                'Status'           => 'Assigned',
                'AssignedBy'       => $assignedBy,
                'AssignedByName'   => $assignedByName,
                'AssignedAt'       => now(),
                'Notes'            => $notes ?: 'Clinical consultation assignment'
            ]);

            // 2. Update or Create Appointment
            if ($currentApp) {
                DB::table('appointments')->where('AppointmentID', $currentApp->AppointmentID)->update([
                    'DoctorID'   => $doctorId,
                    'Status'     => 'Scheduled',
                    'UpdatedAt'  => now()
                ]);
                $appId = $currentApp->AppointmentID;
            } else {
                $appId = DB::table('appointments')->insertGetId([
                    'PatientID'       => $patientId,
                    'DoctorID'        => $doctorId,
                    'AppointmentDate' => date('Y-m-d'),
                    'AppointmentTime' => date('H:i:s'),
                    'Reason'          => $notes ?: 'Physician assignment consultation',
                    'Status'          => 'Scheduled',
                    'Priority'        => 'Normal',
                    'CreatedBy'       => $assignedBy ?: 1,
                    'CreatedAt'       => now(),
                    'UpdatedAt'       => now(),
                ]);
            }

            // 3. Update or Create Queue Ticket
            $queueRecord = DB::table('patient_queue')
                ->where('PatientID', $patientId)
                ->where('QueueDate', date('Y-m-d'))
                ->first();

            if ($queueRecord) {
                DB::table('patient_queue')->where('QueueID', $queueRecord->QueueID)->update([
                    'DoctorID'    => $doctorId,
                    'QueueStatus' => 'Waiting',
                ]);
            } else {
                $nextNum = ((int)DB::table('patient_queue')->where('QueueDate', date('Y-m-d'))->max('QueueNumber')) + 1;
                DB::table('patient_queue')->insert([
                    'AppointmentID' => $appId,
                    'PatientID'     => $patientId,
                    'DoctorID'      => $doctorId,
                    'QueueNumber'   => $nextNum,
                    'QueueDate'     => date('Y-m-d'),
                    'QueueStatus'   => 'Waiting',
                    'Priority'      => 'Normal',
                    'CreatedAt'     => now(),
                ]);
            }

            // 4. Update Patient Timestamp
            DB::table('patients')->where('PatientID', $patientId)->update([
                'UpdatedAt' => now()
            ]);

            // 5. System Audit Trail
            $docName = 'Dr. ' . trim($doctor->FirstName . ' ' . $doctor->LastName);
            $patName = trim($patient->FirstName . ' ' . $patient->LastName);
            SystemAuditLog::create([
                'UserID'    => $assignedBy ?? 1,
                'UserName'  => $assignedByName,
                'UserRole'  => $user ? $user->Role : 'Staff',
                'Action'    => 'Doctor Assigned',
                'Module'    => 'Clinical Care',
                'RecordID'  => (string)$assignment->AssignmentID,
                'Details'   => "{$assignedByName} assigned {$docName} to Patient {$patName} ({$patient->PatientCode}).",
                'IPAddress' => $request->ip(),
                'UserAgent' => substr((string)$request->userAgent(), 0, 255),
                'CreatedAt' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success'    => true,
                'message'    => "Doctor {$docName} successfully assigned to {$patName}.",
                'assignment' => $assignment
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => safe_error_message($e, 'Failed to assign doctor to patient.')
            ], 500);
        }
    }

    /**
     * Assign Nurse to Patient Care Task
     * Fulfills Requirement 8: Nurse Assignment
     */
    public function assignNurse(Request $request): JsonResponse
    {
        $patientId = (int)$request->input('patient_id');
        $nurseId = (int)$request->input('nurse_id');
        $doctorId = $request->input('doctor_id') ? (int)$request->input('doctor_id') : null;
        $task = trim((string)$request->input('task', 'Vital Signs & Patient Monitoring'));
        $schedule = $request->input('schedule') ?: date('Y-m-d H:i:s', strtotime('+1 hour'));
        $priority = $request->input('priority') ?: 'Normal';
        $notes = trim((string)$request->input('notes', ''));

        if ($patientId <= 0 || $nurseId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Valid Patient ID and Nurse ID are required.'
            ], 422);
        }

        $patient = Patient::find($patientId);
        if (!$patient) {
            return response()->json(['success' => false, 'message' => 'Patient not found.'], 404);
        }

        $nurse = User::find($nurseId);
        if (!$nurse) {
            return response()->json(['success' => false, 'message' => 'Nurse profile not found.'], 404);
        }

        $user = auth()->user();
        $assignedBy = $user ? $user->UserID : null;
        $assignedByName = $user ? ($user->FullName ?: $user->Username) : 'Attending Physician';

        DB::beginTransaction();
        try {
            // 1. Create Nurse Task
            $dueTimeFormatted = date('h:i A', strtotime($schedule));
            $nurseTask = NurseTask::create([
                'PatientID'      => $patientId,
                'NurseID'        => $nurseId,
                'AssignedBy'     => $assignedBy,
                'AssignedByName' => $assignedByName,
                'DoctorID'       => $doctorId,
                'TaskTitle'      => $task,
                'Category'       => 'Nursing Care',
                'DueTime'        => $dueTimeFormatted,
                'ScheduledTime'  => date('Y-m-d H:i:s', strtotime($schedule)),
                'Priority'       => $priority,
                'Status'         => 'Assigned',
                'Remarks'        => $notes ?: 'Care task assigned via TMHIMS Nurse Coordination',
                'CreatedAt'      => now(),
            ]);

            // 2. Create Nurse Assignment Record
            $assignment = NurseAssignment::create([
                'PatientID'       => $patientId,
                'DoctorID'        => $doctorId,
                'NurseID'         => $nurseId,
                'TaskID'          => $nurseTask->TaskID,
                'TaskDescription' => $task,
                'ScheduledTime'   => date('Y-m-d H:i:s', strtotime($schedule)),
                'Priority'        => $priority,
                'Status'          => 'Assigned',
                'AssignedBy'      => $assignedBy,
                'AssignedByName'  => $assignedByName,
                'AssignedAt'      => now(),
                'Notes'           => $notes
            ]);

            // 3. System Audit Log
            $nurseName = $nurse->FullName ?: ('Nurse ' . $nurse->Username);
            $patName = trim($patient->FirstName . ' ' . $patient->LastName);
            SystemAuditLog::create([
                'UserID'    => $assignedBy ?? 1,
                'UserName'  => $assignedByName,
                'UserRole'  => $user ? $user->Role : 'Staff',
                'Action'    => 'Nurse Assigned',
                'Module'    => 'Nursing',
                'RecordID'  => (string)$assignment->AssignmentID,
                'Details'   => "{$assignedByName} assigned {$nurseName} to Patient {$patName} for {$task}.",
                'IPAddress' => $request->ip(),
                'UserAgent' => substr((string)$request->userAgent(), 0, 255),
                'CreatedAt' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success'    => true,
                'message'    => "Patient {$patName} successfully assigned to {$nurseName} for {$task}.",
                'assignment' => $assignment,
                'task'       => $nurseTask
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => safe_error_message($e, 'Failed to assign nurse to care task.')
            ], 500);
        }
    }
}
