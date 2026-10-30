<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\Referral;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\SystemAuditLog;

class ReferralApiController extends Controller
{
    /**
     * Create Clinical Referral (Internal or External Hospital)
     * Fulfills Requirements 12, 13, 14
     */
    public function createReferral(Request $request): JsonResponse
    {
        $patientId = (int)$request->input('patient_id');
        $referralType = $request->input('referral_type', 'Internal'); // Internal or External
        $reason = trim((string)$request->input('reason', ''));
        $clinicalSummary = trim((string)$request->input('clinical_summary', ''));
        $rawPriority = trim((string)$request->input('priority', 'Routine'));
        $normalizedPriority = ucfirst(strtolower($rawPriority));
        if (in_array($normalizedPriority, ['Routine', 'Urgent', 'Emergency'])) {
            $priority = $normalizedPriority;
        } elseif (in_array(strtoupper($rawPriority), ['STAT', 'CRITICAL', 'HIGH'])) {
            $priority = 'Emergency';
        } else {
            $priority = 'Routine';
        }
        $urgency = $request->input('urgency', $priority);

        if ($patientId <= 0 || empty($reason)) {
            return response()->json([
                'success' => false,
                'message' => 'Patient ID and Reason for Referral are required.'
            ], 422);
        }

        $patient = Patient::find($patientId);
        if (!$patient) {
            return response()->json(['success' => false, 'message' => 'Patient not found.'], 404);
        }

        $user = auth()->user();
        $doctorId = (int)($request->input('doctor_id') ?: session('doctor_id'));
        if (!$doctorId && $user) {
            $doc = Doctor::where('UserID', $user->UserID)->first();
            $doctorId = $doc ? $doc->DoctorID : null;
        }
        if (!$doctorId) {
            return response()->json([
                'success' => false,
                'message' => 'An active attending physician profile is required to issue clinical referrals.'
            ], 422);
        }

        // Prevent accidental duplicate referral submission (same patient, same doctor, within last 60 seconds)
        $duplicateCheck = Referral::where('PatientID', $patientId)
            ->where('ReferringDoctorID', $doctorId)
            ->where('Reason', $reason)
            ->where('CreatedAt', '>=', now()->subSeconds(60))
            ->first();

        if ($duplicateCheck) {
            return response()->json([
                'success'   => true,
                'message'   => 'Referral already recorded.',
                'data'      => $duplicateCheck,
                'print_url' => "/print/referral/{$duplicateCheck->ReferralID}"
            ]);
        }

        // Generate unique referral code
        $refCode = 'REF-' . date('Ymd') . '-' . rand(1000, 9999);

        $receivingHospital = $request->input('receiving_hospital');
        $hospitalAddress = $request->input('hospital_address');
        $requestedService = $request->input('requested_service');
        $referringDept = $request->input('referring_department', 'Outpatient & Emergency Department');

        $targetDoctorId = $request->input('target_doctor_id') ? (int)$request->input('target_doctor_id') : null;
        $targetSpecialtyId = $request->input('target_specialty_id') ? (int)$request->input('target_specialty_id') : 1;

        $rawAmbId = $request->input('ambulance_id');
        $ambulanceId = ($rawAmbId !== null && $rawAmbId !== '') ? (int)$rawAmbId : null;
        $requiresAmbulance = ($ambulanceId && $ambulanceId > 0) ? 1 : 0;
        $ambulanceStatus = $requiresAmbulance ? 'Dispatched' : ($ambulanceId === 0 ? 'Private Transport' : ($ambulanceId === -1 ? 'MDRRMO Rescue' : null));

        DB::beginTransaction();
        try {
            $referral = Referral::create([
                'ReferralCode'        => $refCode,
                'ReferralType'        => ($referralType === 'External' || !empty($receivingHospital)) ? 'External' : 'Internal',
                'PatientID'           => $patientId,
                'ReferringDoctorID'   => $doctorId,
                'ReferringDepartment' => $referringDept,
                'TargetSpecialtyID'   => $targetSpecialtyId,
                'TargetDoctorID'      => $targetDoctorId,
                'ReceivingHospital'   => $receivingHospital,
                'HospitalAddress'     => $hospitalAddress,
                'RequestedService'    => $requestedService,
                'AmbulanceID'         => $ambulanceId > 0 ? $ambulanceId : null,
                'RequiresAmbulance'   => $requiresAmbulance,
                'AmbulanceStatus'     => $ambulanceStatus,
                'Reason'              => $reason,
                'ClinicalSummary'     => $clinicalSummary,
                'Priority'            => $priority,
                'Urgency'             => $urgency,
                'Status'              => 'Pending',
                'TrackingStatus'      => 'Created',
                'CreatedAt'           => now(),
                'UpdatedAt'           => now()
            ]);

            // Dispatch Ambulance if requested
            if ($ambulanceId && $ambulanceId > 0) {
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('ambulances')) {
                        \App\Models\Ambulance::where('AmbulanceID', $ambulanceId)->update([
                            'Status'             => 'On Transfer',
                            'CurrentDestination' => $receivingHospital ?: 'Tertiary Hospital Transfer',
                            'CurrentPatientID'   => $patientId,
                            'EstimatedReturnTime'=> 'Est. 2 Hours',
                            'updated_at'         => now(),
                        ]);
                    }
                } catch (\Throwable $ae) {}
            }

            // Audit Trail
            $patName = trim($patient->FirstName . ' ' . $patient->LastName);
            $typeLabel = $referral->ReferralType === 'External' ? "to {$receivingHospital}" : "to Specialist Doctor #{$targetDoctorId}";
            SystemAuditLog::create([
                'UserID'    => $user ? $user->UserID : 1,
                'UserName'  => $user ? ($user->FullName ?: $user->Username) : 'Attending Physician',
                'UserRole'  => 'Doctor',
                'Action'    => 'Referral Created',
                'Module'    => 'Clinical Care',
                'RecordID'  => (string)$referral->ReferralID,
                'Details'   => "Physician initiated {$referral->ReferralType} referral ({$refCode}) for {$patName} {$typeLabel}.",
                'IPAddress' => $request->ip(),
                'UserAgent' => substr((string)$request->userAgent(), 0, 255),
                'CreatedAt' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Clinical referral {$refCode} successfully registered.",
                'data'    => $referral,
                'print_url' => "/print/referral/{$referral->ReferralID}"
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => safe_error_message($e, 'Failed to create clinical referral.')
            ], 500);
        }
    }

    /**
     * Update Referral Tracking Workflow Status
     * Fulfills Requirement 14: Referral Tracking
     */
    public function updateTracking(Request $request, int $id): JsonResponse
    {
        $referral = Referral::find($id);
        if (!$referral) {
            return response()->json(['success' => false, 'message' => 'Referral record not found.'], 404);
        }

        $trackingStatus = $request->input('tracking_status'); // Created -> Sent -> Received -> Accepted / Rejected -> Transferred / Completed
        $clinicalStatus = $request->input('status'); // Pending, Accepted, Rejected, Completed, Cancelled
        $notes = trim((string)$request->input('response_notes', ''));

        if ($trackingStatus) {
            $referral->TrackingStatus = $trackingStatus;
        }
        if ($clinicalStatus) {
            $referral->Status = $clinicalStatus;
        }
        if ($notes) {
            $referral->ResponseNotes = trim(($referral->ResponseNotes ? $referral->ResponseNotes . ' | ' : '') . $notes);
        }
        $referral->save();

        return response()->json([
            'success' => true,
            'message' => "Referral tracking updated to '{$referral->TrackingStatus}' (Status: {$referral->Status}).",
            'data'    => $referral
        ]);
    }

    /**
     * Route alias for createReferral
     */
    public function create(Request $request): JsonResponse
    {
        return $this->createReferral($request);
    }

    /**
     * List all clinical referrals for a patient
     */
    public function listForPatient(int $patientId): JsonResponse
    {
        $patient = Patient::find($patientId);
        if (!$patient) {
            return response()->json(['success' => false, 'message' => 'Patient not found.'], 404);
        }

        $referrals = DB::table('referrals as r')
            ->leftJoin('doctors as rd', 'r.ReferringDoctorID', '=', 'rd.DoctorID')
            ->leftJoin('doctors as td', 'r.TargetDoctorID', '=', 'td.DoctorID')
            ->where('r.PatientID', $patientId)
            ->select(
                'r.*',
                'rd.FirstName as ReferringDocFirst',
                'rd.LastName as ReferringDocLast',
                'td.FirstName as TargetDocFirst',
                'td.LastName as TargetDocLast'
            )
            ->orderBy('r.ReferralID', 'desc')
            ->get();

        return response()->json([
            'success'      => true,
            'patient_id'   => $patientId,
            'patient_name' => trim($patient->FirstName . ' ' . $patient->LastName),
            'count'        => count($referrals),
            'data'         => $referrals
        ]);
    }
}
