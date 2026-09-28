<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\NurseTask;
use App\Models\Prescription;
use App\Models\Patient;
use App\Models\SystemAuditLog;

class MedicationAdministrationApiController extends Controller
{
    /**
     * Record Medication Administration by Nurse on Duty
     * Fulfills Requirement 9: Medication Administration Workflow
     */
    public function recordAdministration(Request $request): JsonResponse
    {
        $taskId = (int)$request->input('task_id');
        $patientId = (int)$request->input('patient_id');
        $status = $request->input('status', 'Administered'); // Administered, Missed, Held, Cancelled
        $notes = trim((string)$request->input('notes', ''));

        $allowedStatuses = ['Administered', 'Missed', 'Held', 'Cancelled'];
        if (!in_array($status, $allowedStatuses)) {
            return response()->json([
                'success' => false,
                'message' => "Invalid administration status '$status'. Allowed: " . implode(', ', $allowedStatuses)
            ], 422);
        }

        $task = NurseTask::find($taskId);
        if (!$task) {
            return response()->json(['success' => false, 'message' => 'Medication administration task not found.'], 404);
        }

        if ($patientId > 0 && (int)$task->PatientID !== $patientId) {
            return response()->json([
                'success' => false,
                'message' => 'Security Error: Task does not match specified patient record.'
            ], 403);
        }

        $user = auth()->user();
        $nurseId = $user ? $user->UserID : ($task->NurseID ?: 1);
        $nurseName = $user ? ($user->FullName ?: $user->Username) : 'Nurse On Duty';

        DB::beginTransaction();
        try {
            $task->Status = $status;
            $task->AdministeredAt = ($status === 'Administered') ? now() : null;
            $task->CompletedAt = now();
            $task->AdministeredBy = $nurseId;
            $task->AdministeredByName = $nurseName;
            if ($notes) {
                $task->Remarks = trim(($task->Remarks ? $task->Remarks . ' | ' : '') . "Nurse Note: " . $notes);
            }
            $task->save();

            // Update patient object
            $patient = Patient::find($task->PatientID);
            $patName = $patient ? trim($patient->FirstName . ' ' . $patient->LastName) : 'Patient #' . $task->PatientID;
            $patCode = $patient ? $patient->PatientCode : 'TMH-' . $task->PatientID;
            $medName = $task->MedicationName ?: ($task->TaskTitle ?: 'Prescribed Medication');

            // Audit Trail
            SystemAuditLog::create([
                'UserID'    => $nurseId,
                'UserName'  => $nurseName,
                'UserRole'  => 'Nurse',
                'Action'    => 'Medication Administration',
                'Module'    => 'Nursing',
                'RecordID'  => (string)$task->TaskID,
                'Details'   => "Nurse {$nurseName} marked {$medName} as '{$status}' for Patient {$patName} ({$patCode}). Notes: {$notes}",
                'IPAddress' => $request->ip(),
                'UserAgent' => substr((string)$request->userAgent(), 0, 255),
                'CreatedAt' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Medication {$medName} successfully recorded as {$status}.",
                'task'    => $task
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to record administration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Schedule-Based Nursing Tasks categorized by Upcoming, Due, Completed, Missed
     * Fulfills Requirement 10 & 11
     */
    public function getSchedule(Request $request): JsonResponse
    {
        $nurseId = (int)$request->query('nurse_id', auth()->id() ?? 0);

        $query = DB::table('nurse_tasks as nt')
            ->join('patients as p', 'nt.PatientID', '=', 'p.PatientID')
            ->leftJoin('doctors as d', 'nt.DoctorID', '=', 'd.DoctorID')
            ->select(
                'nt.*',
                'p.PatientCode',
                'p.FirstName as PatientFirstName',
                'p.LastName as PatientLastName',
                'p.Age',
                'p.Gender',
                'p.PatientCategory',
                'd.FirstName as DocFirstName',
                'd.LastName as DocLastName',
                'd.Specialty as DocSpecialty'
            );

        if ($nurseId > 0) {
            $query->where(function ($q) use ($nurseId) {
                $q->where('nt.NurseID', $nurseId)
                  ->orWhereNull('nt.NurseID');
            });
        }

        $all = $query->orderBy('nt.TaskID', 'desc')->limit(100)->get();

        $now = time();
        $upcoming = [];
        $due = [];
        $completed = [];
        $missed = [];

        foreach ($all as $item) {
            $scheduledTs = !empty($item->ScheduledTime) ? strtotime($item->ScheduledTime) : strtotime($item->CreatedAt);
            $status = $item->Status;

            $docFull = !empty($item->DocLastName) ? "Dr. {$item->DocFirstName} {$item->DocLastName}" : "Dr. Daniel Lewis, MD";
            $room = ($item->PatientCategory === 'Inpatient') ? ('Room ' . (200 + ($item->PatientID % 20))) : 'OPD Triage';

            $formattedItem = [
                'task_id'         => (int)$item->TaskID,
                'patient_id'      => (int)$item->PatientID,
                'patient_name'    => trim($item->PatientFirstName . ' ' . $item->PatientLastName),
                'patient_code'    => $item->PatientCode ?: ('TMH-' . $item->PatientID),
                'doctor_name'     => $docFull,
                'room'            => $room,
                'task_title'      => $item->TaskTitle,
                'category'        => $item->Category ?: 'Nursing Care',
                'medication'      => $item->MedicationName ?: $item->TaskTitle,
                'dosage'          => $item->Dosage ?: 'Standard',
                'route'           => $item->Route ?: 'Oral',
                'frequency'       => $item->Frequency ?: 'Routine',
                'schedule_time'   => $item->ScheduledTime ? date('g:i A', strtotime($item->ScheduledTime)) : $item->DueTime,
                'schedule_date'   => $item->ScheduledTime ? date('M d, Y', strtotime($item->ScheduledTime)) : date('M d, Y'),
                'priority'        => $item->Priority ?: 'Normal',
                'status'          => $status,
                'administered_at' => $item->AdministeredAt ? date('g:i A', strtotime($item->AdministeredAt)) : null,
                'administered_by' => $item->AdministeredByName,
                'remarks'         => $item->Remarks,
            ];

            if (in_array($status, ['Administered', 'Completed'])) {
                $completed[] = $formattedItem;
            } elseif (in_array($status, ['Missed', 'Held', 'Cancelled'])) {
                $missed[] = $formattedItem;
            } else {
                // If scheduled time has passed by more than 30 mins and not done, it is Missed/Due
                if ($scheduledTs < ($now - 1800)) {
                    $formattedItem['status'] = 'Due';
                    $due[] = $formattedItem;
                } elseif ($scheduledTs <= ($now + 1800)) {
                    $formattedItem['status'] = 'Due';
                    $due[] = $formattedItem;
                } else {
                    $upcoming[] = $formattedItem;
                }
            }
        }

        return response()->json([
            'success'   => true,
            'counts'    => [
                'upcoming'  => count($upcoming),
                'due'       => count($due),
                'completed' => count($completed),
                'missed'    => count($missed),
            ],
            'upcoming'  => $upcoming,
            'due'       => $due,
            'completed' => $completed,
            'missed'    => $missed,
        ]);
    }

    /**
     * Route alias for recordAdministration
     */
    public function administer(Request $request): JsonResponse
    {
        return $this->recordAdministration($request);
    }

    /**
     * Create Nurse Administration Task directly from a Doctor Prescription
     * Fulfills Requirement 9
     */
    public function createTaskFromPrescription(Request $request): JsonResponse
    {
        $prescriptionId = (int)$request->input('prescription_id');
        $patientId = (int)$request->input('patient_id');
        $scheduledTime = $request->input('scheduled_time', now()->addHours(1)->format('Y-m-d H:i:s'));
        $nurseId = (int)$request->input('nurse_id', auth()->id() ?? 1);
        $notes = trim((string)$request->input('notes', ''));

        $rx = Prescription::find($prescriptionId);
        if (!$rx) {
            return response()->json(['success' => false, 'message' => 'Prescription not found.'], 404);
        }

        $task = NurseTask::create([
            'PatientID'      => $patientId ?: $rx->PatientID,
            'DoctorID'       => $rx->DoctorID,
            'NurseID'        => $nurseId,
            'TaskTitle'      => "Administer " . ($rx->MedicationName ?: 'Prescribed Medication'),
            'Category'       => 'Medication Administration',
            'Priority'       => 'High',
            'Status'         => 'Scheduled',
            'MedicationName' => $rx->MedicationName,
            'Dosage'         => $rx->Dosage,
            'Route'          => $rx->Route ?: 'Oral',
            'Frequency'      => $rx->Frequency ?: 'Daily',
            'ScheduledTime'  => $scheduledTime,
            'DueTime'        => date('g:i A', strtotime($scheduledTime)),
            'PrescriptionID' => $rx->PrescriptionID,
            'Remarks'        => $notes ?: "Medication administration order for {$rx->MedicationName}",
            'CreatedAt'      => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Medication administration task successfully created for nurse schedule.',
            'task'    => $task
        ]);
    }
}

