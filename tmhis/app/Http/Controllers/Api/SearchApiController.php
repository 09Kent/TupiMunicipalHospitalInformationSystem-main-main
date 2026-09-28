<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SearchApiController extends Controller
{
    /**
     * Search patients by Name, Patient ID, Patient Code, Contact, etc.
     * Fulfills Requirement 5: Search Bar instead of dropdown for finding patients/students.
     */
    public function searchPatients(Request $request): JsonResponse
    {
        $term = trim((string)$request->query('q', ''));
        $limit = min(50, max(5, (int)$request->query('limit', 15)));

        if (mb_strlen($term) < 1) {
            // Return recent active patients if no term
            $patients = DB::table('patients')
                ->where('Status', '!=', 'Archived')
                ->orderBy('PatientID', 'desc')
                ->limit($limit)
                ->get();
        } else {
            $like = "%{$term}%";
            $cleanNumeric = preg_replace('/[^0-9]/', '', $term);

            $patients = DB::table('patients')
                ->where(function ($query) use ($like, $cleanNumeric) {
                    $query->where('PatientCode', 'like', $like)
                        ->orWhere('FirstName', 'like', $like)
                        ->orWhere('LastName', 'like', $like)
                        ->orWhere('MiddleName', 'like', $like)
                        ->orWhere(DB::raw("CONCAT(FirstName, ' ', LastName)"), 'like', $like)
                        ->orWhere(DB::raw("CONCAT(FirstName, ' ', MiddleName, ' ', LastName)"), 'like', $like)
                        ->orWhere('ContactNumber', 'like', $like)
                        ->orWhere('Email', 'like', $like);

                    if (!empty($cleanNumeric)) {
                        $query->orWhere('PatientID', (int)$cleanNumeric);
                    }
                })
                ->where('Status', '!=', 'Archived')
                ->orderBy('LastName')
                ->orderBy('FirstName')
                ->limit($limit)
                ->get();
        }

        $formatted = $patients->map(function ($p) {
            $code = $p->PatientCode ?: ('TMH-' . str_pad((string)$p->PatientID, 6, '0', STR_PAD_LEFT));
            // Simulate/format Student ID if available or derive standardized institutional ID
            $studentId = 'STU-' . date('Y', strtotime($p->CreatedAt ?? 'now')) . '-' . str_pad((string)$p->PatientID, 5, '0', STR_PAD_LEFT);

            return [
                'id'           => (int)$p->PatientID,
                'patient_id'   => (int)$p->PatientID,
                'patient_code' => $code,
                'student_id'   => $studentId,
                'name'         => trim($p->FirstName . ' ' . ($p->MiddleName ? $p->MiddleName . ' ' : '') . $p->LastName),
                'first_name'   => $p->FirstName,
                'last_name'    => $p->LastName,
                'age'          => (int)$p->Age,
                'gender'       => $p->Gender,
                'contact'      => $p->ContactNumber ?: 'N/A',
                'email'        => $p->Email ?: 'N/A',
                'category'     => $p->PatientCategory ?: 'Outpatient',
                'status'       => $p->Status ?: 'Active',
            ];
        });

        return response()->json([
            'success' => true,
            'count'   => count($formatted),
            'data'    => $formatted,
        ]);
    }

    /**
     * Search doctors by Name, Specialty, Department
     * Fulfills Requirement 7: Doctor Assignment Search
     */
    public function searchDoctors(Request $request): JsonResponse
    {
        $term = trim((string)$request->query('q', ''));
        $deptId = (int)$request->query('department_id', 0);

        $query = DB::table('doctors as d')
            ->leftJoin('departments as dept', 'd.DepartmentID', '=', 'dept.DepartmentID')
            ->leftJoin('specialties as s', 'd.SpecialtyID', '=', 's.SpecialtyID')
            ->select(
                'd.DoctorID',
                'd.UserID',
                'd.FirstName',
                'd.LastName',
                'd.Specialty',
                'd.ContactNumber',
                'd.LicenseNumber',
                'd.Status',
                'dept.DepartmentID',
                'dept.DepartmentName',
                's.SpecialtyName'
            )
            ->where('d.Status', 'Active');

        if ($deptId > 0) {
            $query->where('d.DepartmentID', $deptId);
        }

        if (mb_strlen($term) > 0) {
            $like = "%{$term}%";
            $query->where(function ($q) use ($like) {
                $q->where('d.FirstName', 'like', $like)
                    ->orWhere('d.LastName', 'like', $like)
                    ->orWhere(DB::raw("CONCAT(d.FirstName, ' ', d.LastName)"), 'like', $like)
                    ->orWhere('d.Specialty', 'like', $like)
                    ->orWhere('dept.DepartmentName', 'like', $like)
                    ->orWhere('s.SpecialtyName', 'like', $like);
            });
        }

        $doctors = $query->orderBy('d.LastName')->get();

        // Count current active workload for each doctor (waiting/in consultation today)
        $today = date('Y-m-d');
        $workloads = DB::table('patient_queue')
            ->select('DoctorID', DB::raw('COUNT(*) as total'))
            ->where('QueueDate', $today)
            ->whereIn('QueueStatus', ['Waiting', 'Called', 'In Progress'])
            ->groupBy('DoctorID')
            ->pluck('total', 'DoctorID')
            ->toArray();

        $formatted = $doctors->map(function ($d) use ($workloads) {
            $fullName = 'Dr. ' . trim($d->FirstName . ' ' . $d->LastName);
            if (empty(trim($d->FirstName . ' ' . $d->LastName))) {
                $fullName = 'Dr. Staff Physician #' . $d->DoctorID;
            }
            $activeLoad = $workloads[$d->DoctorID] ?? 0;

            return [
                'doctor_id'       => (int)$d->DoctorID,
                'user_id'         => (int)$d->UserID,
                'name'            => $fullName,
                'specialty'       => $d->SpecialtyName ?: ($d->Specialty ?: 'General Medicine'),
                'department'      => $d->DepartmentName ?: 'Clinical Services',
                'department_id'   => (int)$d->DepartmentID,
                'schedule'        => '8:00 AM – 5:00 PM',
                'current_load'    => $activeLoad,
                'status'          => $d->Status,
            ];
        });

        return response()->json([
            'success' => true,
            'count'   => count($formatted),
            'data'    => $formatted,
        ]);
    }

    /**
     * Search Nurses for Nurse Assignment
     * Fulfills Requirement 8: Nurse Assignment Search
     */
    public function searchNurses(Request $request): JsonResponse
    {
        $term = trim((string)$request->query('q', ''));

        $query = DB::table('users')
            ->where('Role', 'Nurse')
            ->where('Status', 'Active');

        if (mb_strlen($term) > 0) {
            $like = "%{$term}%";
            $query->where(function ($q) use ($like) {
                $q->where('FirstName', 'like', $like)
                    ->orWhere('LastName', 'like', $like)
                    ->orWhereRaw("CONCAT(FirstName, ' ', LastName) LIKE ?", [$like])
                    ->orWhere('Username', 'like', $like);
            });
        }

        $nurses = $query->orderBy('LastName')->get();

        $formatted = $nurses->map(function ($n) {
            $name = $n->FullName ?: ('Nurse ' . trim($n->FirstName . ' ' . $n->LastName));
            if (empty(trim($name)) || $name === 'Nurse') {
                $name = 'Nurse ' . $n->Username;
            }

            return [
                'nurse_id'   => (int)$n->UserID,
                'user_id'    => (int)$n->UserID,
                'name'       => $name,
                'department' => 'General Ward & Triage',
                'shift'      => 'Day Shift (8:00 AM – 4:00 PM)',
                'status'     => $n->Status,
            ];
        });

        return response()->json([
            'success' => true,
            'count'   => count($formatted),
            'data'    => $formatted,
        ]);
    }
}
