<?php

namespace Tests\Feature;

use App\Services\Register\PatientRegistration;
use App\Models\User;
use App\Models\Doctor;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__ . '/../../app/Services/Doctor/LaboratoryRequest.php';

class ReturningPatientWorkflowTest extends TestCase
{
    public function test_returning_patient_reuses_identity_and_creates_a_new_encounter(): void
    {
        $patient = DB::table('patients')->orderBy('PatientID')->first();
        $doctor = DB::table('doctors')->whereIn('Status', ['Available', 'Busy'])->orderBy('DoctorID')->first();

        $this->assertNotNull($patient, 'The integration database must contain a patient fixture.');
        $this->assertNotNull($doctor, 'The integration database must contain an active doctor fixture.');

        DB::beginTransaction();

        try {
            $appointmentsBefore = DB::table('appointments')->where('PatientID', $patient->PatientID)->count();
            $historyBefore = DB::table('medical_histories')->where('PatientID', $patient->PatientID)->count();

            $result = (new PatientRegistration(DB::connection()->getPdo()))->registerCompletePatient([
                'patient_id' => $patient->PatientID,
                'personal' => [
                    'firstName' => $patient->FirstName,
                    'lastName' => $patient->LastName,
                    'dob' => $patient->DateOfBirth,
                    'gender' => $patient->Gender,
                    'phone' => $patient->ContactNumber,
                    'address' => $patient->Address,
                ],
                'complaint' => 'Regression test returning-patient encounter',
                'doctorId' => $doctor->DoctorID,
                'appointmentDate' => date('Y-m-d', strtotime('+30 days')),
                'selectedSlot' => '11:45 AM',
                'consultationType' => 'Follow-up Review',
            ], 1);

            $this->assertTrue($result['success'], $result['message'] ?? 'Returning registration failed.');
            $this->assertSame((int)$patient->PatientID, (int)$result['patient_id']);
            $this->assertSame($appointmentsBefore + 1, DB::table('appointments')->where('PatientID', $patient->PatientID)->count());
            $this->assertSame($historyBefore, DB::table('medical_histories')->where('PatientID', $patient->PatientID)->count());
            $this->assertSame(1, DB::table('patients')->where('PatientID', $patient->PatientID)->count());
            $this->assertDatabaseHas('billing_charges', [
                'AppointmentID' => $result['appointment_id'],
                'ChargeCategory' => 'Consultation',
                'source_type' => 'consultation',
                'source_id' => $result['appointment_id'],
                'BillingStatus' => 'Unbilled',
                'UnitPrice' => '500.00',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_duplicate_laboratory_submission_creates_one_request_and_one_charge(): void
    {
        $patient = DB::table('patients')->orderBy('PatientID')->first();
        $doctor = DB::table('doctors')->whereIn('Status', ['Available', 'Busy'])->orderBy('DoctorID')->first();

        $this->assertNotNull($patient);
        $this->assertNotNull($doctor);

        DB::beginTransaction();

        try {
            $service = new \LaboratoryRequest(DB::connection()->getPdo());
            $payload = [
                'patient_id' => $patient->PatientID,
                'doctor_id' => $doctor->DoctorID,
                'test_type' => 'Regression CBC Duplicate Guard',
                'requested_date' => date('Y-m-d', strtotime('+31 days')),
                'priority' => 'Routine',
            ];

            $firstRequest = $service->create($payload);
            $secondRequest = $service->create($payload);

            $this->assertSame($firstRequest, $secondRequest);
            $this->assertSame(1, DB::table('laboratory_requests')->where('RequestID', $firstRequest)->count());
            $this->assertSame(1, DB::table('billing_charges')->where('source_type', 'laboratory_request')->where('source_id', $firstRequest)->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_doctor_can_search_active_laboratory_catalog(): void
    {
        $doctor = User::where('Username', 'dr.chen')->first();
        $this->assertNotNull($doctor);

        $response = $this->actingAs($doctor)->getJson('/doctor/api/laboratory-tests?q=cbc');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonFragment(['TestName' => 'Complete Blood Count (CBC) with Platelet']);
    }

    public function test_doctor_and_staff_accounts_have_database_department_relationships(): void
    {
        $doctor = Doctor::with(['department', 'specialty', 'user'])->where('DoctorID', 3)->first();
        $chief = User::with('departments')->where('Username', 'chief.medical')->first();

        $this->assertNotNull($doctor);
        $this->assertNotNull($doctor->department);
        $this->assertNotNull($doctor->specialty);
        $this->assertNotNull($doctor->user);
        $this->assertNotNull($chief);
        $this->assertTrue($chief->departments->isNotEmpty());
    }
}