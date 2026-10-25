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
            ->where('d.Status', '!=', 'Inactive');

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
            $fullName = property_exists($n, 'FullName') ? $n->FullName : trim(($n->FirstName ?? '') . ' ' . ($n->LastName ?? ''));
            $name = !empty($fullName) ? ('Nurse ' . $fullName) : ('Nurse ' . ($n->Username ?? 'Staff'));

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

    /**
     * Search Diseases & ICD-10 Coding Catalog
     * Allows AJAX lookup by Disease Name, ICD-10 Code, Category, or Clinical Description
     */
    public function searchDiseases(Request $request): JsonResponse
    {
        $term = trim((string)$request->query('q', ''));
        $limit = min(50, max(5, (int)$request->query('limit', 20)));

        $query = DB::table('icd10_catalog')
            ->where('Status', 'Active');

        if (mb_strlen($term) > 0) {
            $like = "%{$term}%";
            $prefix = "{$term}%";

            $query->where(function ($q) use ($like) {
                $q->where('DiseaseName', 'like', $like)
                    ->orWhere('ICD10Code', 'like', $like)
                    ->orWhere('Category', 'like', $like)
                    ->orWhere('Description', 'like', $like);
            })
            ->orderByRaw("
                CASE 
                    WHEN ICD10Code LIKE ? THEN 0
                    WHEN DiseaseName LIKE ? THEN 1
                    WHEN DiseaseName LIKE ? THEN 2
                    ELSE 3 
                END
            ", [$prefix, $prefix, $like])
            ->orderBy('DiseaseName');
        } else {
            // Default top clinical diagnoses
            $query->orderBy('CatalogID', 'asc');
        }

        $results = $query->limit($limit)->get();

        $formatted = $results->map(function ($d) {
            $days = (int)($d->DefaultFollowUpDays ?: 14);
            $targetDate = date('Y-m-d', strtotime("+{$days} days"));

            return [
                'id'               => (int)$d->CatalogID,
                'name'             => $d->DiseaseName,
                'disease_name'     => $d->DiseaseName,
                'icd_code'         => $d->ICD10Code,
                'icd10_code'       => $d->ICD10Code,
                'category'         => $d->Category ?: 'General Clinical',
                'description'      => $d->Description ?: '',
                'default_severity' => $d->DefaultSeverity ?: 'Moderate',
                'display_label'    => "{$d->DiseaseName} (ICD-10: {$d->ICD10Code})",
                'treatment_plan'   => [
                    'goal'               => $d->DefaultGoal ?: "Symptom stabilization, control of {$d->DiseaseName}, and clinical recovery.",
                    'lifestyle'          => $d->DefaultLifestyle ?: "Adequate rest, balanced nutrition, stay hydrated, avoid smoking and alcohol.",
                    'medication_plan'    => $d->DefaultMedication ?: "Prescribed pharmacological therapy and supportive clinical care as indicated.",
                    'follow_up_schedule' => $d->DefaultFollowUp ?: "2 Weeks",
                    'follow_up_days'     => $days,
                    'follow_up_date'     => $targetDate,
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'count'   => count($formatted),
            'data'    => $formatted,
        ]);
    }

    /**
     * Search Medications in Pharmacy Inventory
     */
    public function searchMedications(Request $request): JsonResponse
    {
        $term = trim((string)$request->query('q', ''));
        $limit = min(50, max(5, (int)$request->query('limit', 20)));

        $query = DB::table('pharmacy_inventory')
            ->whereIn('Status', ['In Stock', 'Low Stock', 'Active']);

        if (mb_strlen($term) > 0) {
            $like = "%{$term}%";
            $query->where(function ($q) use ($like) {
                $q->where('GenericName', 'like', $like)
                    ->orWhere('BrandName', 'like', $like)
                    ->orWhere('ItemCode', 'like', $like)
                    ->orWhere('Category', 'like', $like);
            });
        }

        $items = $query->orderBy('GenericName')->limit($limit)->get();

        $formatted = $items->map(function ($m) {
            return [
                'id'            => (int)$m->InventoryID,
                'item_code'     => $m->ItemCode,
                'generic_name'  => $m->GenericName,
                'brand_name'    => $m->BrandName ?: '',
                'full_name'     => trim($m->GenericName . ($m->BrandName ? " ({$m->BrandName})" : '') . " {$m->Strength}"),
                'dosage_form'   => $m->DosageForm,
                'strength'      => $m->Strength,
                'category'      => $m->Category,
                'selling_price' => (float)$m->SellingPrice,
                'current_stock' => (int)$m->CurrentStock,
                'status'        => $m->Status,
            ];
        });

        return response()->json([
            'success' => true,
            'count'   => count($formatted),
            'data'    => $formatted,
        ]);
    }

    /**
     * Search Laboratory Tests Catalog
     */
    public function searchLaboratoryTests(Request $request): JsonResponse
    {
        $term = trim((string)$request->query('q', ''));
        $limit = min(50, max(5, (int)$request->query('limit', 20)));

        $query = DB::table('test_catalog')->where('Status', 'Active');

        if (mb_strlen($term) > 0) {
            $like = "%{$term}%";
            $query->where(function ($q) use ($like) {
                $q->where('TestName', 'like', $like)
                    ->orWhere('TestCode', 'like', $like)
                    ->orWhere('Category', 'like', $like)
                    ->orWhere('SpecimenType', 'like', $like);
            });
        }

        $items = $query->orderBy('TestName')->limit($limit)->get();

        $formatted = $items->map(function ($t) {
            return [
                'id'              => (int)$t->CatalogID,
                'test_code'       => $t->TestCode,
                'test_name'       => $t->TestName,
                'category'        => $t->Category,
                'specimen_type'   => $t->SpecimenType,
                'turnaround_time' => $t->TurnaroundTime,
                'standard_price'  => (float)$t->StandardPrice,
                'status'          => $t->Status,
            ];
        });

        return response()->json([
            'success' => true,
            'count'   => count($formatted),
            'data'    => $formatted,
        ]);
    }

    /**
     * Search Admissions (Inpatient Roster)
     */
    public function searchAdmissions(Request $request): JsonResponse
    {
        $term = trim((string)$request->query('q', ''));
        $status = trim((string)$request->query('status', 'Admitted'));
        $limit = min(50, max(5, (int)$request->query('limit', 20)));

        $query = DB::table('admissions as a')
            ->join('patients as p', 'a.PatientID', '=', 'p.PatientID')
            ->leftJoin('doctors as d', 'a.DoctorID', '=', 'd.DoctorID')
            ->select(
                'a.*',
                'p.FirstName as PatientFirst',
                'p.LastName as PatientLast',
                'p.PatientCode',
                'p.Age',
                'p.Gender',
                'd.FirstName as DoctorFirst',
                'd.LastName as DoctorLast'
            );

        if (!empty($status) && $status !== 'All') {
            $query->where('a.Status', $status);
        }

        if (mb_strlen($term) > 0) {
            $like = "%{$term}%";
            $query->where(function ($q) use ($like) {
                $q->where('a.AdmissionNumber', 'like', $like)
                    ->orWhere('a.Ward', 'like', $like)
                    ->orWhere('a.RoomNumber', 'like', $like)
                    ->orWhere('a.BedNumber', 'like', $like)
                    ->orWhere('p.FirstName', 'like', $like)
                    ->orWhere('p.LastName', 'like', $like)
                    ->orWhere('p.PatientCode', 'like', $like);
            });
        }

        $items = $query->orderBy('a.AdmissionDate', 'desc')->limit($limit)->get();

        $formatted = $items->map(function ($adm) {
            return [
                'admission_id'     => (int)$adm->AdmissionID,
                'admission_number' => $adm->AdmissionNumber,
                'patient_id'       => (int)$adm->PatientID,
                'patient_name'     => trim("{$adm->PatientFirst} {$adm->PatientLast}"),
                'patient_code'     => $adm->PatientCode,
                'age'              => (int)$adm->Age,
                'gender'           => $adm->Gender,
                'doctor_name'      => 'Dr. ' . trim("{$adm->DoctorFirst} {$adm->DoctorLast}"),
                'ward'             => $adm->Ward,
                'room_number'      => $adm->RoomNumber,
                'bed_number'       => $adm->BedNumber,
                'admission_reason' => $adm->AdmissionReason,
                'admission_date'   => $adm->AdmissionDate,
                'status'           => $adm->Status,
            ];
        });

        return response()->json([
            'success' => true,
            'count'   => count($formatted),
            'data'    => $formatted,
        ]);
    }

    /**
     * Search Billing Invoices
     */
    public function searchBillingInvoices(Request $request): JsonResponse
    {
        $term = trim((string)$request->query('q', ''));
        $status = trim((string)$request->query('status', ''));
        $limit = min(50, max(5, (int)$request->query('limit', 20)));

        $query = DB::table('billing_invoices as bi')
            ->join('patients as p', 'bi.PatientID', '=', 'p.PatientID')
            ->select('bi.*', 'p.FirstName', 'p.LastName', 'p.PatientCode', 'p.ContactNumber');

        if (!empty($status) && $status !== 'All') {
            $query->where('bi.PaymentStatus', $status);
        }

        if (mb_strlen($term) > 0) {
            $like = "%{$term}%";
            $query->where(function ($q) use ($like) {
                $q->where('bi.InvoiceNumber', 'like', $like)
                    ->orWhere('p.FirstName', 'like', $like)
                    ->orWhere('p.LastName', 'like', $like)
                    ->orWhere('p.PatientCode', 'like', $like);
            });
        }

        $items = $query->orderBy('bi.CreatedAt', 'desc')->limit($limit)->get();

        $formatted = $items->map(function ($inv) {
            return [
                'invoice_id'     => (int)$inv->InvoiceID,
                'invoice_number' => $inv->InvoiceNumber,
                'patient_id'     => (int)$inv->PatientID,
                'patient_name'   => trim("{$inv->FirstName} {$inv->LastName}"),
                'patient_code'   => $inv->PatientCode,
                'gross_amount'   => (float)$inv->GrossAmount,
                'total_payable'  => (float)$inv->TotalPayable,
                'amount_paid'    => (float)$inv->AmountPaid,
                'balance_due'    => (float)$inv->BalanceDue,
                'payment_status' => $inv->PaymentStatus,
                'created_at'     => $inv->CreatedAt,
            ];
        });

        return response()->json([
            'success' => true,
            'count'   => count($formatted),
            'data'    => $formatted,
        ]);
    }

    /**
     * Search Appointments
     */
    public function searchAppointments(Request $request): JsonResponse
    {
        $term = trim((string)$request->query('q', ''));
        $date = trim((string)$request->query('date', ''));
        $limit = min(50, max(5, (int)$request->query('limit', 20)));

        $query = DB::table('appointments as app')
            ->join('patients as p', 'app.PatientID', '=', 'p.PatientID')
            ->leftJoin('doctors as d', 'app.DoctorID', '=', 'd.DoctorID')
            ->select('app.*', 'p.FirstName as PatientFirst', 'p.LastName as PatientLast', 'p.PatientCode', 'd.FirstName as DoctorFirst', 'd.LastName as DoctorLast');

        if (!empty($date)) {
            $query->where('app.AppointmentDate', $date);
        }

        if (mb_strlen($term) > 0) {
            $like = "%{$term}%";
            $query->where(function ($q) use ($like) {
                $q->where('p.FirstName', 'like', $like)
                    ->orWhere('p.LastName', 'like', $like)
                    ->orWhere('p.PatientCode', 'like', $like)
                    ->orWhere('app.Reason', 'like', $like)
                    ->orWhere('app.ConsultationType', 'like', $like);
            });
        }

        $items = $query->orderBy('app.AppointmentDate', 'desc')->orderBy('app.AppointmentTime', 'asc')->limit($limit)->get();

        $formatted = $items->map(function ($a) {
            return [
                'appointment_id'    => (int)$a->AppointmentID,
                'patient_id'        => (int)$a->PatientID,
                'patient_name'      => trim("{$a->PatientFirst} {$a->PatientLast}"),
                'patient_code'      => $a->PatientCode,
                'doctor_name'       => 'Dr. ' . trim("{$a->DoctorFirst} {$a->DoctorLast}"),
                'appointment_date'  => $a->AppointmentDate,
                'appointment_time'  => $a->AppointmentTime,
                'consultation_type' => $a->ConsultationType,
                'reason'            => $a->Reason,
                'priority'          => $a->Priority,
                'status'            => $a->Status,
            ];
        });

        return response()->json([
            'success' => true,
            'count'   => count($formatted),
            'data'    => $formatted,
        ]);
    }

    /**
     * Search Live Queue
     */
    public function searchQueue(Request $request): JsonResponse
    {
        $term = trim((string)$request->query('q', ''));
        $date = trim((string)$request->query('date', date('Y-m-d')));
        $limit = min(50, max(5, (int)$request->query('limit', 20)));

        $query = DB::table('patient_queue as q')
            ->join('patients as p', 'q.PatientID', '=', 'p.PatientID')
            ->leftJoin('doctors as d', 'q.DoctorID', '=', 'd.DoctorID')
            ->select('q.*', 'p.FirstName as PatientFirst', 'p.LastName as PatientLast', 'p.PatientCode', 'd.FirstName as DoctorFirst', 'd.LastName as DoctorLast')
            ->where('q.QueueDate', $date);

        if (mb_strlen($term) > 0) {
            $like = "%{$term}%";
            $query->where(function ($sq) use ($like) {
                $sq->where('q.QueueNumber', 'like', $like)
                    ->orWhere('p.FirstName', 'like', $like)
                    ->orWhere('p.LastName', 'like', $like)
                    ->orWhere('p.PatientCode', 'like', $like)
                    ->orWhere('q.QueueStatus', 'like', $like);
            });
        }

        $items = $query->orderBy('q.QueueID', 'asc')->limit($limit)->get();

        $formatted = $items->map(function ($item) {
            return [
                'queue_id'      => (int)$item->QueueID,
                'queue_number'  => $item->QueueNumber,
                'patient_id'    => (int)$item->PatientID,
                'patient_name'  => trim("{$item->PatientFirst} {$item->PatientLast}"),
                'patient_code'  => $item->PatientCode,
                'doctor_name'   => 'Dr. ' . trim("{$item->DoctorFirst} {$item->DoctorLast}"),
                'queue_status'  => $item->QueueStatus,
                'priority'      => $item->Priority,
                'called_at'     => $item->CalledAt,
                'queue_date'    => $item->QueueDate,
            ];
        });

        return response()->json([
            'success' => true,
            'count'   => count($formatted),
            'data'    => $formatted,
        ]);
    }
}

