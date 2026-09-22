<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\Appointment;
use App\Models\PatientQueue;
use App\Models\ConsultationNote;
use App\Models\PatientVital;
use App\Models\Diagnosis;
use App\Models\TreatmentPlan;
use Illuminate\Support\Facades\DB;

class EndToEndHospitalFlowTest extends TestCase
{
    private ?int $testPatientId = null;
    private ?int $testAppointmentId = null;
    private ?string $testPatientCode = null;

    protected function setUp(): void
    {
        parent::setUp();

        $lingering = Patient::where('Email', 'qa_integrated_test@tmhis.gov.ph')
            ->orWhere('FirstName', 'QA_INTEGRATION_TEST')
            ->get();

        foreach ($lingering as $p) {
            DB::table('treatment_plans')->where('PatientID', $p->PatientID)->delete();
            DB::table('diagnoses')->where('PatientID', $p->PatientID)->delete();
            DB::table('patient_vitals')->where('PatientID', $p->PatientID)->delete();
            DB::table('consultation_notes')->where('PatientID', $p->PatientID)->delete();
            DB::table('patient_queue')->where('PatientID', $p->PatientID)->delete();
            DB::table('appointments')->where('PatientID', $p->PatientID)->delete();
            $p->delete();
        }
    }

    protected function tearDown(): void
    {
        // Safe cleanup: Only delete QA test data created by this test suite
        if ($this->testPatientId) {
            DB::table('treatment_plans')->where('PatientID', $this->testPatientId)->delete();
            DB::table('diagnoses')->where('PatientID', $this->testPatientId)->delete();
            DB::table('patient_vitals')->where('PatientID', $this->testPatientId)->delete();
            DB::table('consultation_notes')->where('PatientID', $this->testPatientId)->delete();
            DB::table('patient_queue')->where('PatientID', $this->testPatientId)->delete();
            DB::table('appointments')->where('PatientID', $this->testPatientId)->delete();
            DB::table('patients')->where('PatientID', $this->testPatientId)->delete();
        }

        // Also clean up any lingering test patients with our unique test email/name
        $lingering = Patient::where('Email', 'qa_integrated_test@tmhis.gov.ph')
            ->orWhere('FirstName', 'QA_INTEGRATION_TEST')
            ->get();

        foreach ($lingering as $p) {
            DB::table('treatment_plans')->where('PatientID', $p->PatientID)->delete();
            DB::table('diagnoses')->where('PatientID', $p->PatientID)->delete();
            DB::table('patient_vitals')->where('PatientID', $p->PatientID)->delete();
            DB::table('consultation_notes')->where('PatientID', $p->PatientID)->delete();
            DB::table('patient_queue')->where('PatientID', $p->PatientID)->delete();
            DB::table('appointments')->where('PatientID', $p->PatientID)->delete();
            $p->delete();
        }

        parent::tearDown();
    }

    public function test_complete_hospital_flow_from_registration_to_doctor_to_medical_records(): void
    {
        // -------------------------------------------------------------
        // Step 1: Resolve Active Doctor for Intake
        // -------------------------------------------------------------
        $doctor = Doctor::where('Status', 'Active')->first() ?? Doctor::first();
        $this->assertNotNull($doctor, 'At least one active doctor must exist in the database.');
        $doctorId = (int)$doctor->DoctorID;

        // -------------------------------------------------------------
        // Step 2: Patient Intake / Registration (Role 1: Registrator / Patient)
        // -------------------------------------------------------------
        $dob = '1992-06-15';
        $registrationPayload = [
            'personal' => [
                'firstName'       => 'QA_INTEGRATION_TEST',
                'lastName'        => 'PATIENT',
                'middleName'      => 'Automated',
                'dob'             => $dob,
                'gender'          => 'Male',
                'civilStatus'     => 'Single',
                'bloodType'       => 'O+',
                'patientCategory' => 'Outpatient',
                'phone'           => '0999-888-7766',
                'email'           => 'qa_integrated_test@tmhis.gov.ph',
                'address'         => 'Tupi Central Plaza, South Cotabato',
            ],
            'emergency' => [
                'name'         => 'Emergency Contact',
                'relationship' => 'Spouse',
                'phone'        => '0999-000-1122',
            ],
            'complaint'        => 'Severe intermittent palpitations and dizziness',
            'severity'         => 7,
            'duration'         => '3 days',
            'symptoms'         => ['Palpitations', 'Dizziness'],
            'bodyLocation'     => 'chest',
            'doctorId'         => $doctorId,
            'appointmentDate'  => date('Y-m-d'),
            'selectedSlot'     => '11:00 AM',
            'consultationType' => 'In-Person Consultation',
        ];

        $registerRes = $this->postJson('/api/register/submit', $registrationPayload);
        $registerRes->assertStatus(201);
        $registerRes->assertJsonFragment(['success' => true]);

        $createdPatient = Patient::where('Email', 'qa_integrated_test@tmhis.gov.ph')->first();
        $this->assertNotNull($createdPatient, 'Patient must be saved to the database upon registration.');
        $this->testPatientId = (int)$createdPatient->PatientID;
        $this->testPatientCode = $createdPatient->PatientCode;

        // Verify Appointment created
        $appointment = Appointment::where('PatientID', $this->testPatientId)->orderBy('AppointmentID', 'desc')->first();
        $this->assertNotNull($appointment, 'Appointment record must be created for registered patient.');
        $this->testAppointmentId = (int)$appointment->AppointmentID;
        $this->assertEquals($doctorId, (int)$appointment->DoctorID);

        // Verify Queue entry created
        $queue = PatientQueue::where('AppointmentID', $this->testAppointmentId)->first();
        $this->assertNotNull($queue, 'Patient must be placed into the patient_queue table.');

        // -------------------------------------------------------------
        // Step 3: Doctor Consultation & Completion (Role 2: Doctor)
        // -------------------------------------------------------------
        $doctorUser = User::where('Username', 'cardio')->first() 
            ?? User::where('Role', 'Doctor')->first();
        $this->assertNotNull($doctorUser, 'Doctor user must exist in the database.');

        $consultationPayload = [
            'action'         => 'complete_consultation',
            'appointment_id' => $this->testAppointmentId,
            'patient_id'     => $this->testPatientId,
            'doctor_id'      => $doctorId,
            'subjective'     => 'Patient reports 3-day history of palpitations triggered by exertion, resolving with rest.',
            'objective'      => 'Vitals stable. S1/S2 distinct, no murmurs. EKG normal sinus rhythm.',
            'assessment'     => 'Sinus Tachycardia, exertion-induced, resolved.',
            'plan'           => 'Adequate hydration, stress management, avoid caffeinated beverages. Recheck in 2 weeks.',
            'vital_bp'       => '118/76',
            'vital_hr'       => '72',
            'vital_temp'     => '36.7',
            'vital_rr'       => '16',
            'vital_o2'       => '99',
            'vital_weight'   => '70',
            'vital_height'   => '175',
        ];

        $consultationRes = $this->actingAs($doctorUser)
            ->withSession([
                'doctor_id' => $doctorId,
                'role'      => 'Doctor'
            ])
            ->post("/doctor/diagnosis?appointment_id={$this->testAppointmentId}", $consultationPayload);

        // Assert Consultation Note saved to Supabase/MySQL
        $note = ConsultationNote::where('PatientID', $this->testPatientId)->first();
        $this->assertNotNull($note, 'Consultation note must be persisted to consultation_notes table.');
        $this->assertStringContainsString('Sinus Tachycardia', $note->Assessment);
        $this->assertStringContainsString('Adequate hydration', $note->Plan);

        // Assert Patient Vitals saved to database
        $vitals = PatientVital::where('PatientID', $this->testPatientId)->first();
        $this->assertNotNull($vitals, 'Patient vitals must be persisted to patient_vitals table.');
        $this->assertEquals('118/76', $vitals->BloodPressure);
        $this->assertEquals('72', (string)$vitals->HeartRate);

        // Assert Diagnosis saved to database
        $diagnosis = Diagnosis::where('PatientID', $this->testPatientId)->first();
        $this->assertNotNull($diagnosis, 'Clinical diagnosis must be persisted to diagnoses table.');
        $this->assertStringContainsString('Sinus Tachycardia', $diagnosis->DiagnosisName);

        // Assert Treatment Plan saved to database
        $treatment = TreatmentPlan::where('PatientID', $this->testPatientId)->first();
        $this->assertNotNull($treatment, 'Treatment plan must be persisted to treatment_plans table.');

        // Assert Appointment marked Completed
        $appointment->refresh();
        $this->assertEquals('Completed', $appointment->Status);

        // -------------------------------------------------------------
        // Step 4: Medical Records Officer History API (Role 3: Records)
        // -------------------------------------------------------------
        $recordsUser = User::where('Username', 'records')->first()
            ?? User::where('Role', 'Records')->first();
        $this->assertNotNull($recordsUser, 'Medical Records Officer user must exist.');

        // Test querying by PatientCode (e.g. PAT-2026-XXXX)
        $historyResByCode = $this->actingAs($recordsUser)->getJson("/records/api/history/{$this->testPatientCode}");
        $historyResByCode->assertStatus(200);
        $historyResByCode->assertJsonFragment(['success' => true]);
        
        $consultations = $historyResByCode->json('consultations');
        $this->assertNotEmpty($consultations, 'Medical records history API must return the newly completed consultation.');
        $this->assertEquals($this->testPatientCode, $consultations[0]['patientId']);
        $this->assertStringContainsString('Sinus Tachycardia', $consultations[0]['diagnosis']);
        $this->assertEquals('Completed', $consultations[0]['status']);

        // Test querying by numeric PatientID
        $historyResById = $this->actingAs($recordsUser)->getJson("/records/api/history/{$this->testPatientId}");
        $historyResById->assertStatus(200);
        $historyResById->assertJsonFragment(['success' => true]);

        // -------------------------------------------------------------
        // Step 5: Medical Records Official Summary View
        // -------------------------------------------------------------
        $summaryRes = $this->actingAs($recordsUser)->get("/records/summary/{$this->testPatientCode}");
        $summaryRes->assertStatus(200);
        $summaryRes->assertSee('QA_INTEGRATION_TEST');
        $summaryRes->assertSee('Sinus Tachycardia');
        $summaryRes->assertSee('Adequate hydration');
    }
}
