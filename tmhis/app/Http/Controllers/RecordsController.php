<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\Patient;
use App\Models\RecordReleaseRequest;
use App\Models\ConsultationNote;
use App\Models\Diagnosis;
use App\Models\TreatmentPlan;
use App\Models\Prescription;
use App\Models\LaboratoryResult;
use App\Models\PatientVital;
use App\Models\SystemAuditLog;

class RecordsController extends Controller
{
    public function dashboard(Request $request)
    {
        $currentOfficer = [
            'name' => auth()->user() ? auth()->user()->FullName : 'Mark Anthony Valenzuela, RMT',
            'title' => 'Registered Medical Records Officer',
            'role' => 'Medical Records Officer (User Role 3)',
            'roleId' => 3,
            'department' => 'Health Information & Records Management Department (HIRM)',
            'hospital' => 'Tupi Municipal Hospital Information Management System',
            'employeeId' => 'MRO-2024-8842',
            'licenseNo' => 'MRO-PH-004928'
        ];

        // Fetch live patients from Supabase
        $patientRows = Patient::orderBy('PatientID', 'asc')->get();
        $livePatients = [];
        foreach ($patientRows as $r) {
            $code = $r->PatientCode ?: ('PAT-' . date('Y') . '-' . str_pad((string)$r->PatientID, 4, '0', STR_PAD_LEFT));
            $livePatients[] = [
                'id'                 => $code,
                'patient_id'         => (int)$r->PatientID,
                'firstName'          => $r->FirstName,
                'middleName'         => $r->MiddleName ?? '',
                'lastName'           => $r->LastName,
                'suffix'             => $r->Suffix ?? '',
                'dob'                => $r->DateOfBirth ?: '1998-05-12',
                'age'                => (int)$r->Age,
                'gender'             => $r->Gender,
                'civilStatus'        => $r->CivilStatus ?? 'Single',
                'bloodType'          => $r->BloodType ?? 'O+',
                'contact'            => $r->ContactNumber ?: '0917-882-9102',
                'email'              => $r->Email ?: strtolower($r->FirstName . '.' . $r->LastName . '@email.ph'),
                'address'            => $r->Address ?: 'Tupi, South Cotabato',
                'emergencyContact'   => [
                    'name'         => 'Family Contact',
                    'relationship' => 'Guardian',
                    'contact'      => $r->ContactNumber ?: '0918-773-4411',
                    'address'      => $r->Address ?: 'Tupi, South Cotabato'
                ],
                'registrationDate'   => substr((string)($r->CreatedAt ?? date('Y-m-d')), 0, 10),
                'registrationType'   => (($r->PatientCategory ?? '') === 'Inpatient') ? 'Inpatient Admission' : 'Outpatient Consultation',
                'registeredBy'       => 'Admitting Staff',
                'recordStatus'       => $r->Status ?: 'Active',
                'verificationStatus' => ($r->Status === 'Archived') ? 'Archived' : 'Verified',
                'verifiedBy'         => $currentOfficer['name'],
                'verifiedDate'       => substr((string)($r->CreatedAt ?? date('Y-m-d')), 0, 10),
                'lastUpdated'        => substr((string)($r->UpdatedAt ?? $r->CreatedAt ?? date('Y-m-d H:i')), 0, 16),
                'lastUpdatedBy'      => $currentOfficer['name']
            ];
        }

        // Fetch live release requests
        $requests = RecordReleaseRequest::with('patient')->orderBy('RequestID', 'desc')->get()->map(function ($req) {
            return [
                'id' => 'REQ-' . str_pad((string)$req->RequestID, 4, '0', STR_PAD_LEFT),
                'request_id' => $req->RequestID,
                'patientId' => $req->patient ? ($req->patient->PatientCode ?: 'PAT-' . date('Y') . '-' . str_pad((string)$req->patient->PatientID, 4, '0', STR_PAD_LEFT)) : 'PAT-2026-0001',
                'patientName' => $req->patient ? ($req->patient->FirstName . ' ' . $req->patient->LastName) : 'Patient Record',
                'requestor' => $req->RequestedBy,
                'relationship' => $req->Relationship,
                'purpose' => $req->Purpose,
                'requestedDate' => (string)$req->RequestDate,
                'priority' => 'Normal',
                'status' => $req->Status,
                'processedBy' => $req->ProcessedBy ? 'Officer ID ' . $req->ProcessedBy : null,
                'processedDate' => $req->ProcessedAt ? substr((string)$req->ProcessedAt, 0, 10) : null,
                'remarks' => $req->Remarks
            ];
        })->toArray();

        // Fetch live consultations with doctor & specialty details
        $consultationRows = DB::table('consultation_notes as cn')
            ->join('patients as p', 'cn.PatientID', '=', 'p.PatientID')
            ->leftJoin('doctors as d', 'cn.DoctorID', '=', 'd.DoctorID')
            ->leftJoin('specialties as s', 'd.SpecialtyID', '=', 's.SpecialtyID')
            ->select(
                'cn.*',
                'p.PatientCode',
                'p.FirstName as PatientFirstName',
                'p.LastName as PatientLastName',
                'd.FirstName as DoctorFirstName',
                'd.LastName as DoctorLastName',
                'd.Title as DoctorTitle',
                'd.Specialty as DoctorSpecialty',
                's.SpecialtyName'
            )
            ->orderBy('cn.CreatedAt', 'desc')
            ->get();

        $liveConsultations = [];
        foreach ($consultationRows as $c) {
            $pCode = $c->PatientCode ?: ('PAT-' . date('Y') . '-' . str_pad((string)$c->PatientID, 4, '0', STR_PAD_LEFT));
            $docName = 'Dr. ' . trim(($c->DoctorFirstName ?? 'Attending') . ' ' . ($c->DoctorLastName ?? 'Physician'));
            if (!empty($c->DoctorTitle)) $docName .= ', ' . $c->DoctorTitle;

            $liveConsultations[] = [
                'id'             => 'CON-2026-' . str_pad((string)$c->NoteID, 3, '0', STR_PAD_LEFT),
                'note_id'        => (int)$c->NoteID,
                'patientId'      => $pCode,
                'patient_id'     => (int)$c->PatientID,
                'patientName'    => trim($c->PatientFirstName . ' ' . $c->PatientLastName),
                'date'           => substr((string)$c->CreatedAt, 0, 10),
                'time'           => date('h:i A', strtotime($c->CreatedAt)),
                'doctor'         => $docName,
                'department'     => $c->DoctorSpecialty ?: ($c->SpecialtyName ?: 'Clinical Services'),
                'chiefComplaint' => $c->Subjective ?: 'Routine Consultation',
                'vitalSigns'     => is_string($c->VitalSigns) ? $c->VitalSigns : json_encode($c->VitalSigns),
                'diagnosis'      => $c->Assessment ?: 'Clinical Impression Evaluated',
                'treatment'      => $c->Plan ?: 'Treatment plan formulated',
                'status'         => 'Completed',
                'notes'          => $c->ClinicalNotes ?: ($c->Subjective . ' ' . $c->Assessment)
            ];
        }

        // Fetch live laboratory results
        $labRows = DB::table('laboratory_results as lr')
            ->join('patients as p', 'lr.PatientID', '=', 'p.PatientID')
            ->leftJoin('laboratory_requests as req', 'lr.RequestID', '=', 'req.RequestID')
            ->leftJoin('doctors as d', 'lr.DoctorID', '=', 'd.DoctorID')
            ->select(
                'lr.*',
                'p.PatientCode',
                'p.FirstName as PatientFirstName',
                'p.LastName as PatientLastName',
                'req.TestType as RequestedTestType',
                'req.Status as RequestStatus',
                'd.FirstName as DoctorFirstName',
                'd.LastName as DoctorLastName'
            )
            ->orderBy('lr.CreatedAt', 'desc')
            ->get();

        $liveLabs = [];
        foreach ($labRows as $lr) {
            $pCode = $lr->PatientCode ?: ('PAT-' . date('Y') . '-' . str_pad((string)$lr->PatientID, 4, '0', STR_PAD_LEFT));
            $docName = $lr->DoctorFirstName ? ('Dr. ' . $lr->DoctorFirstName . ' ' . $lr->DoctorLastName) : 'Attending Physician';
            $testName = $lr->TestName ?: ($lr->RequestedTestType ?: 'Diagnostic Test');
            $resultsData = [
                [
                    'parameter' => $testName,
                    'value'     => ($lr->ResultValue ?? 'Completed') . ($lr->Units ? ' ' . $lr->Units : ''),
                    'reference' => $lr->NormalRange ?: 'Normal',
                    'status'    => $lr->Interpretation ?: 'Normal'
                ]
            ];

            $liveLabs[] = [
                'id'               => 'LAB-2026-' . str_pad((string)$lr->ResultID, 4, '0', STR_PAD_LEFT),
                'result_id'        => (int)$lr->ResultID,
                'patientId'        => $pCode,
                'patient_id'       => (int)$lr->PatientID,
                'patientName'      => trim($lr->PatientFirstName . ' ' . $lr->PatientLastName),
                'date'             => (string)($lr->ResultDate ?? substr((string)$lr->CreatedAt, 0, 10)),
                'testName'         => $testName,
                'requestingDoctor' => $docName,
                'department'       => 'Clinical Pathology / Laboratory',
                'resultStatus'     => $lr->RequestStatus ?: ($lr->Interpretation ?: 'Completed'),
                'reportStatus'     => 'Available',
                'results'          => $resultsData,
                'pathologist'      => 'Dr. Corazon Mendoza, FPSP',
                'medTech'          => 'Registered Medical Technologist'
            ];
        }

        // Fetch live treatment plans
        $treatmentRows = DB::table('treatment_plans as tp')
            ->join('patients as p', 'tp.PatientID', '=', 'p.PatientID')
            ->leftJoin('doctors as d', 'tp.DoctorID', '=', 'd.DoctorID')
            ->select(
                'tp.*',
                'p.PatientCode',
                'p.FirstName as PatientFirstName',
                'p.LastName as PatientLastName',
                'd.FirstName as DoctorFirstName',
                'd.LastName as DoctorLastName',
                'd.Specialty as DoctorSpecialty'
            )
            ->orderBy('tp.CreatedAt', 'desc')
            ->get();

        $liveTreatments = [];
        foreach ($treatmentRows as $tp) {
            $pCode = $tp->PatientCode ?: ('PAT-' . date('Y') . '-' . str_pad((string)$tp->PatientID, 4, '0', STR_PAD_LEFT));
            $docName = $tp->DoctorFirstName ? ('Dr. ' . $tp->DoctorFirstName . ' ' . $tp->DoctorLastName) : 'Attending Physician';
            $liveTreatments[] = [
                'id'                => 'TRT-2026-' . str_pad((string)$tp->PlanID, 3, '0', STR_PAD_LEFT),
                'plan_id'           => (int)$tp->PlanID,
                'patientId'         => $pCode,
                'patient_id'        => (int)$tp->PatientID,
                'treatment'         => $tp->Goal ?: 'Clinical Treatment Protocol',
                'date'              => substr((string)$tp->CreatedAt, 0, 10),
                'startDate'         => substr((string)$tp->CreatedAt, 0, 10),
                'doctor'            => $docName,
                'prescribingDoctor' => $docName,
                'department'        => $tp->DoctorSpecialty ?: 'Outpatient Department',
                'notes'             => $tp->Notes ?: ($tp->LifestyleRecommendations . ' ' . $tp->MedicationPlan),
                'status'            => $tp->Status ?: 'Active'
            ];
        }

        $serverData = [
            'currentOfficer'        => $currentOfficer,
            'patients'              => $livePatients,
            'medicalRecordRequests' => $requests,
            'consultations'         => $liveConsultations,
            'laboratoryHistory'     => $liveLabs,
            'treatmentHistory'      => $liveTreatments,
        ];

        return view('medical_officer.dashboard', compact('currentOfficer', 'serverData'));
    }

    public function getPatients(Request $request): JsonResponse
    {
        $q = trim((string)$request->query('q', ''));
        $status = $request->query('status', 'all');

        $query = Patient::query();
        if ($q !== '') {
            $query->where(function ($b) use ($q) {
                $b->where('FirstName', 'like', "%{$q}%")
                  ->orWhere('LastName', 'like', "%{$q}%")
                  ->orWhere('PatientCode', 'like', "%{$q}%")
                  ->orWhere('ContactNumber', 'like', "%{$q}%");
            });
        }
        if ($status !== 'all') {
            $query->where('Status', $status);
        }

        $patients = $query->orderBy('PatientID', 'asc')->get();
        return response()->json([
            'success' => true,
            'data' => $patients
        ]);
    }

    private function logAudit(Request $request, string $action, string $details, ?string $recordId = null): void
    {
        try {
            $user = auth()->user();
            SystemAuditLog::create([
                'UserID'    => $user ? $user->UserID : 1,
                'UserName'  => $user ? $user->FullName : 'Mark Anthony Valenzuela',
                'UserRole'  => $user ? $user->Role : 'Records',
                'Action'    => $action,
                'Module'    => 'Medical Records',
                'RecordID'  => $recordId,
                'Details'   => $details,
                'IPAddress' => $request->ip(),
                'UserAgent' => substr((string)$request->userAgent(), 0, 250),
                'CreatedAt' => now(),
            ]);
        } catch (\Throwable $e) {
            // Non-blocking
        }
    }

    public function createPatient(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'FirstName'      => 'required|string|max:100',
            'LastName'       => 'required|string|max:100',
            'MiddleName'     => 'nullable|string|max:100',
            'DateOfBirth'    => 'required|date',
            'Gender'         => 'required|string|in:Male,Female,Other',
            'ContactNumber'  => 'nullable|string|max:50',
            'Email'          => 'nullable|email|max:150',
            'Address'        => 'nullable|string|max:255',
            'CivilStatus'    => 'nullable|string|max:50',
            'BloodType'      => 'nullable|string|max:10',
            'PatientCategory'=> 'nullable|string|max:50',
        ]);

        $dob = new \DateTime($validated['DateOfBirth']);
        $now = new \DateTime();
        $age = $now->diff($dob)->y;

        $count = Patient::count() + 1;
        $patientCode = 'P-' . date('Y') . '-' . str_pad((string)$count, 3, '0', STR_PAD_LEFT);

        $patient = Patient::create([
            'PatientCode'     => $patientCode,
            'FirstName'       => $validated['FirstName'],
            'MiddleName'      => $validated['MiddleName'] ?? null,
            'LastName'        => $validated['LastName'],
            'DateOfBirth'     => $validated['DateOfBirth'],
            'Age'             => $age,
            'Gender'          => $validated['Gender'],
            'ContactNumber'   => $validated['ContactNumber'] ?? '0917-000-0000',
            'Email'           => !empty($validated['Email']) ? $validated['Email'] : strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $validated['FirstName']) . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $validated['LastName']) . '@patient.tmhis.local'),
            'Address'         => $validated['Address'] ?? 'Tupi, South Cotabato',
            'CivilStatus'     => $validated['CivilStatus'] ?? 'Single',
            'BloodType'       => $validated['BloodType'] ?? 'O+',
            'PatientCategory' => $validated['PatientCategory'] ?? 'Outpatient',
            'Status'          => 'Active',
            'CreatedAt'       => now(),
        ]);

        $this->logAudit($request, 'CREATE_PATIENT', "Created patient record {$patientCode} - {$patient->FirstName} {$patient->LastName}", (string)$patient->PatientID);

        return response()->json([
            'success' => true,
            'message' => 'Patient record created successfully.',
            'patient' => $patient
        ], 201);
    }

    public function updatePatient(Request $request, $id): JsonResponse
    {
        $patient = Patient::where('PatientID', $id)
            ->orWhere('PatientCode', $id)
            ->firstOrFail();

        $patient->update($request->only([
            'FirstName', 'LastName', 'MiddleName', 'DateOfBirth',
            'Gender', 'ContactNumber', 'Email', 'Address',
            'CivilStatus', 'BloodType', 'PatientCategory', 'Status'
        ]));

        $this->logAudit($request, 'UPDATE_PATIENT', "Updated patient record #{$patient->PatientID} ({$patient->PatientCode})", (string)$patient->PatientID);

        return response()->json([
            'success' => true,
            'message' => 'Patient record updated successfully.',
            'patient' => $patient
        ]);
    }

    public function archivePatient(Request $request, $id): JsonResponse
    {
        $patient = Patient::where('PatientID', $id)
            ->orWhere('PatientCode', $id)
            ->firstOrFail();

        $patient->update(['Status' => 'Archived']);

        $this->logAudit($request, 'ARCHIVE_PATIENT', "Archived patient record {$patient->PatientCode}", (string)$patient->PatientID);

        return response()->json([
            'success' => true,
            'message' => "Patient record {$patient->PatientCode} archived successfully."
        ]);
    }

    public function verifyPatient(Request $request, $id): JsonResponse
    {
        $patient = Patient::where('PatientID', $id)
            ->orWhere('PatientCode', $id)
            ->firstOrFail();

        $this->logAudit($request, 'VERIFY_RECORD_ACCURACY', "Verified accuracy of patient record {$patient->PatientCode} ({$patient->FirstName} {$patient->LastName})", (string)$patient->PatientID);

        return response()->json([
            'success' => true,
            'message' => "Patient record {$patient->PatientCode} verified for accuracy.",
            'verified_at' => now()->toDateTimeString()
        ]);
    }

    public function getRequests(): JsonResponse
    {
        $requests = RecordReleaseRequest::with('patient')->orderBy('RequestID', 'desc')->get();
        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    public function processRequest(Request $request, $id): JsonResponse
    {
        $status = $request->input('status', 'Approved');
        $remarks = $request->input('remarks', 'Processed by Medical Records Officer');

        $req = RecordReleaseRequest::where('RequestID', $id)->firstOrFail();
        $req->update([
            'Status' => $status,
            'ProcessedBy' => auth()->id() ?? 1,
            'ProcessedAt' => now(),
            'Remarks' => $remarks
        ]);

        $this->logAudit($request, 'PROCESS_RECORD_REQUEST', "Record request #{$id} status changed to {$status}", (string)$id);

        return response()->json([
            'success' => true,
            'message' => "Request #{$id} processed successfully as {$status}.",
            'data' => $req
        ]);
    }

    public function patientHistory($id): JsonResponse
    {
        $patient = Patient::where('PatientID', $id)
            ->orWhere('PatientCode', $id)
            ->first();

        if (!$patient && is_string($id) && preg_match('/PAT-\d{4}-(\d+)/', $id, $matches)) {
            $numId = (int)$matches[1];
            $patient = Patient::where('PatientID', $numId)->first();
        }

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient not found'
            ], 404);
        }

        $patientId = $patient->PatientID;
        $pCode = $patient->PatientCode ?: ('PAT-' . date('Y') . '-' . str_pad((string)$patient->PatientID, 4, '0', STR_PAD_LEFT));

        $consultationRows = DB::table('consultation_notes as cn')
            ->leftJoin('doctors as d', 'cn.DoctorID', '=', 'd.DoctorID')
            ->leftJoin('specialties as s', 'd.SpecialtyID', '=', 's.SpecialtyID')
            ->where('cn.PatientID', $patientId)
            ->select(
                'cn.*',
                'd.FirstName as DoctorFirstName',
                'd.LastName as DoctorLastName',
                'd.Title as DoctorTitle',
                'd.Specialty as DoctorSpecialty',
                's.SpecialtyName'
            )
            ->orderBy('cn.CreatedAt', 'desc')
            ->get();

        $consultations = [];
        foreach ($consultationRows as $c) {
            $docName = 'Dr. ' . trim(($c->DoctorFirstName ?? 'Attending') . ' ' . ($c->DoctorLastName ?? 'Physician'));
            if (!empty($c->DoctorTitle)) $docName .= ', ' . $c->DoctorTitle;

            $consultations[] = [
                'id'             => 'CON-2026-' . str_pad((string)$c->NoteID, 3, '0', STR_PAD_LEFT),
                'note_id'        => (int)$c->NoteID,
                'patientId'      => $pCode,
                'patient_id'     => (int)$c->PatientID,
                'patientName'    => trim($patient->FirstName . ' ' . $patient->LastName),
                'date'           => substr((string)$c->CreatedAt, 0, 10),
                'time'           => date('h:i A', strtotime($c->CreatedAt)),
                'doctor'         => $docName,
                'department'     => $c->DoctorSpecialty ?: ($c->SpecialtyName ?: 'Clinical Services'),
                'chiefComplaint' => $c->Subjective ?: 'Routine Consultation',
                'vitalSigns'     => is_string($c->VitalSigns) ? $c->VitalSigns : json_encode($c->VitalSigns),
                'diagnosis'      => $c->Assessment ?: 'Clinical Impression Evaluated',
                'treatment'      => $c->Plan ?: 'Treatment plan formulated',
                'status'         => 'Completed',
                'notes'          => $c->ClinicalNotes ?: ($c->Subjective . ' ' . $c->Assessment)
            ];
        }

        $labRows = DB::table('laboratory_results as lr')
            ->leftJoin('laboratory_requests as req', 'lr.RequestID', '=', 'req.RequestID')
            ->leftJoin('doctors as d', 'lr.DoctorID', '=', 'd.DoctorID')
            ->where('lr.PatientID', $patientId)
            ->select(
                'lr.*',
                'req.TestType as RequestedTestType',
                'req.Status as RequestStatus',
                'd.FirstName as DoctorFirstName',
                'd.LastName as DoctorLastName'
            )
            ->orderBy('lr.CreatedAt', 'desc')
            ->get();

        $labs = [];
        foreach ($labRows as $lr) {
            $docName = $lr->DoctorFirstName ? ('Dr. ' . $lr->DoctorFirstName . ' ' . $lr->DoctorLastName) : 'Attending Physician';
            $testName = $lr->TestName ?: ($lr->RequestedTestType ?: 'Diagnostic Test');
            $resultsData = [
                [
                    'parameter' => $testName,
                    'value'     => ($lr->ResultValue ?? 'Completed') . ($lr->Units ? ' ' . $lr->Units : ''),
                    'reference' => $lr->NormalRange ?: 'Normal',
                    'status'    => $lr->Interpretation ?: 'Normal'
                ]
            ];

            $labs[] = [
                'id'               => 'LAB-2026-' . str_pad((string)$lr->ResultID, 4, '0', STR_PAD_LEFT),
                'result_id'        => (int)$lr->ResultID,
                'patientId'        => $pCode,
                'patient_id'       => (int)$lr->PatientID,
                'patientName'      => trim($patient->FirstName . ' ' . $patient->LastName),
                'date'             => (string)($lr->ResultDate ?? substr((string)$lr->CreatedAt, 0, 10)),
                'testName'         => $testName,
                'requestingDoctor' => $docName,
                'department'       => 'Clinical Pathology / Laboratory',
                'resultStatus'     => $lr->RequestStatus ?: ($lr->Interpretation ?: 'Completed'),
                'reportStatus'     => 'Available',
                'results'          => $resultsData,
                'pathologist'      => 'Dr. Corazon Mendoza, FPSP',
                'medTech'          => 'Registered Medical Technologist'
            ];
        }

        $treatmentRows = DB::table('treatment_plans as tp')
            ->leftJoin('doctors as d', 'tp.DoctorID', '=', 'd.DoctorID')
            ->where('tp.PatientID', $patientId)
            ->select(
                'tp.*',
                'd.FirstName as DoctorFirstName',
                'd.LastName as DoctorLastName',
                'd.Specialty as DoctorSpecialty'
            )
            ->orderBy('tp.CreatedAt', 'desc')
            ->get();

        $treatments = [];
        foreach ($treatmentRows as $tp) {
            $docName = $tp->DoctorFirstName ? ('Dr. ' . $tp->DoctorFirstName . ' ' . $tp->DoctorLastName) : 'Attending Physician';
            $treatments[] = [
                'id'                => 'TRT-2026-' . str_pad((string)$tp->PlanID, 3, '0', STR_PAD_LEFT),
                'plan_id'           => (int)$tp->PlanID,
                'patientId'         => $pCode,
                'patient_id'        => (int)$tp->PatientID,
                'treatment'         => $tp->Goal ?: 'Clinical Treatment Protocol',
                'date'              => substr((string)$tp->CreatedAt, 0, 10),
                'startDate'         => substr((string)$tp->CreatedAt, 0, 10),
                'doctor'            => $docName,
                'prescribingDoctor' => $docName,
                'department'        => $tp->DoctorSpecialty ?: 'Outpatient Department',
                'notes'             => $tp->Notes ?: ($tp->LifestyleRecommendations . ' ' . $tp->MedicationPlan),
                'status'            => $tp->Status ?: 'Active'
            ];
        }

        $diagnoses = Diagnosis::where('PatientID', $patientId)->orderBy('CreatedAt', 'desc')->get();
        $prescriptions = Prescription::where('PatientID', $patientId)->orderBy('CreatedAt', 'desc')->get();
        $vitals = PatientVital::where('PatientID', $patientId)->orderBy('CreatedAt', 'desc')->get();

        return response()->json([
            'success' => true,
            'patient' => $patient,
            'consultations' => $consultations,
            'diagnoses' => $diagnoses,
            'treatments' => $treatments,
            'prescriptions' => $prescriptions,
            'laboratories' => $labs,
            'vitals' => $vitals
        ]);
    }

    public function summary($id)
    {
        $patient = Patient::where('PatientID', $id)
            ->orWhere('PatientCode', $id)
            ->first();

        if (!$patient && is_string($id) && preg_match('/PAT-\d{4}-(\d+)/', $id, $matches)) {
            $numId = (int)$matches[1];
            $patient = Patient::where('PatientID', $numId)->first();
        }

        if (!$patient) {
            abort(404, 'Patient not found');
        }

        $patientId = $patient->PatientID;
        $consultations = ConsultationNote::where('PatientID', $patientId)->orderBy('CreatedAt', 'desc')->get();
        $diagnoses = Diagnosis::where('PatientID', $patientId)->orderBy('CreatedAt', 'desc')->get();
        $treatments = TreatmentPlan::where('PatientID', $patientId)->orderBy('CreatedAt', 'desc')->get();
        $prescriptions = Prescription::where('PatientID', $patientId)->orderBy('CreatedAt', 'desc')->get();
        $labs = LaboratoryResult::where('PatientID', $patientId)->orderBy('CreatedAt', 'desc')->get();
        $vitals = PatientVital::where('PatientID', $patientId)->orderBy('CreatedAt', 'desc')->get();

        return view('medical_officer.summary', compact(
            'patient', 'consultations', 'diagnoses', 'treatments', 'prescriptions', 'labs', 'vitals'
        ));
    }
}
