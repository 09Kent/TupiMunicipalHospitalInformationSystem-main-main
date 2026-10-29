<?php

namespace App\Services\Clinical;

use Illuminate\Support\Facades\DB;
use App\Models\Patient;

class PatientTimelineService
{
    /**
     * Build comprehensive chronological patient-care timeline from real database events
     * Fulfills Requirement 18: Patient Timeline
     */
    public static function getTimeline(int $patientId): array
    {
        $patient = Patient::find($patientId);
        if (!$patient) {
            return [];
        }

        $events = [];

        // 1. Patient Registration
        if (!empty($patient->CreatedAt)) {
            $events[] = [
                'timestamp' => strtotime($patient->CreatedAt),
                'time' => date('h:i A', strtotime($patient->CreatedAt)),
                'date' => date('M d, Y', strtotime($patient->CreatedAt)),
                'stage' => 'Registration',
                'title' => 'Patient Intake & Registration Completed',
                'description' => "Enrolled in TMHIMS with Digital ID {$patient->PatientCode}.",
                'actor' => 'Admitting Officer',
                'icon' => 'user-plus',
                'status' => 'completed'
            ];
        }

        // 2. Patient Verification
        if (!empty($patient->VerifiedAt)) {
            $events[] = [
                'timestamp' => strtotime($patient->VerifiedAt),
                'time' => date('h:i A', strtotime($patient->VerifiedAt)),
                'date' => date('M d, Y', strtotime($patient->VerifiedAt)),
                'stage' => 'Verification',
                'title' => 'Identity & Record Verified',
                'description' => 'Medical Records Officer verified patient credentials.',
                'actor' => $patient->VerifiedBy ?: 'Medical Records Officer',
                'icon' => 'shield-check',
                'status' => 'completed'
            ];
        }

        // 3. Appointments & Queue Ticket
        try {
            $apps = DB::table('appointments as a')
                ->leftJoin('doctors as d', 'a.DoctorID', '=', 'd.DoctorID')
                ->where('a.PatientID', $patientId)
                ->select('a.*', 'd.FirstName as DocFirst', 'd.LastName as DocLast')
                ->get();

            foreach ($apps as $a) {
                $docName = $a->DocLast ? "Dr. {$a->DocFirst} {$a->DocLast}" : 'Attending Physician';
                $events[] = [
                    'timestamp' => strtotime($a->CreatedAt ?? ($a->AppointmentDate . ' ' . $a->AppointmentTime)),
                    'time' => date('h:i A', strtotime($a->AppointmentTime ?: '08:00:00')),
                    'date' => date('M d, Y', strtotime($a->AppointmentDate)),
                    'stage' => 'Queue / Appointment',
                    'title' => 'Consultation Scheduled & Triage Queued',
                    'description' => "Booked for consultation with {$docName}. Reason: " . ($a->Reason ?: 'Clinical check'),
                    'actor' => 'Triage Staff',
                    'icon' => 'calendar',
                    'status' => 'completed'
                ];
            }
        } catch (\Throwable $e) {
        }

        // 4. Doctor Assignments
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('doctor_assignments')) {
                $docAssigns = DB::table('doctor_assignments as da')
                    ->leftJoin('doctors as d', 'da.DoctorID', '=', 'd.DoctorID')
                    ->where('da.PatientID', $patientId)
                    ->select('da.*', 'd.FirstName as DocFirst', 'd.LastName as DocLast')
                    ->get();

                foreach ($docAssigns as $da) {
                    $docName = $da->DocLast ? "Dr. {$da->DocFirst} {$da->DocLast}" : 'Attending Physician';
                    $events[] = [
                        'timestamp' => strtotime($da->AssignedAt),
                        'time' => date('h:i A', strtotime($da->AssignedAt)),
                        'date' => date('M d, Y', strtotime($da->AssignedAt)),
                        'stage' => 'Doctor Assignment',
                        'title' => 'Assigned to Attending Physician',
                        'description' => "Patient assigned to {$docName} by " . ($da->AssignedByName ?: 'Staff') . ".",
                        'actor' => $da->AssignedByName ?: 'Clinical Coordinator',
                        'icon' => 'stethoscope',
                        'status' => 'completed'
                    ];
                }
            }
        } catch (\Throwable $e) {
        }

        // 5. Vital Signs Records
        try {
            $vitals = DB::table('patient_vitals')->where('PatientID', $patientId)->get();
            foreach ($vitals as $v) {
                $events[] = [
                    'timestamp' => strtotime($v->CreatedAt),
                    'time' => date('h:i A', strtotime($v->CreatedAt)),
                    'date' => date('M d, Y', strtotime($v->CreatedAt)),
                    'stage' => 'Vital Signs',
                    'title' => 'Vital Signs Logged',
                    'description' => "BP: {$v->BloodPressure}, HR: {$v->HeartRate} bpm, Temp: {$v->Temperature}°C, SpO2: {$v->OxygenSaturation}%.",
                    'actor' => $v->RecordedByName ?: 'Nurse on Duty',
                    'icon' => 'activity',
                    'status' => 'completed'
                ];
            }
        } catch (\Throwable $e) {
        }

        // 6. Consultations (SOAP Notes)
        try {
            $notes = DB::table('consultation_notes as cn')
                ->leftJoin('doctors as d', 'cn.DoctorID', '=', 'd.DoctorID')
                ->where('cn.PatientID', $patientId)
                ->select('cn.*', 'd.FirstName as DocFirst', 'd.LastName as DocLast')
                ->get();

            foreach ($notes as $n) {
                $docName = $n->DocLast ? "Dr. {$n->DocFirst} {$n->DocLast}" : 'Attending Physician';
                $events[] = [
                    'timestamp' => strtotime($n->CreatedAt),
                    'time' => date('h:i A', strtotime($n->CreatedAt)),
                    'date' => date('M d, Y', strtotime($n->CreatedAt)),
                    'stage' => 'Consultation',
                    'title' => 'Clinical Consultation Note Recorded',
                    'description' => "SOAP assessment by {$docName}. Impression: " . ($n->Assessment ?: 'Evaluated'),
                    'actor' => $docName,
                    'icon' => 'clipboard-check',
                    'status' => 'completed'
                ];
            }
        } catch (\Throwable $e) {
        }

        // 7. Diagnoses
        try {
            $diags = DB::table('diagnoses')->where('PatientID', $patientId)->get();
            foreach ($diags as $d) {
                $events[] = [
                    'timestamp' => strtotime($d->CreatedAt ?? ($d->DiagnosedDate . ' 10:00:00')),
                    'time' => date('h:i A', strtotime($d->CreatedAt ?? ($d->DiagnosedDate . ' 10:00:00'))),
                    'date' => date('M d, Y', strtotime($d->DiagnosedDate ?: ($d->CreatedAt ?? 'now'))),
                    'stage' => 'Diagnosis',
                    'title' => "Diagnosis Formulated: {$d->DiagnosisName}",
                    'description' => "ICD-10: " . ($d->ICD10Code ?: 'Unspecified') . " ({$d->Type}, Severity: {$d->Severity}).",
                    'actor' => 'Attending Physician',
                    'icon' => 'file-heart',
                    'status' => 'completed'
                ];
            }
        } catch (\Throwable $e) {
        }

        // 8. Prescriptions
        try {
            $rxs = DB::table('prescriptions')->where('PatientID', $patientId)->get();
            foreach ($rxs as $r) {
                $events[] = [
                    'timestamp' => strtotime($r->CreatedAt),
                    'time' => date('h:i A', strtotime($r->CreatedAt)),
                    'date' => date('M d, Y', strtotime($r->CreatedAt)),
                    'stage' => 'Prescription',
                    'title' => "Prescription Created: {$r->MedicineName}",
                    'description' => "Dosage: {$r->Dosage}, {$r->Frequency} for {$r->Duration}.",
                    'actor' => 'Attending Physician',
                    'icon' => 'pill',
                    'status' => 'completed'
                ];
            }
        } catch (\Throwable $e) {
        }

        // 9. Nurse Tasks & Medication Administrations
        try {
            $tasks = DB::table('nurse_tasks as nt')
                ->leftJoin('users as u', 'nt.NurseID', '=', 'u.UserID')
                ->where('nt.PatientID', $patientId)
                ->select('nt.*', DB::raw("TRIM(CONCAT(COALESCE(u.FirstName, ''), ' ', COALESCE(u.LastName, ''))) as NurseName"))
                ->get();

            foreach ($tasks as $t) {
                $nurseName = $t->NurseName ?: 'Nurse on Duty';
                if ($t->AdministeredAt) {
                    $events[] = [
                        'timestamp' => strtotime($t->AdministeredAt),
                        'time' => date('h:i A', strtotime($t->AdministeredAt)),
                        'date' => date('M d, Y', strtotime($t->AdministeredAt)),
                        'stage' => 'Medication Administration',
                        'title' => "Medication Administered: " . ($t->MedicationName ?: $t->TaskTitle),
                        'description' => "Administered by {$nurseName}. Status: {$t->Status}.",
                        'actor' => $nurseName,
                        'icon' => 'syringe',
                        'status' => 'completed'
                    ];
                } else {
                    $events[] = [
                        'timestamp' => strtotime($t->ScheduledTime ?? $t->CreatedAt),
                        'time' => date('h:i A', strtotime($t->ScheduledTime ?? $t->CreatedAt)),
                        'date' => date('M d, Y', strtotime($t->ScheduledTime ?? $t->CreatedAt)),
                        'stage' => 'Nursing Task',
                        'title' => "Nursing Task Scheduled: {$t->TaskTitle}",
                        'description' => "Assigned to {$nurseName} (Priority: {$t->Priority}, Status: {$t->Status}).",
                        'actor' => $t->AssignedByName ?: 'Care Coordinator',
                        'icon' => 'clock',
                        'status' => ($t->Status === 'Due') ? 'current' : 'pending'
                    ];
                }
            }
        } catch (\Throwable $e) {
        }

        // 10. Referrals
        try {
            $refs = DB::table('referrals')->where('PatientID', $patientId)->get();
            foreach ($refs as $rf) {
                $typeDesc = ($rf->ReferralType === 'External') ? "to {$rf->ReceivingHospital}" : "to Specialist Doctor";
                $events[] = [
                    'timestamp' => strtotime($rf->CreatedAt),
                    'time' => date('h:i A', strtotime($rf->CreatedAt)),
                    'date' => date('M d, Y', strtotime($rf->CreatedAt)),
                    'stage' => 'Referral',
                    'title' => "Clinical Referral Created ({$rf->ReferralType})",
                    'description' => "Referred {$typeDesc}. Reason: {$rf->Reason}.",
                    'actor' => 'Attending Physician',
                    'icon' => 'share-2',
                    'status' => 'completed'
                ];
            }
        } catch (\Throwable $e) {
        }

        // 11. Billing & Payments
        try {
            $invoices = DB::table('billing_invoices')->where('PatientID', $patientId)->get();
            foreach ($invoices as $inv) {
                $events[] = [
                    'timestamp' => strtotime($inv->CreatedAt),
                    'time' => date('h:i A', strtotime($inv->CreatedAt)),
                    'date' => date('M d, Y', strtotime($inv->CreatedAt)),
                    'stage' => 'Billing',
                    'title' => "Billing Invoice Generated ({$inv->InvoiceNumber})",
                    'description' => "Gross: ₱" . number_format($inv->GrossAmount, 2) . ", PhilHealth: ₱" . number_format($inv->PhilHealthDeduction, 2) . ", Payable: ₱" . number_format($inv->TotalPayable, 2) . ".",
                    'actor' => 'Cashier / Billing Staff',
                    'icon' => 'receipt',
                    'status' => 'completed'
                ];
            }
        } catch (\Throwable $e) {
        }

        try {
            $payments = DB::table('billing_payments')->where('PatientID', $patientId)->get();
            foreach ($payments as $pmt) {
                $events[] = [
                    'timestamp' => strtotime($pmt->PaymentDate ?? 'now'),
                    'time' => date('h:i A', strtotime($pmt->PaymentDate ?? 'now')),
                    'date' => date('M d, Y', strtotime($pmt->PaymentDate ?? 'now')),
                    'stage' => 'Payment Settlement',
                    'title' => "Payment Settled ({$pmt->ReceiptNumber})",
                    'description' => "Amount: ₱" . number_format($pmt->AmountPaid, 2) . " via {$pmt->PaymentMethod} (Ref: " . ($pmt->ReferenceNumber ?: 'Direct') . ").",
                    'actor' => $pmt->CashierName ?: 'Cashier Staff',
                    'icon' => 'credit-card',
                    'status' => 'completed'
                ];
            }
        } catch (\Throwable $e) {
        }

        // Sort events chronologically (oldest to newest)
        usort($events, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        return $events;
    }
}
