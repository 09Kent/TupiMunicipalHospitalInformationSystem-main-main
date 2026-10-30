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
            // Standardized institutional / student ID
            $studentId = 'STU-' . date('Y', strtotime($p->CreatedAt ?? 'now')) . '-' . str_pad((string)$p->PatientID, 5, '0', STR_PAD_LEFT);
            $fullName = trim($p->FirstName . ' ' . ($p->MiddleName ? $p->MiddleName . ' ' : '') . $p->LastName);

            return [
                'id'                => (int)$p->PatientID,
                'patient_id'        => (int)$p->PatientID,
                'PatientID'         => (int)$p->PatientID,
                'patient_code'      => $code,
                'PatientCode'       => $code,
                'code'              => $code,
                'student_id'        => $studentId,
                'name'              => $fullName,
                'first_name'        => $p->FirstName,
                'FirstName'         => $p->FirstName,
                'last_name'         => $p->LastName,
                'LastName'          => $p->LastName,
                'middle_name'       => $p->MiddleName ?? '',
                'MiddleName'        => $p->MiddleName ?? '',
                'date_of_birth'     => $p->DateOfBirth ?? '',
                'DateOfBirth'       => $p->DateOfBirth ?? '',
                'age'               => (int)$p->Age,
                'Age'               => (int)$p->Age,
                'gender'            => $p->Gender,
                'Gender'            => $p->Gender,
                'contact'           => $p->ContactNumber ?: 'N/A',
                'ContactNumber'     => $p->ContactNumber ?: '',
                'email'             => $p->Email ?: 'N/A',
                'Email'             => $p->Email ?: '',
                'address'           => $p->Address ?? '',
                'Address'           => $p->Address ?? '',
                'philhealth_number' => $p->PhilHealthNumber ?? '',
                'PhilHealthNumber'  => $p->PhilHealthNumber ?? '',
                'category'          => $p->PatientCategory ?: 'Outpatient',
                'PatientCategory'   => $p->PatientCategory ?: 'Outpatient',
                'status'            => $p->Status ?: 'Active',
                'Status'            => $p->Status ?: 'Active',
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
     * Allows AJAX lookup by Disease Name, ICD-10 Code, Category, or Clinical Description.
     * Compatible with PostgreSQL & MySQL (case-insensitive) with comprehensive fallback.
     */
    public function searchDiseases(Request $request): JsonResponse
    {
        $term = trim((string)$request->query('q', ''));
        $limit = min(50, max(5, (int)$request->query('limit', 20)));

        $formatted = [];
        $existingCodes = [];

        try {
            if (Schema::hasTable('icd10_catalog')) {
                $query = DB::table('icd10_catalog')
                    ->where(function ($w) {
                        $w->where('Status', 'Active')
                          ->orWhere('Status', 'active')
                          ->orWhereNull('Status');
                    });

                if (mb_strlen($term) > 0) {
                    $like = "%{$term}%";

                    $query->where(function ($q) use ($like) {
                        $q->where('DiseaseName', 'like', $like)
                            ->orWhere('ICD10Code', 'like', $like)
                            ->orWhere('Category', 'like', $like)
                            ->orWhere('Description', 'like', $like);
                    })
                    ->orderBy('DiseaseName');
                } else {
                    $query->orderBy('CatalogID', 'asc');
                }

                $results = $query->limit($limit)->get();

                foreach ($results as $d) {
                    $days = (int)($d->DefaultFollowUpDays ?: 14);
                    $targetDate = date('Y-m-d', strtotime("+{$days} days"));
                    $code = (string)$d->ICD10Code;
                    $existingCodes[strtoupper($code)] = true;

                    $formatted[] = [
                        'id'               => (int)$d->CatalogID,
                        'name'             => $d->DiseaseName,
                        'disease_name'     => $d->DiseaseName,
                        'icd_code'         => $code,
                        'icd10_code'       => $code,
                        'category'         => $d->Category ?: 'General Clinical',
                        'description'      => $d->Description ?: '',
                        'default_severity' => $d->DefaultSeverity ?: 'Moderate',
                        'display_label'    => "{$d->DiseaseName} (ICD-10: {$code})",
                        'treatment_plan'   => [
                            'goal'               => $d->DefaultGoal ?: "Symptom stabilization, control of {$d->DiseaseName}, and clinical recovery.",
                            'lifestyle'          => $d->DefaultLifestyle ?: "Adequate rest, balanced nutrition, stay hydrated, avoid smoking and alcohol.",
                            'medication_plan'    => $d->DefaultMedication ?: "Prescribed pharmacological therapy and supportive clinical care as indicated.",
                            'follow_up_schedule' => $d->DefaultFollowUp ?: "2 Weeks",
                            'follow_up_days'     => $days,
                            'follow_up_date'     => $targetDate,
                        ]
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Log warning and gracefully continue to master catalog fallback
            \Log::warning("SearchApiController::searchDiseases DB lookup exception: " . $e->getMessage());
        }

        // Augment with Comprehensive Master ICD-10 Catalog
        $masterList = self::getMasterDiseasesCatalog();
        $termLower = mb_strtolower($term);

        foreach ($masterList as $item) {
            $codeUpper = strtoupper($item['icd_code']);
            if (isset($existingCodes[$codeUpper])) {
                continue;
            }

            $matched = false;
            if ($termLower === '') {
                $matched = count($formatted) < $limit;
            } else {
                $nameLower = mb_strtolower($item['name']);
                $codeLower = mb_strtolower($item['icd_code']);
                $catLower  = mb_strtolower($item['category']);
                $descLower = mb_strtolower($item['description'] ?? '');

                if (str_contains($nameLower, $termLower) ||
                    str_contains($codeLower, $termLower) ||
                    str_contains($catLower, $termLower) ||
                    str_contains($descLower, $termLower)) {
                    $matched = true;
                }
            }

            if ($matched) {
                $formatted[] = $item;
                $existingCodes[$codeUpper] = true;
                if (count($formatted) >= $limit) {
                    break;
                }
            }
        }

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
            $fullName = trim($m->GenericName . ($m->BrandName ? " ({$m->BrandName})" : '') . " {$m->Strength}");
            return [
                'id'            => (int)$m->InventoryID,
                'InventoryID'   => (int)$m->InventoryID,
                'item_code'     => $m->ItemCode,
                'ItemCode'      => $m->ItemCode,
                'generic_name'  => $m->GenericName,
                'GenericName'   => $m->GenericName,
                'brand_name'    => $m->BrandName ?: '',
                'BrandName'     => $m->BrandName ?: '',
                'full_name'     => $fullName,
                'dosage_form'   => $m->DosageForm,
                'DosageForm'    => $m->DosageForm,
                'strength'      => $m->Strength,
                'Strength'      => $m->Strength,
                'category'      => $m->Category,
                'Category'      => $m->Category,
                'selling_price' => (float)$m->SellingPrice,
                'SellingPrice'  => (float)$m->SellingPrice,
                'current_stock' => (int)$m->CurrentStock,
                'CurrentStock'  => (int)$m->CurrentStock,
                'batch_number'  => $m->BatchNumber ?? '',
                'BatchNumber'   => $m->BatchNumber ?? '',
                'supplier'      => $m->Supplier ?? '',
                'Supplier'      => $m->Supplier ?? '',
                'expiry_date'   => $m->ExpiryDate ? substr((string)$m->ExpiryDate, 0, 10) : '',
                'ExpiryDate'    => $m->ExpiryDate ? substr((string)$m->ExpiryDate, 0, 10) : '',
                'status'        => $m->Status,
                'Status'        => $m->Status,
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
                'CatalogID'       => (int)$t->CatalogID,
                'test_code'       => $t->TestCode,
                'TestCode'        => $t->TestCode,
                'test_name'       => $t->TestName,
                'TestName'        => $t->TestName,
                'category'        => $t->Category,
                'Category'        => $t->Category,
                'specimen_type'   => $t->SpecimenType,
                'SpecimenType'    => $t->SpecimenType,
                'turnaround_time' => $t->TurnaroundTime,
                'TurnaroundTime'  => $t->TurnaroundTime,
                'standard_price'  => (float)$t->StandardPrice,
                'StandardPrice'   => (float)$t->StandardPrice,
                'status'          => $t->Status,
                'Status'          => $t->Status,
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

    /**
     * Comprehensive Master ICD-10 Hospital Catalog Fallback
     */
    public static function getMasterDiseasesCatalog(): array
    {
        return [
            // Rabies & Animal Bites
            [
                'id' => 9001,
                'name' => 'Rabies Virus Infection (Lyssavirus Encephalitis)',
                'disease_name' => 'Rabies Virus Infection (Lyssavirus Encephalitis)',
                'icd_code' => 'A82.9',
                'icd10_code' => 'A82.9',
                'category' => 'Zoonotic Infection',
                'description' => 'Acute fatal viral encephalomyelitis caused by rabies virus transmitted via animal bite/saliva; requires urgent post-exposure prophylaxis.',
                'default_severity' => 'Critical',
                'display_label' => 'Rabies Virus Infection (Lyssavirus Encephalitis) (ICD-10: A82.9)',
                'treatment_plan' => [
                    'goal' => 'Immediate post-exposure prophylaxis to neutralize rabies virus prior to CNS infiltration; intensive supportive critical care.',
                    'lifestyle' => 'Immediate aggressive wound flushing under running water with detergent soap for at least 15 minutes; isolate biting domestic animal for 14-day observation.',
                    'medication_plan' => 'Rabies Vaccine (PCECV/PVRV) Day 0, 3, 7, 28; Equine/Human Rabies Immune Globulin (ERIG/HRIG) infiltration at wound site; Tetanus Toxoid 0.5 mL IM; Co-amoxiclav 625mg PO BID x 7 days.',
                    'follow_up_schedule' => 'Day 3 Rabies Dose',
                    'follow_up_days' => 3,
                    'follow_up_date' => date('Y-m-d', strtotime('+3 days')),
                ]
            ],
            [
                'id' => 9002,
                'name' => 'Contact / Exposure to Rabies (Animal Bite Exposure)',
                'disease_name' => 'Contact / Exposure to Rabies (Animal Bite Exposure)',
                'icd_code' => 'Z20.3',
                'icd10_code' => 'Z20.3',
                'category' => 'Preventive / Emergency',
                'description' => 'Potential lyssavirus exposure from mammalian bite or scratch requiring anti-rabies vaccination protocol.',
                'default_severity' => 'Severe',
                'display_label' => 'Contact / Exposure to Rabies (Animal Bite Exposure) (ICD-10: Z20.3)',
                'treatment_plan' => [
                    'goal' => 'Prevent rabies virus invasion following animal exposure and prevent secondary bacterial wound infection.',
                    'lifestyle' => 'Wound cleaning with soap and running water for 15 minutes; Observe biting animal for 14 days if domestic.',
                    'medication_plan' => 'Anti-Rabies Vaccine (PCECV) on Day 0, 3, 7, 28; Tetanus Toxoid 0.5ml IM; Co-amoxiclav 625mg PO BID x 5 days.',
                    'follow_up_schedule' => 'Day 3 Rabies Dose',
                    'follow_up_days' => 3,
                    'follow_up_date' => date('Y-m-d', strtotime('+3 days')),
                ]
            ],
            [
                'id' => 9003,
                'name' => 'Bitten by Dog (Animal Bite - Dog)',
                'disease_name' => 'Bitten by Dog (Animal Bite - Dog)',
                'icd_code' => 'W54.0',
                'icd10_code' => 'W54.0',
                'category' => 'Injury / Trauma',
                'description' => 'Canine bite injury with risk of secondary soft tissue infection (Pasteurella, Capnocytophaga) and rabies transmission.',
                'default_severity' => 'Moderate',
                'display_label' => 'Bitten by Dog (Animal Bite - Dog) (ICD-10: W54.0)',
                'treatment_plan' => [
                    'goal' => 'Wound debridement, rabies post-exposure prophylaxis, tetanus prophylaxis, and secondary infection prevention.',
                    'lifestyle' => 'Wash wound thoroughly with clean water and povidone-iodine; do not tightly suture high-risk puncture wounds; keep dressing dry.',
                    'medication_plan' => 'Co-amoxiclav 625mg PO BID x 7 days; Anti-Rabies Vaccine (PCECV) Day 0, 3, 7, 28; Tetanus Toxoid 0.5 mL IM; Paracetamol 500mg PO Q6H PRN for pain.',
                    'follow_up_schedule' => 'Day 3 Rabies Dose & Wound Check',
                    'follow_up_days' => 3,
                    'follow_up_date' => date('Y-m-d', strtotime('+3 days')),
                ]
            ],
            [
                'id' => 9004,
                'name' => 'Contact with Cat (Cat Scratch / Bite)',
                'disease_name' => 'Contact with Cat (Cat Scratch / Bite)',
                'icd_code' => 'W55.0',
                'icd10_code' => 'W55.0',
                'category' => 'Injury / Trauma',
                'description' => 'Feline scratch or bite with high risk of deep inoculation and Bartonella / Pasteurella infection.',
                'default_severity' => 'Moderate',
                'display_label' => 'Contact with Cat (Cat Scratch / Bite) (ICD-10: W55.0)',
                'treatment_plan' => [
                    'goal' => 'Prevent cat scratch disease (Bartonella henselae) and rabies transmission; monitor localized lymphadenopathy.',
                    'lifestyle' => 'Wash scratch or bite site immediately with antiseptic soap; observe cat for 14 days.',
                    'medication_plan' => 'Co-amoxiclav 625mg PO BID x 7 days or Azithromycin 500mg PO OD x 3 days; Anti-Rabies Vaccine Day 0, 3, 7; Tetanus Toxoid 0.5 mL IM.',
                    'follow_up_schedule' => 'Day 3 Post-Exposure Dose',
                    'follow_up_days' => 3,
                    'follow_up_date' => date('Y-m-d', strtotime('+3 days')),
                ]
            ],

            // Vector-Borne & Infectious
            [
                'id' => 9005,
                'name' => 'Dengue Fever without Warning Signs',
                'disease_name' => 'Dengue Fever without Warning Signs',
                'icd_code' => 'A90',
                'icd10_code' => 'A90',
                'category' => 'Vector-Borne Infection',
                'description' => 'Acute viral flavivirus infection transmitted by Aedes aegypti; high fever, myalgia, arthralgia, retro-orbital pain.',
                'default_severity' => 'Moderate',
                'display_label' => 'Dengue Fever without Warning Signs (ICD-10: A90)',
                'treatment_plan' => [
                    'goal' => 'Maintenance of intravascular hydration, fever control, and strict prevention of severe plasma leakage.',
                    'lifestyle' => 'Strict bed rest, use mosquito net or repellant, consume oral rehydration solution (ORS) and fruit juices. Avoid NSAIDs (aspirin, ibuprofen, mefenamic acid).',
                    'medication_plan' => 'Paracetamol 500mg PO every 6 hours PRN for fever; Oral Rehydration Salts (ORS) 1 sachet per liter of water as tolerated.',
                    'follow_up_schedule' => 'Daily CBC & Platelet Monitoring',
                    'follow_up_days' => 1,
                    'follow_up_date' => date('Y-m-d', strtotime('+1 day')),
                ]
            ],
            [
                'id' => 9006,
                'name' => 'Dengue Hemorrhagic Fever with Warning Signs',
                'disease_name' => 'Dengue Hemorrhagic Fever with Warning Signs',
                'icd_code' => 'A91',
                'icd10_code' => 'A91',
                'category' => 'Vector-Borne Infection',
                'description' => 'Dengue infection with persistent vomiting, mucosal bleeding, abdominal tenderness, or thrombocytopenia.',
                'default_severity' => 'Severe',
                'display_label' => 'Dengue Hemorrhagic Fever with Warning Signs (ICD-10: A91)',
                'treatment_plan' => [
                    'goal' => 'Immediate hospital admission, close hemodynamic monitoring, and controlled intravenous crystalloid resuscitation.',
                    'lifestyle' => 'Inpatient bed rest; strict hourly intake and output chart; serial hematocrit and platelet count monitoring.',
                    'medication_plan' => 'Intravenous fluid hydration (D5 0.9 NaCl or Plain Lactated Ringer\'s at maintenance/resuscitation rate); IV Paracetamol 1g Q6H for hyperpyrexia.',
                    'follow_up_schedule' => 'Hourly Inpatient Vitals Check',
                    'follow_up_days' => 1,
                    'follow_up_date' => date('Y-m-d', strtotime('+1 day')),
                ]
            ],
            [
                'id' => 9007,
                'name' => 'Typhoid Fever (Salmonella Typhi)',
                'disease_name' => 'Typhoid Fever (Salmonella Typhi)',
                'icd_code' => 'A01.0',
                'icd10_code' => 'A01.0',
                'category' => 'Infectious & Parasitic',
                'description' => 'Systemic enteric fever characterized by step-ladder pyrexia, hepatosplenomegaly, and headache.',
                'default_severity' => 'Severe',
                'display_label' => 'Typhoid Fever (Salmonella Typhi) (ICD-10: A01.0)',
                'treatment_plan' => [
                    'goal' => 'Eradication of Salmonella typhi, prevention of bowel perforation, and fever defervescence.',
                    'lifestyle' => 'Strict personal hygiene, drink only boiled/potable water, soft low-fiber diet during active intestinal inflammation.',
                    'medication_plan' => 'Ciprofloxacin 500mg PO BID x 10 days OR Ceftriaxone 2g IV OD x 7 days; Paracetamol 500mg PO Q6H PRN.',
                    'follow_up_schedule' => '5 Days',
                    'follow_up_days' => 5,
                    'follow_up_date' => date('Y-m-d', strtotime('+5 days')),
                ]
            ],
            [
                'id' => 9008,
                'name' => 'Cholera (Vibrio Cholerae)',
                'disease_name' => 'Cholera (Vibrio Cholerae)',
                'icd_code' => 'A00.9',
                'icd10_code' => 'A00.9',
                'category' => 'Infectious & Parasitic',
                'description' => 'Severe acute watery diarrhea ("rice-water stools") causing rapid dehydration and hypovolemic shock.',
                'default_severity' => 'Critical',
                'display_label' => 'Cholera (Vibrio Cholerae) (ICD-10: A00.9)',
                'treatment_plan' => [
                    'goal' => 'Immediate aggressive rehydration, correction of metabolic acidosis and hypokalemia, antimicrobial eradication.',
                    'lifestyle' => 'Strict enteric isolation, safe water sanitation, continuous ORS administration.',
                    'medication_plan' => 'Ringer\'s Lactate IV rapid infusion; Doxycycline 300mg PO single dose OR Azithromycin 1g PO single dose; Zinc supplementation.',
                    'follow_up_schedule' => 'Daily Inpatient Rounds',
                    'follow_up_days' => 1,
                    'follow_up_date' => date('Y-m-d', strtotime('+1 day')),
                ]
            ],
            [
                'id' => 9009,
                'name' => 'Leptospirosis, Unspecified',
                'disease_name' => 'Leptospirosis, Unspecified',
                'icd_code' => 'A27.9',
                'icd10_code' => 'A27.9',
                'category' => 'Zoonotic Infection',
                'description' => 'Spirochetal infection following exposure to contaminated flood water; calf tenderness, conjunctival suffusion.',
                'default_severity' => 'Severe',
                'display_label' => 'Leptospirosis, Unspecified (ICD-10: A27.9)',
                'treatment_plan' => [
                    'goal' => 'Elimination of Leptospira, prevention of renal failure (Weil\'s syndrome) and pulmonary hemorrhage.',
                    'lifestyle' => 'Avoid wading in flood waters; wear rubber boots; maintain hydration and report dark urine or jaundice.',
                    'medication_plan' => 'Doxycycline 100mg PO BID x 7 days (mild) OR Penicillin G 1.5 million units IV Q6H x 7 days (moderate-severe).',
                    'follow_up_schedule' => '3 Days (Renal Panel Check)',
                    'follow_up_days' => 3,
                    'follow_up_date' => date('Y-m-d', strtotime('+3 days')),
                ]
            ],
            [
                'id' => 9010,
                'name' => 'Tuberculosis of Lung (Pulmonary TB)',
                'disease_name' => 'Tuberculosis of Lung (Pulmonary TB)',
                'icd_code' => 'A15.0',
                'icd10_code' => 'A15.0',
                'category' => 'Mycobacterial Infection',
                'description' => 'Chronic Mycobacterium tuberculosis lung infection with productive cough >2 weeks, night sweats, and weight loss.',
                'default_severity' => 'Moderate',
                'display_label' => 'Tuberculosis of Lung (Pulmonary TB) (ICD-10: A15.0)',
                'treatment_plan' => [
                    'goal' => 'Microbiologic cure via DOTS protocol, prevent transmission, and prevent drug resistance.',
                    'lifestyle' => 'Cough etiquette, well-ventilated living quarters, high-protein diet, absolute smoking cessation.',
                    'medication_plan' => 'Fixed-dose combination anti-TB therapy (Rifampicin, Isoniazid, Pyrazinamide, Ethambutol) daily for 2 months intensive phase, then Rifampicin/Isoniazid for 4 months.',
                    'follow_up_schedule' => 'Monthly DOTS Review',
                    'follow_up_days' => 30,
                    'follow_up_date' => date('Y-m-d', strtotime('+30 days')),
                ]
            ],
            [
                'id' => 9011,
                'name' => 'Malaria (Plasmodium Falciparum / Vivax)',
                'disease_name' => 'Malaria (Plasmodium Falciparum / Vivax)',
                'icd_code' => 'B50.9',
                'icd10_code' => 'B50.9',
                'category' => 'Vector-Borne Infection',
                'description' => 'Protozoan mosquito-borne illness presenting with paroxysms of shaking chills, high fever, and sweating.',
                'default_severity' => 'Severe',
                'display_label' => 'Malaria (Plasmodium Falciparum / Vivax) (ICD-10: B50.9)',
                'treatment_plan' => [
                    'goal' => 'Clearance of blood parasites, prevention of cerebral malaria, and prevention of relapse.',
                    'lifestyle' => 'Insecticide-treated bed nets, prompt fever reporting, adequate oral fluids.',
                    'medication_plan' => 'Artemether-Lumefantrine (Coartem) standard weight-based 3-day course; Primaquine single dose on Day 1 for gametocyte clearance.',
                    'follow_up_schedule' => 'Day 3 & Day 7 Blood Smear',
                    'follow_up_days' => 3,
                    'follow_up_date' => date('Y-m-d', strtotime('+3 days')),
                ]
            ],
            [
                'id' => 9012,
                'name' => 'COVID-19 (SARS-CoV-2 Acute Respiratory Disease)',
                'disease_name' => 'COVID-19 (SARS-CoV-2 Acute Respiratory Disease)',
                'icd_code' => 'U07.1',
                'icd10_code' => 'U07.1',
                'category' => 'Viral Infection',
                'description' => 'Acute respiratory illness caused by SARS-CoV-2 presenting with fever, cough, loss of taste/smell, and dyspnea.',
                'default_severity' => 'Moderate',
                'display_label' => 'COVID-19 (SARS-CoV-2 Acute Respiratory Disease) (ICD-10: U07.1)',
                'treatment_plan' => [
                    'goal' => 'Prevent hypoxic respiratory failure, manage inflammatory cascade, and isolate during infectious window.',
                    'lifestyle' => '7-day home isolation, pulse oximetry monitoring twice daily (report SpO2 <94%), mask wearing, fluid hydration.',
                    'medication_plan' => 'Paracetamol 500mg PO Q6H PRN; Vitamin C 500mg + Zinc 20mg PO OD; Nirmatrelvir/Ritonavir (Paxlovid) for high-risk patients within 5 days.',
                    'follow_up_schedule' => '5 Days',
                    'follow_up_days' => 5,
                    'follow_up_date' => date('Y-m-d', strtotime('+5 days')),
                ]
            ],
            [
                'id' => 9013,
                'name' => 'Varicella (Chickenpox) without Complication',
                'disease_name' => 'Varicella (Chickenpox) without Complication',
                'icd_code' => 'B01.9',
                'icd10_code' => 'B01.9',
                'category' => 'Viral Exanthem',
                'description' => 'Varicella-zoster virus presenting with polymorphic pruritic maculopapular and vesicular skin eruptions.',
                'default_severity' => 'Mild',
                'display_label' => 'Varicella (Chickenpox) without Complication (ICD-10: B01.9)',
                'treatment_plan' => [
                    'goal' => 'Symptom relief, prevention of secondary bacterial superinfection from excoriation, and isolation until all lesions crust.',
                    'lifestyle' => 'Keep fingernails short and clean; cool sponge baths; avoid scratching lesions; isolate from non-immune individuals.',
                    'medication_plan' => 'Calamine lotion topically TID; Cetirizine 10mg PO OD for itching; Paracetamol 500mg PO Q6H PRN (Avoid aspirin); Acyclovir 800mg 5x/day if initiated within 24h.',
                    'follow_up_schedule' => '7 Days',
                    'follow_up_days' => 7,
                    'follow_up_date' => date('Y-m-d', strtotime('+7 days')),
                ]
            ],
            [
                'id' => 9014,
                'name' => 'Diarrhea and Gastroenteritis (Presumed Infectious Origin)',
                'disease_name' => 'Diarrhea and Gastroenteritis (Presumed Infectious Origin)',
                'icd_code' => 'A09',
                'icd10_code' => 'A09',
                'category' => 'Infectious & Parasitic',
                'description' => 'Acute loose/watery stools with nausea, vomiting, or abdominal cramps; common in food/waterborne exposure.',
                'default_severity' => 'Moderate',
                'display_label' => 'Diarrhea and Gastroenteritis (Presumed Infectious Origin) (ICD-10: A09)',
                'treatment_plan' => [
                    'goal' => 'Correction of fluid/electrolyte deficit, restoration of gastrointestinal mucosal barrier, and prevention of acute renal injury.',
                    'lifestyle' => 'Oral rehydration therapy with 200-400 mL ORS after each loose stool; BRAT diet (bananas, rice, applesauce, toast); avoid dairy, caffeine, and fatty foods.',
                    'medication_plan' => 'Oral Rehydration Salts (ORS) 1 sachet in 1 liter clean drinking water; Zinc Sulfate 20mg PO OD x 14 days; Racecadotril 100mg PO TID PRN x 3 days.',
                    'follow_up_schedule' => '3 Days',
                    'follow_up_days' => 3,
                    'follow_up_date' => date('Y-m-d', strtotime('+3 days')),
                ]
            ],

            // Respiratory Diseases
            [
                'id' => 9015,
                'name' => 'Acute Upper Respiratory Tract Infection (URTI)',
                'disease_name' => 'Acute Upper Respiratory Tract Infection (URTI)',
                'icd_code' => 'J06.9',
                'icd10_code' => 'J06.9',
                'category' => 'Respiratory Infection',
                'description' => 'Acute viral inflammation of upper respiratory tract presenting with rhinorrhea, nasal congestion, cough, and low-grade fever.',
                'default_severity' => 'Mild',
                'display_label' => 'Acute Upper Respiratory Tract Infection (URTI) (ICD-10: J06.9)',
                'treatment_plan' => [
                    'goal' => 'Symptomatic relief of nasal congestion and fever, adequate hydration, and prevention of secondary bacterial rhinosinusitis.',
                    'lifestyle' => 'Warm saline gargles, adequate oral fluid intake (>2L/day), steam inhalation, adequate sleep, and face mask wearing.',
                    'medication_plan' => 'Paracetamol 500mg PO Q6H PRN for fever/headache; Cetirizine 10mg PO OD at bedtime; Saline nasal spray 2 puffs per nostril TID.',
                    'follow_up_schedule' => '5 Days',
                    'follow_up_days' => 5,
                    'follow_up_date' => date('Y-m-d', strtotime('+5 days')),
                ]
            ],
            [
                'id' => 9016,
                'name' => 'Acute Bronchitis, Unspecified',
                'disease_name' => 'Acute Bronchitis, Unspecified',
                'icd_code' => 'J20.9',
                'icd10_code' => 'J20.9',
                'category' => 'Lower Respiratory',
                'description' => 'Acute inflammation of tracheobronchial tree with productive cough, mild chest discomfort, and absence of consolidation on auscultation.',
                'default_severity' => 'Mild',
                'display_label' => 'Acute Bronchitis, Unspecified (ICD-10: J20.9)',
                'treatment_plan' => [
                    'goal' => 'Symptomatic relief of bronchial cough, clearance of airway secretions, and avoidance of unnecessary antibiotics.',
                    'lifestyle' => 'Increased fluid intake to thin secretions; avoid smoke, dust, and vaping; steam inhalation.',
                    'medication_plan' => 'Ambroxol 30mg PO TID or Carbocisteine 500mg PO TID x 7 days; Salbutamol 2mg PO TID if bronchospasm is present.',
                    'follow_up_schedule' => '1 Week',
                    'follow_up_days' => 7,
                    'follow_up_date' => date('Y-m-d', strtotime('+7 days')),
                ]
            ],
            [
                'id' => 9017,
                'name' => 'Community-Acquired Pneumonia (CAP), Unspecified',
                'disease_name' => 'Community-Acquired Pneumonia (CAP), Unspecified',
                'icd_code' => 'J18.9',
                'icd10_code' => 'J18.9',
                'category' => 'Lower Respiratory',
                'description' => 'Acute parenchymal lung infection acquired outside hospital setting; productive cough, fever, crackles/rales on auscultation.',
                'default_severity' => 'Moderate',
                'display_label' => 'Community-Acquired Pneumonia (CAP), Unspecified (ICD-10: J18.9)',
                'treatment_plan' => [
                    'goal' => 'Eradication of respiratory pathogens, restoration of normal oxygenation, and resolution of pulmonary consolidation.',
                    'lifestyle' => 'Deep breathing and coughing exercises, chest physiotherapy, elevate head of bed 30 degrees, avoid tobacco smoke.',
                    'medication_plan' => 'Co-amoxiclav 625mg PO BID x 7 days + Azithromycin 500mg PO OD x 3 days OR Ceftriaxone 1g IV OD for moderate/high risk; Paracetamol 500mg PO Q6H PRN.',
                    'follow_up_schedule' => '3 Days for Clinical Response',
                    'follow_up_days' => 3,
                    'follow_up_date' => date('Y-m-d', strtotime('+3 days')),
                ]
            ],
            [
                'id' => 9018,
                'name' => 'Bronchial Asthma with (Acute) Exacerbation',
                'disease_name' => 'Bronchial Asthma with (Acute) Exacerbation',
                'icd_code' => 'J45.901',
                'icd10_code' => 'J45.901',
                'category' => 'Chronic Respiratory',
                'description' => 'Acute flare of reversible airway obstruction characterized by wheezing, chest tightness, dyspnea, and prolonged expiratory phase.',
                'default_severity' => 'Severe',
                'display_label' => 'Bronchial Asthma with (Acute) Exacerbation (ICD-10: J45.901)',
                'treatment_plan' => [
                    'goal' => 'Rapid reversal of airflow obstruction, correction of hypoxemia, and prevention of respiratory muscle fatigue.',
                    'lifestyle' => 'Identify and eliminate environmental asthma triggers (pollen, cold air, smoke, pet dander); keep reliever inhaler accessible.',
                    'medication_plan' => 'Salbutamol + Ipratropium nebulization every 20 minutes x 3 doses; Prednisone 40mg PO OD x 5 days; Budesonide/Formoterol maintenance inhaler.',
                    'follow_up_schedule' => '3 Days',
                    'follow_up_days' => 3,
                    'follow_up_date' => date('Y-m-d', strtotime('+3 days')),
                ]
            ],
            [
                'id' => 9019,
                'name' => 'Chronic Obstructive Pulmonary Disease (COPD)',
                'disease_name' => 'Chronic Obstructive Pulmonary Disease (COPD)',
                'icd_code' => 'J44.9',
                'icd10_code' => 'J44.9',
                'category' => 'Chronic Respiratory',
                'description' => 'Persistent respiratory symptoms and airflow limitation caused by significant exposure to noxious particles or gases.',
                'default_severity' => 'Moderate',
                'display_label' => 'Chronic Obstructive Pulmonary Disease (COPD) (ICD-10: J44.9)',
                'treatment_plan' => [
                    'goal' => 'Improve exercise tolerance, reduce exacerbation frequency, and preserve lung function.',
                    'lifestyle' => 'Smoking cessation program, pulmonary rehabilitation, annual influenza and pneumococcal vaccination.',
                    'medication_plan' => 'Tiotropium Respimat 2.5mcg 2 puffs OD; Salbutamol MDI 2 puffs Q4-6H PRN for acute shortness of breath.',
                    'follow_up_schedule' => '1 Month',
                    'follow_up_days' => 30,
                    'follow_up_date' => date('Y-m-d', strtotime('+30 days')),
                ]
            ],

            // Cardiovascular
            [
                'id' => 9020,
                'name' => 'Essential (Primary) Hypertension',
                'disease_name' => 'Essential (Primary) Hypertension',
                'icd_code' => 'I10',
                'icd10_code' => 'I10',
                'category' => 'Cardiovascular',
                'description' => 'Persistently elevated systemic arterial blood pressure (>=140/90 mmHg) without identified secondary cause.',
                'default_severity' => 'Moderate',
                'display_label' => 'Essential (Primary) Hypertension (ICD-10: I10)',
                'treatment_plan' => [
                    'goal' => 'Target blood pressure <130/80 mmHg, reduction of cardiovascular event risk, and prevention of end-organ damage.',
                    'lifestyle' => 'DASH dietary pattern (low sodium <2g/day, high potassium), 150 minutes/week moderate aerobic exercise, weight reduction, alcohol moderation.',
                    'medication_plan' => 'Amlodipine 5mg to 10mg PO OD in the morning OR Losartan 50mg to 100mg PO OD; Hydrochlorothiazide 12.5mg PO OD if combo needed.',
                    'follow_up_schedule' => '2 Weeks',
                    'follow_up_days' => 14,
                    'follow_up_date' => date('Y-m-d', strtotime('+14 days')),
                ]
            ],
            [
                'id' => 9021,
                'name' => 'Hypertensive Heart Disease without Heart Failure',
                'disease_name' => 'Hypertensive Heart Disease without Heart Failure',
                'icd_code' => 'I11.9',
                'icd10_code' => 'I11.9',
                'category' => 'Cardiovascular',
                'description' => 'Left ventricular hypertrophy and cardiac structural remodeling resulting from longstanding systemic arterial hypertension.',
                'default_severity' => 'Moderate',
                'display_label' => 'Hypertensive Heart Disease without Heart Failure (ICD-10: I11.9)',
                'treatment_plan' => [
                    'goal' => 'Regression of left ventricular hypertrophy, strict BP control <130/80 mmHg, and prevention of diastolic dysfunction.',
                    'lifestyle' => 'Strict low-sodium diet (<1.5g sodium/day), daily home blood pressure diary, moderate exercise, weight management.',
                    'medication_plan' => 'Losartan 50mg PO OD + Amlodipine 5mg PO OD; Carvedilol 6.25mg to 12.5mg PO BID if indicated.',
                    'follow_up_schedule' => '2 Weeks',
                    'follow_up_days' => 14,
                    'follow_up_date' => date('Y-m-d', strtotime('+14 days')),
                ]
            ],
            [
                'id' => 9022,
                'name' => 'Heart Failure, Unspecified (Congestive Heart Failure)',
                'disease_name' => 'Heart Failure, Unspecified (Congestive Heart Failure)',
                'icd_code' => 'I50.9',
                'icd10_code' => 'I50.9',
                'category' => 'Cardiovascular',
                'description' => 'Clinical syndrome characterized by bipedal edema, paroxysmal nocturnal dyspnea, orthopnea, and elevated JVP.',
                'default_severity' => 'Severe',
                'display_label' => 'Heart Failure, Unspecified (Congestive Heart Failure) (ICD-10: I50.9)',
                'treatment_plan' => [
                    'goal' => 'Decongestion, symptom improvement, neurohormonal blockade, and reduction of hospitalization.',
                    'lifestyle' => 'Fluid restriction to 1.5-2.0 liters/day, strict low salt intake (<2g/day), daily morning weight check.',
                    'medication_plan' => 'Furosemide 20mg to 40mg PO OD (morning); Sacubitril/Valsartan or Enalapril; Bisoprolol 2.5mg PO OD; Spironolactone 25mg PO OD.',
                    'follow_up_schedule' => '1 Week',
                    'follow_up_days' => 7,
                    'follow_up_date' => date('Y-m-d', strtotime('+7 days')),
                ]
            ],
            [
                'id' => 9023,
                'name' => 'Atherosclerotic Heart Disease (Coronary Artery Disease)',
                'disease_name' => 'Atherosclerotic Heart Disease (Coronary Artery Disease)',
                'icd_code' => 'I25.10',
                'icd10_code' => 'I25.10',
                'category' => 'Cardiovascular',
                'description' => 'Lipid plaque buildup in coronary arteries reducing myocardial perfusion; presents with exertional angina or dyspnea.',
                'default_severity' => 'Severe',
                'display_label' => 'Atherosclerotic Heart Disease (Coronary Artery Disease) (ICD-10: I25.10)',
                'treatment_plan' => [
                    'goal' => 'Secondary prevention of acute coronary events, relief of angina, and stabilization of coronary plaques.',
                    'lifestyle' => 'Heart-healthy Mediterranean diet, smoking cessation, stress reduction, cardiac rehabilitation exercise.',
                    'medication_plan' => 'Aspirin 80mg PO OD + Atorvastatin 40mg PO OD at bedtime; Metoprolol tartrate 50mg PO BID; Isosorbide mononitrate 30mg PO OD.',
                    'follow_up_schedule' => '2 Weeks',
                    'follow_up_days' => 14,
                    'follow_up_date' => date('Y-m-d', strtotime('+14 days')),
                ]
            ],
            [
                'id' => 9024,
                'name' => 'Acute Ischemic Stroke / Cerebrovascular Accident (CVA)',
                'disease_name' => 'Acute Ischemic Stroke / Cerebrovascular Accident (CVA)',
                'icd_code' => 'I63.9',
                'icd10_code' => 'I63.9',
                'category' => 'Cardiovascular / Neurological',
                'description' => 'Focal neurological deficit due to cerebral infarction; facial droop, hemiparesis, dysarthria, or aphasia.',
                'default_severity' => 'Critical',
                'display_label' => 'Acute Ischemic Stroke / Cerebrovascular Accident (CVA) (ICD-10: I63.9)',
                'treatment_plan' => [
                    'goal' => 'Emergency neuroprotection, airway preservation, secondary prevention, and early physical rehabilitation.',
                    'lifestyle' => 'Aspiration precautions, elevate head of bed, physical and occupational therapy, speech rehabilitation.',
                    'medication_plan' => 'Aspirin 80mg PO OD + Clopidogrel 75mg PO OD; Atorvastatin 40mg PO OD; Permissive hypertension management in acute phase.',
                    'follow_up_schedule' => '1 Week Post-Discharge',
                    'follow_up_days' => 7,
                    'follow_up_date' => date('Y-m-d', strtotime('+7 days')),
                ]
            ],

            // Endocrine & Metabolic
            [
                'id' => 9025,
                'name' => 'Type 2 Diabetes Mellitus without Complications',
                'disease_name' => 'Type 2 Diabetes Mellitus without Complications',
                'icd_code' => 'E11.9',
                'icd10_code' => 'E11.9',
                'category' => 'Endocrine & Metabolic',
                'description' => 'Insulin resistance and relative insulin deficiency resulting in chronic hyperglycemia (FBS >=126 mg/dL or HbA1c >=6.5%).',
                'default_severity' => 'Moderate',
                'display_label' => 'Type 2 Diabetes Mellitus without Complications (ICD-10: E11.9)',
                'treatment_plan' => [
                    'goal' => 'Achieve target HbA1c <7.0%, fasting blood glucose 80-130 mg/dL, and prevent microvascular/macrovascular complications.',
                    'lifestyle' => 'Diabetic meal planning (portion control, low glycemic index foods, avoid refined sugars), 150 minutes/week exercise, daily foot inspection.',
                    'medication_plan' => 'Metformin 500mg PO BID with meals (titrate to 1000mg BID); Gliclazide MR 30mg PO OD if glycemic target unreached.',
                    'follow_up_schedule' => '1 Month (FBS & HbA1c Review)',
                    'follow_up_days' => 30,
                    'follow_up_date' => date('Y-m-d', strtotime('+30 days')),
                ]
            ],
            [
                'id' => 9026,
                'name' => 'Pure Hypercholesterolemia / Dyslipidemia',
                'disease_name' => 'Pure Hypercholesterolemia / Dyslipidemia',
                'icd_code' => 'E78.0',
                'icd10_code' => 'E78.0',
                'category' => 'Endocrine & Metabolic',
                'description' => 'Elevated low-density lipoprotein cholesterol (LDL-C) or total cholesterol increasing cardiovascular atherosclerotic risk.',
                'default_severity' => 'Mild',
                'display_label' => 'Pure Hypercholesterolemia / Dyslipidemia (ICD-10: E78.0)',
                'treatment_plan' => [
                    'goal' => 'Lower LDL-C by >=50% or target <100 mg/dL (<70 mg/dL if high cardiovascular risk) and stabilize vascular plaques.',
                    'lifestyle' => 'Low saturated fat diet, replace trans fats with unsaturated oils, increase soluble fiber, regular aerobic exercise.',
                    'medication_plan' => 'Atorvastatin 20mg to 40mg PO OD at bedtime or Rosuvastatin 10mg PO OD; repeat lipid profile in 8-12 weeks.',
                    'follow_up_schedule' => '2 Months',
                    'follow_up_days' => 60,
                    'follow_up_date' => date('Y-m-d', strtotime('+60 days')),
                ]
            ],
            [
                'id' => 9027,
                'name' => 'Gouty Arthritis / Hyperuricemia, Unspecified',
                'disease_name' => 'Gouty Arthritis / Hyperuricemia, Unspecified',
                'icd_code' => 'M10.9',
                'icd10_code' => 'M10.9',
                'category' => 'Musculoskeletal / Metabolic',
                'description' => 'Monosodium urate crystal deposition in peripheral joints causing acute severe inflammatory monoarthritis (e.g. 1st MTP podagra).',
                'default_severity' => 'Moderate',
                'display_label' => 'Gouty Arthritis / Hyperuricemia, Unspecified (ICD-10: M10.9)',
                'treatment_plan' => [
                    'goal' => 'Rapid termination of acute joint inflammation/pain, followed by long-term serum uric acid lowering to <6.0 mg/dL.',
                    'lifestyle' => 'Avoid purine-rich foods (organ meats, anchovies, beer, high-fructose corn syrup); high fluid intake (>2.5L/day); cold pack application to joint.',
                    'medication_plan' => 'Colchicine 0.5mg PO BID during acute flare + Naproxen 500mg PO BID with meals; Allopurinol 100mg PO OD started 2 weeks after flare settles.',
                    'follow_up_schedule' => '2 Weeks',
                    'follow_up_days' => 14,
                    'follow_up_date' => date('Y-m-d', strtotime('+14 days')),
                ]
            ],

            // Gastrointestinal & Abdominal
            [
                'id' => 9028,
                'name' => 'Gastritis, Unspecified',
                'disease_name' => 'Gastritis, Unspecified',
                'icd_code' => 'K29.7',
                'icd10_code' => 'K29.7',
                'category' => 'Gastrointestinal',
                'description' => 'Acute or chronic mucosal inflammation of gastric lining presenting with epigastric burning, nausea, and early satiety.',
                'default_severity' => 'Mild',
                'display_label' => 'Gastritis, Unspecified (ICD-10: K29.7)',
                'treatment_plan' => [
                    'goal' => 'Gastric acid suppression, mucosal healing, and prevention of peptic ulcer formation or gastrointestinal hemorrhage.',
                    'lifestyle' => 'Avoid NSAIDs, alcohol, caffeine, and spicy/acidic foods; eat smaller frequent meals; do not lie down immediately after eating.',
                    'medication_plan' => 'Omeprazole 20mg to 40mg PO OD 30 minutes before breakfast x 14 days; Aluminum-Magnesium Hydroxide antacid 10mL PO TID PC & HS PRN.',
                    'follow_up_schedule' => '2 Weeks',
                    'follow_up_days' => 14,
                    'follow_up_date' => date('Y-m-d', strtotime('+14 days')),
                ]
            ],
            [
                'id' => 9029,
                'name' => 'Gastro-Esophageal Reflux Disease (GERD)',
                'disease_name' => 'Gastro-Esophageal Reflux Disease (GERD)',
                'icd_code' => 'K21.9',
                'icd10_code' => 'K21.9',
                'category' => 'Gastrointestinal',
                'description' => 'Retrograde flow of gastric contents into esophagus producing retrosternal heartburn, acid regurgitation, or chronic cough.',
                'default_severity' => 'Mild',
                'display_label' => 'Gastro-Esophageal Reflux Disease (GERD) (ICD-10: K21.9)',
                'treatment_plan' => [
                    'goal' => 'Relief of heartburn and regurgitation, healing of reflux esophagitis, and prevention of Barrett\'s esophagus.',
                    'lifestyle' => 'Elevate head of bed 6 inches; avoid eating within 3 hours of sleeping; avoid chocolate, peppermint, coffee, and tight clothing.',
                    'medication_plan' => 'Esomeprazole 40mg or Pantoprazole 40mg PO OD before morning meal x 4-8 weeks; Sodium alginate oral suspension 10mL PRN.',
                    'follow_up_schedule' => '4 Weeks',
                    'follow_up_days' => 28,
                    'follow_up_date' => date('Y-m-d', strtotime('+28 days')),
                ]
            ],
            [
                'id' => 9030,
                'name' => 'Acute Appendicitis, Other / Unspecified',
                'disease_name' => 'Acute Appendicitis, Other / Unspecified',
                'icd_code' => 'K35.80',
                'icd10_code' => 'K35.80',
                'category' => 'Surgical / Abdominal',
                'description' => 'Acute inflammation of vermiform appendix; periumbilical pain migrating to right lower quadrant (McBurney\'s sign), fever, anorexia.',
                'default_severity' => 'Severe',
                'display_label' => 'Acute Appendicitis, Other / Unspecified (ICD-10: K35.80)',
                'treatment_plan' => [
                    'goal' => 'Immediate surgical referral, prevention of appendiceal perforation, peritonitis, and intra-abdominal sepsis.',
                    'lifestyle' => 'Strict NPO (nothing by mouth); avoid hot compress or laxatives which increase perforation risk; urgent surgical admission.',
                    'medication_plan' => 'IV fluids (Plain Ringer\'s Lactate); Ceftriaxone 1g IV + Metronidazole 500mg IV; IV analgesia upon surgical evaluation.',
                    'follow_up_schedule' => 'Immediate Surgical Evaluation',
                    'follow_up_days' => 1,
                    'follow_up_date' => date('Y-m-d', strtotime('+1 day')),
                ]
            ],

            // Genitourinary & Renal
            [
                'id' => 9031,
                'name' => 'Urinary Tract Infection (UTI), Site Not Specified',
                'disease_name' => 'Urinary Tract Infection (UTI), Site Not Specified',
                'icd_code' => 'N39.0',
                'icd10_code' => 'N39.0',
                'category' => 'Genitourinary',
                'description' => 'Bacterial infection of urinary tract producing dysuria, urinary frequency, urgency, and suprapubic discomfort.',
                'default_severity' => 'Moderate',
                'display_label' => 'Urinary Tract Infection (UTI), Site Not Specified (ICD-10: N39.0)',
                'treatment_plan' => [
                    'goal' => 'Eradication of uropathogens, relief of dysuria, and prevention of ascending pyelonephritis.',
                    'lifestyle' => 'High oral fluid intake (>2.5-3.0 liters/day), void frequently, do not delay urination, wipe front to back, post-coital voiding.',
                    'medication_plan' => 'Cefuroxime 500mg PO BID x 7 days OR Ciprofloxacin 500mg PO BID x 5 days; Phenazopyridine 200mg PO TID x 2 days for severe dysuria.',
                    'follow_up_schedule' => '1 Week (Repeat Urinalysis)',
                    'follow_up_days' => 7,
                    'follow_up_date' => date('Y-m-d', strtotime('+7 days')),
                ]
            ],
            [
                'id' => 9032,
                'name' => 'Acute Pyelonephritis',
                'disease_name' => 'Acute Pyelonephritis',
                'icd_code' => 'N10',
                'icd10_code' => 'N10',
                'category' => 'Genitourinary / Renal',
                'description' => 'Infection of upper urinary tract/renal parenchyma; high spiking fevers, chills, flank pain, and costovertebral angle tenderness.',
                'default_severity' => 'Severe',
                'display_label' => 'Acute Pyelonephritis (ICD-10: N10)',
                'treatment_plan' => [
                    'goal' => 'Treatment of upper urinary tract infection, prevention of urosepsis, and preservation of renal parenchyma.',
                    'lifestyle' => 'Bed rest, vigorous hydration, monitor urine output and daily body temperature.',
                    'medication_plan' => 'Ceftriaxone 1g to 2g IV OD OR Ciprofloxacin 500mg PO BID x 10-14 days; Paracetamol 500mg PO Q6H for fever.',
                    'follow_up_schedule' => '3 Days for Culture Review',
                    'follow_up_days' => 3,
                    'follow_up_date' => date('Y-m-d', strtotime('+3 days')),
                ]
            ],
            [
                'id' => 9033,
                'name' => 'Urinary Calculus (Nephrolithiasis / Urolithiasis)',
                'disease_name' => 'Urinary Calculus (Nephrolithiasis / Urolithiasis)',
                'icd_code' => 'N20.9',
                'icd10_code' => 'N20.9',
                'category' => 'Genitourinary / Renal',
                'description' => 'Stones in kidney or ureter producing acute spasmodic flank pain radiating to groin (renal colic), hematuria, and nausea.',
                'default_severity' => 'Moderate',
                'display_label' => 'Urinary Calculus (Nephrolithiasis / Urolithiasis) (ICD-10: N20.9)',
                'treatment_plan' => [
                    'goal' => 'Analgesia, facilitation of stone expulsion, and prevention of obstructive hydronephrosis.',
                    'lifestyle' => 'High fluid intake (>=2.5-3.0 liters/day), strain urine to catch passed calculus for composition analysis, reduce salt intake.',
                    'medication_plan' => 'Ketorolac 30mg IM/IV or Tramadol 50mg PO Q8H PRN for colic; Tamsulosin 0.4mg PO OD at bedtime for medical expulsive therapy.',
                    'follow_up_schedule' => '1 Week (Renal Ultrasound Check)',
                    'follow_up_days' => 7,
                    'follow_up_date' => date('Y-m-d', strtotime('+7 days')),
                ]
            ],

            // Musculoskeletal
            [
                'id' => 9034,
                'name' => 'Low Back Pain / Lumbago (Mechanical Lumbar Strain)',
                'disease_name' => 'Low Back Pain / Lumbago (Mechanical Lumbar Strain)',
                'icd_code' => 'M54.5',
                'icd10_code' => 'M54.5',
                'category' => 'Musculoskeletal',
                'description' => 'Acute or subacute paraspinal muscle spasm or ligamentous sprain of lumbar spine without radiculopathy.',
                'default_severity' => 'Mild',
                'display_label' => 'Low Back Pain / Lumbago (Mechanical Lumbar Strain) (ICD-10: M54.5)',
                'treatment_plan' => [
                    'goal' => 'Pain reduction, relief of paraspinal muscle spasm, early return to functional activities, and core strengthening.',
                    'lifestyle' => 'Avoid prolonged bed rest (>48h); apply warm compresses; maintain proper ergonomic lifting technique with knees bent.',
                    'medication_plan' => 'Ibuprofen 400mg PO TID with meals OR Celecoxib 200mg PO OD; Eperisone HCl 50mg PO TID x 5 days for muscle spasm.',
                    'follow_up_schedule' => '2 Weeks',
                    'follow_up_days' => 14,
                    'follow_up_date' => date('Y-m-d', strtotime('+14 days')),
                ]
            ],
            [
                'id' => 9035,
                'name' => 'Osteoarthritis, Unspecified Site (Joint Wear)',
                'disease_name' => 'Osteoarthritis, Unspecified Site (Joint Wear)',
                'icd_code' => 'M19.90',
                'icd10_code' => 'M19.90',
                'category' => 'Musculoskeletal',
                'description' => 'Degenerative articular cartilage breakdown presenting with joint pain worsened by weight-bearing, crepitus, and stiffness.',
                'default_severity' => 'Mild',
                'display_label' => 'Osteoarthritis, Unspecified Site (Joint Wear) (ICD-10: M19.90)',
                'treatment_plan' => [
                    'goal' => 'Alleviate joint pain, maintain range of motion, strengthen periarticular muscles, and minimize functional limitation.',
                    'lifestyle' => 'Low-impact aerobic exercise (swimming, cycling, walking), weight loss if overweight, use of knee support or cane.',
                    'medication_plan' => 'Paracetamol 500mg PO Q6-8H first-line; Topical Diclofenac gel 1% TID-QID; Celecoxib 200mg PO OD for persistent flares.',
                    'follow_up_schedule' => '1 Month',
                    'follow_up_days' => 30,
                    'follow_up_date' => date('Y-m-d', strtotime('+30 days')),
                ]
            ],

            // Trauma & Injuries
            [
                'id' => 9036,
                'name' => 'Open Wound / Laceration, Unspecified',
                'disease_name' => 'Open Wound / Laceration, Unspecified',
                'icd_code' => 'T14.1',
                'icd10_code' => 'T14.1',
                'category' => 'Trauma & Injury',
                'description' => 'Mechanical breach of cutaneous integrity resulting from sharp or blunt trauma, requiring wound debridement or closure.',
                'default_severity' => 'Moderate',
                'display_label' => 'Open Wound / Laceration, Unspecified (ICD-10: T14.1)',
                'treatment_plan' => [
                    'goal' => 'Hemostasis, surgical wound toilet/closure, prevention of tetanus and bacterial infection, primary intention healing.',
                    'lifestyle' => 'Keep wound clean and dry for 48 hours; do not soak in water; watch for erythema, warmth, purulent drainage, or spreading redness.',
                    'medication_plan' => 'Tetanus Toxoid 0.5 mL IM + Tetanus Immunoglobulin (TIG) 250 IU if high risk; Cephalexin 500mg PO QID x 5 days; Mefenamic Acid 500mg PO TID PRN for pain.',
                    'follow_up_schedule' => 'Suture Removal (7-10 Days)',
                    'follow_up_days' => 7,
                    'follow_up_date' => date('Y-m-d', strtotime('+7 days')),
                ]
            ],
            [
                'id' => 9037,
                'name' => 'Superficial Injury / Abrasion / Contusion',
                'disease_name' => 'Superficial Injury / Abrasion / Contusion',
                'icd_code' => 'T14.0',
                'icd10_code' => 'T14.0',
                'category' => 'Trauma & Injury',
                'description' => 'Superficial skin loss or subcutaneous hematoma without deep fascial or neurovascular compromise.',
                'default_severity' => 'Mild',
                'display_label' => 'Superficial Injury / Abrasion / Contusion (ICD-10: T14.0)',
                'treatment_plan' => [
                    'goal' => 'Antiseptic wound cleansing, secondary intention re-epithelialization, and pain management.',
                    'lifestyle' => 'Gentle cleansing with mild soap and water; apply protective antiseptic dressing; cold compress on contusions for 24h.',
                    'medication_plan' => 'Mupirocin 2% topical ointment BID x 5 days; Paracetamol 500mg PO Q6H PRN for discomfort.',
                    'follow_up_schedule' => '5 Days',
                    'follow_up_days' => 5,
                    'follow_up_date' => date('Y-m-d', strtotime('+5 days')),
                ]
            ],
            [
                'id' => 9038,
                'name' => 'Closed Fracture of Extremity / Bone Fracture',
                'disease_name' => 'Closed Fracture of Extremity / Bone Fracture',
                'icd_code' => 'T14.2',
                'icd10_code' => 'T14.2',
                'category' => 'Trauma & Orthopedic',
                'description' => 'Disruption of bone cortex without skin laceration; deformity, focal bone tenderness, localized swelling, and loss of function.',
                'default_severity' => 'Severe',
                'display_label' => 'Closed Fracture of Extremity / Bone Fracture (ICD-10: T14.2)',
                'treatment_plan' => [
                    'goal' => 'Anatomic alignment, rigid immobilization via splint or cast, radiographic evaluation, and pain control.',
                    'lifestyle' => 'Immobilize limb; elevate above heart level to decrease swelling; keep cast dry; report numbness, pallor, or blue digits.',
                    'medication_plan' => 'Tramadol 50mg + Paracetamol 325mg PO Q8H PRN; Calcium carbonate 500mg + Vitamin D3 OD; orthopedic referral.',
                    'follow_up_schedule' => '1 Week Orthopedic Clinic',
                    'follow_up_days' => 7,
                    'follow_up_date' => date('Y-m-d', strtotime('+7 days')),
                ]
            ],

            // Dermatology & Allergy
            [
                'id' => 9039,
                'name' => 'Allergic Urticaria (Hives)',
                'disease_name' => 'Allergic Urticaria (Hives)',
                'icd_code' => 'L50.0',
                'icd10_code' => 'L50.0',
                'category' => 'Dermatology & Allergy',
                'description' => 'Mast cell histamine release causing transient intensely pruritic erythematous edematous wheals.',
                'default_severity' => 'Mild',
                'display_label' => 'Allergic Urticaria (Hives) (ICD-10: L50.0)',
                'treatment_plan' => [
                    'goal' => 'Suppression of cutaneous histamine release, immediate relief of pruritus, and identification of allergen.',
                    'lifestyle' => 'Avoid identified food/drug triggers, cool compresses on itchy skin, wear loose cotton clothing, avoid hot showers.',
                    'medication_plan' => 'Cetirizine 10mg or Bilastine 20mg PO OD (double dose if refractory); Hydrocortisone 1% cream topically; Hydroxyzine 25mg HS if severe night itching.',
                    'follow_up_schedule' => '1 Week',
                    'follow_up_days' => 7,
                    'follow_up_date' => date('Y-m-d', strtotime('+7 days')),
                ]
            ],
            [
                'id' => 9040,
                'name' => 'Cellulitis, Unspecified',
                'disease_name' => 'Cellulitis, Unspecified',
                'icd_code' => 'L03.90',
                'icd10_code' => 'L03.90',
                'category' => 'Dermatology / Infectious',
                'description' => 'Acute non-necrotizing bacterial dermis and subcutaneous infection; spreading erythema, warmth, edema, and tenderness.',
                'default_severity' => 'Moderate',
                'display_label' => 'Cellulitis, Unspecified (ICD-10: L03.90)',
                'treatment_plan' => [
                    'goal' => 'Arrest bacterial dissemination, resolve soft tissue erythema and swelling, and prevent abscess or necrotizing fasciitis.',
                    'lifestyle' => 'Elevate affected limb above heart level; mark erythematous borders with surgical pen to track progression; warm moist soaks.',
                    'medication_plan' => 'Cloxacillin 500mg PO Q6H on empty stomach x 10 days OR Cephalexin 500mg PO QID x 10 days; Paracetamol 500mg PO Q6H PRN.',
                    'follow_up_schedule' => '3 Days for Border Regression',
                    'follow_up_days' => 3,
                    'follow_up_date' => date('Y-m-d', strtotime('+3 days')),
                ]
            ],
            [
                'id' => 9041,
                'name' => 'Scabies Infestation',
                'disease_name' => 'Scabies Infestation',
                'icd_code' => 'B86',
                'icd10_code' => 'B86',
                'category' => 'Parasitic Infestation',
                'description' => 'Sarcoptes scabiei mite burrowing into epidermis; nocturnal pruritus, serpiginous burrows on finger webs, wrists, and genitalia.',
                'default_severity' => 'Mild',
                'display_label' => 'Scabies Infestation (ICD-10: B86)',
                'treatment_plan' => [
                    'goal' => 'Eradication of scabies mites and ova from patient and all household contacts; prevention of re-infestation.',
                    'lifestyle' => 'Wash all bed linens, towels, and clothing worn in past 4 days in hot water; treat all co-habitants simultaneously.',
                    'medication_plan' => 'Permethrin 5% lotion applied from neck down to soles of feet, leave on for 8-14 hours before washing, repeat in 7 days; Cetirizine 10mg PO OD for itching.',
                    'follow_up_schedule' => '2 Weeks',
                    'follow_up_days' => 14,
                    'follow_up_date' => date('Y-m-d', strtotime('+14 days')),
                ]
            ],

            // Neurological & Headaches
            [
                'id' => 9042,
                'name' => 'Tension-Type Headache, Unspecified',
                'disease_name' => 'Tension-Type Headache, Unspecified',
                'icd_code' => 'G44.2',
                'icd10_code' => 'G44.2',
                'category' => 'Neurological',
                'description' => 'Bilateral dull band-like pressing head pain without nausea or photophobia, exacerbated by stress, fatigue, or muscle tension.',
                'default_severity' => 'Mild',
                'display_label' => 'Tension-Type Headache, Unspecified (ICD-10: G44.2)',
                'treatment_plan' => [
                    'goal' => 'Relief of acute headache pain, reduction of frequency, and prevention of medication-overuse headache.',
                    'lifestyle' => 'Stress management techniques, neck and shoulder stretches, maintain regular sleep schedule, stay well hydrated.',
                    'medication_plan' => 'Paracetamol 500-1000mg PO or Ibuprofen 400mg PO at onset; limit simple analgesics to <15 days/month.',
                    'follow_up_schedule' => '2 Weeks',
                    'follow_up_days' => 14,
                    'follow_up_date' => date('Y-m-d', strtotime('+14 days')),
                ]
            ],
            [
                'id' => 9043,
                'name' => 'Migraine without Aura (Common Migraine)',
                'disease_name' => 'Migraine without Aura (Common Migraine)',
                'icd_code' => 'G43.0',
                'icd10_code' => 'G43.0',
                'category' => 'Neurological',
                'description' => 'Recurrent unilateral throbbing headache (4-72 hours) aggravated by physical activity, accompanied by nausea, photophobia, or phonophobia.',
                'default_severity' => 'Moderate',
                'display_label' => 'Migraine without Aura (Common Migraine) (ICD-10: G43.0)',
                'treatment_plan' => [
                    'goal' => 'Rapid abortive pain relief, restoration of functioning, and avoidance of migraine triggers.',
                    'lifestyle' => 'Rest in a dark quiet room during attacks; maintain headache diary; avoid triggers (aged cheese, MSG, skipped meals, sleep deficit).',
                    'medication_plan' => 'Sumatriptan 50mg PO at onset OR Naproxen 500mg PO; Metoclopramide 10mg PO for nausea; Propranolol 40mg PO OD if prophylactic therapy indicated.',
                    'follow_up_schedule' => '1 Month',
                    'follow_up_days' => 30,
                    'follow_up_date' => date('Y-m-d', strtotime('+30 days')),
                ]
            ],
            [
                'id' => 9044,
                'name' => 'Benign Paroxysmal Positional Vertigo (BPPV)',
                'disease_name' => 'Benign Paroxysmal Positional Vertigo (BPPV)',
                'icd_code' => 'H81.10',
                'icd10_code' => 'H81.10',
                'category' => 'ENT / Neurological',
                'description' => 'Brief episodes of rotational vertigo provoked by head positional changes due to canalithiasis in posterior semicircular canal.',
                'default_severity' => 'Mild',
                'display_label' => 'Benign Paroxysmal Positional Vertigo (BPPV) (ICD-10: H81.10)',
                'treatment_plan' => [
                    'goal' => 'Canalith repositioning, resolution of positional vertigo, and fall prevention in elderly patients.',
                    'lifestyle' => 'Avoid sudden head movements, rise slowly from bed, perform Brandt-Daroff home exercises if repositioning maneuver incomplete.',
                    'medication_plan' => 'Epley repositioning maneuver (definitive); Betahistine 16mg to 24mg PO BID x 14 days; Meclizine 25mg PO PRN for acute vestibular suppression.',
                    'follow_up_schedule' => '2 Weeks',
                    'follow_up_days' => 14,
                    'follow_up_date' => date('Y-m-d', strtotime('+14 days')),
                ]
            ],

            // Maternal & Blood
            [
                'id' => 9045,
                'name' => 'Iron Deficiency Anemia, Unspecified',
                'disease_name' => 'Iron Deficiency Anemia, Unspecified',
                'icd_code' => 'D50.9',
                'icd10_code' => 'D50.9',
                'category' => 'Hematology',
                'description' => 'Microcytic hypochromic anemia from depleted iron stores; pallor, fatigue, exercise intolerance, brittle nails, or pica.',
                'default_severity' => 'Mild',
                'display_label' => 'Iron Deficiency Anemia, Unspecified (ICD-10: D50.9)',
                'treatment_plan' => [
                    'goal' => 'Replenish iron stores (ferritin), normalize hemoglobin/hematocrit, and identify cause of occult blood loss.',
                    'lifestyle' => 'High iron diet (dark leafy greens, lean red meat, legumes); take iron with vitamin C (citrus); avoid tea/coffee near dose.',
                    'medication_plan' => 'Ferrous Sulfate 325mg (65mg elemental iron) PO OD to BID on empty stomach x 3-6 months; Ascorbic Acid 500mg PO with iron.',
                    'follow_up_schedule' => '1 Month (CBC Check)',
                    'follow_up_days' => 30,
                    'follow_up_date' => date('Y-m-d', strtotime('+30 days')),
                ]
            ],
            [
                'id' => 9046,
                'name' => 'Single Spontaneous Normal Delivery',
                'disease_name' => 'Single Spontaneous Normal Delivery',
                'icd_code' => 'O80',
                'icd10_code' => 'O80',
                'category' => 'Obstetrics & Delivery',
                'description' => 'Uncomplicated vaginal delivery of single liveborn infant requiring standard postpartum care and lochia monitoring.',
                'default_severity' => 'Moderate',
                'display_label' => 'Single Spontaneous Normal Delivery (ICD-10: O80)',
                'treatment_plan' => [
                    'goal' => 'Safe postpartum recovery, uterine involution, exclusive breastfeeding initiation, and perineal healing.',
                    'lifestyle' => 'Exclusive breastfeeding on demand, perineal hygiene with warm water, pelvic floor (Kegel) exercises, early ambulation.',
                    'medication_plan' => 'Methylergonovine 0.125mg PO TID x 2 days if uterine atony; Mefenamic Acid 500mg PO TID PRN for perineal pain; Ferrous Sulfate + Folic Acid OD.',
                    'follow_up_schedule' => '1 Week Postpartum Check',
                    'follow_up_days' => 7,
                    'follow_up_date' => date('Y-m-d', strtotime('+7 days')),
                ]
            ],
            [
                'id' => 9047,
                'name' => 'Threatened Abortion / Early Pregnancy Bleeding',
                'disease_name' => 'Threatened Abortion / Early Pregnancy Bleeding',
                'icd_code' => 'O20.0',
                'icd10_code' => 'O20.0',
                'category' => 'Obstetrics & Pregnancy',
                'description' => 'Vaginal bleeding before 20 weeks gestation with closed cervical os and documented fetal cardiac activity.',
                'default_severity' => 'Moderate',
                'display_label' => 'Threatened Abortion / Early Pregnancy Bleeding (ICD-10: O20.0)',
                'treatment_plan' => [
                    'goal' => 'Preserve pregnancy, cessation of vaginal bleeding, pelvic rest, and serial ultrasound monitoring.',
                    'lifestyle' => 'Strict bed rest, complete pelvic rest (avoid coitus, douching), avoid strenuous lifting or prolonged standing.',
                    'medication_plan' => 'Isoxsuprine 10mg PO TID or Dydrogesterone 10mg PO BID-TID x 7-14 days; Folic Acid 5mg PO OD; emergency return if bleeding increases.',
                    'follow_up_schedule' => '3 Days for Repeat Pelvic US',
                    'follow_up_days' => 3,
                    'follow_up_date' => date('Y-m-d', strtotime('+3 days')),
                ]
            ]
        ];
    }
}


