<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Patient;
use App\Models\Appointment;

class FddWorkflowTest extends TestCase
{
    /**
     * 1. Test Authentication for all 9 FDD Roles
     */
    public function test_all_nine_fdd_roles_can_authenticate_and_redirect_correctly(): void
    {
        $roles = [
            'admin'       => '/admin',
            'director'    => '/director',
            'records'     => '/records',
            'registrator' => '/register',
            'cardio'      => '/doctor',
            'nurse'       => '/nurse',
            'medtech'     => '/medtech',
            'pharmacist'  => '/pharmacy',
            'cashier'     => '/billing',
        ];

        foreach ($roles as $username => $expectedDashboard) {
            $user = User::where('Username', $username)->first();
            $this->assertNotNull($user, "User '$username' must exist in the database.");

            $response = $this->post('/login', [
                'email'    => $username,
                'password' => 'password123',
            ]);

            $response->assertRedirect($expectedDashboard);
            $this->assertAuthenticatedAs($user);

            // Verify authorized dashboard loads with 200
            $dashRes = $this->get($expectedDashboard);
            $dashRes->assertStatus(200);

            $this->post('/logout');
            $this->assertGuest();
        }
    }

    /**
     * 2. Test Unauthenticated Access is Blocked
     */
    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $protectedUrls = [
            '/admin',
            '/director',
            '/records',
            '/doctor',
            '/nurse',
            '/medtech',
            '/pharmacy',
            '/billing',
        ];

        foreach ($protectedUrls as $url) {
            $response = $this->get($url);
            $response->assertRedirect('/login');
        }
    }

    /**
     * 3. Test RBAC Authorization - Non-admin cannot access admin
     */
    public function test_unauthorized_roles_are_blocked_from_admin(): void
    {
        $nurse = User::where('Username', 'nurse')->first();
        $this->actingAs($nurse);

        $response = $this->get('/admin');
        // RoleMiddleware either redirects with error or gives 403
        $this->assertTrue(in_array($response->getStatusCode(), [302, 403]));
    }

    /**
     * 4. Test System Administrator FDD APIs
     */
    public function test_admin_apis_function_correctly(): void
    {
        $admin = User::where('Username', 'admin')->first();
        $this->actingAs($admin);

        // Hospital Info
        $configRes = $this->get('/admin/api/config.php?action=get');
        $configRes->assertStatus(200);
        $configRes->assertJsonStructure(['success', 'data']);

        // Users API
        $usersRes = $this->get('/admin/api/users.php?action=list');
        $usersRes->assertStatus(200);
        $usersRes->assertJsonStructure(['success', 'data']);

        // Departments API
        $deptsRes = $this->get('/admin/api/departments.php?action=list');
        $deptsRes->assertStatus(200);
        $deptsRes->assertJsonStructure(['success', 'data']);

        // Service Fees API
        $feesRes = $this->get('/admin/api/service_fees.php?action=list');
        $feesRes->assertStatus(200);
        $feesRes->assertJsonStructure(['success', 'data']);

        // Backup History
        $backupsRes = $this->get('/admin/api/backups.php?action=list');
        $backupsRes->assertStatus(200);
        $backupsRes->assertJsonStructure(['success', 'data']);
    }

    /**
     * 5. Test Hospital Chief / Medical Director FDD APIs
     */
    public function test_director_operational_reports_and_performance(): void
    {
        $director = User::where('Username', 'director')->first();
        $this->actingAs($director);

        // Department Performance
        $deptPerf = $this->get('/director/api/department-performance.php');
        $deptPerf->assertStatus(200);
        $deptPerf->assertJson(['status' => 'success']);

        // Doctor Stats
        $docStats = $this->get('/director/api/doctor-stats.php');
        $docStats->assertStatus(200);
        $docStats->assertJson(['status' => 'success']);

        // Dashboard Stats
        $dashStats = $this->get('/director/api/dashboard-stats.php');
        $dashStats->assertStatus(200);
        $dashStats->assertJson(['status' => 'success']);
    }

    /**
     * 6. Test Admitting / Registration Staff Functions
     */
    public function test_registration_intake_and_appointments(): void
    {
        // Public intake form
        $intakeRes = $this->get('/register/intake');
        $intakeRes->assertStatus(200);

        // Staff features
        $registrator = User::where('Username', 'registrator')->first();
        $this->actingAs($registrator);

        $patientsRes = $this->get('/register/patients');
        $patientsRes->assertStatus(200);

        $appointmentsRes = $this->get('/register/appointments');
        $appointmentsRes->assertStatus(200);

        $queueRes = $this->get('/register/queue');
        $queueRes->assertStatus(200);

        $reportsRes = $this->get('/register/reports');
        $reportsRes->assertStatus(200);
    }

    /**
     * 7. Test Attending Physician Portal & Subroutes
     */
    public function test_doctor_portal_subpages_render_cleanly(): void
    {
        $doctor = User::where('Username', 'cardio')->first();
        $this->actingAs($doctor);

        $doctorPages = [
            '/doctor',
            '/doctor/patients',
            '/doctor/diagnosis',
            '/doctor/prescriptions',
            '/doctor/laboratory',
            '/doctor/referrals',
            '/doctor/certificates',
            '/doctor/settings',
        ];

        foreach ($doctorPages as $page) {
            $res = $this->get($page);
            $this->assertEquals(200, $res->getStatusCode(), "Doctor page '$page' should return HTTP 200");
        }
    }

    /**
     * 8. Test Nurse on Duty Portal & Subroutes
     */
    public function test_nurse_portal_subpages_render_cleanly(): void
    {
        $nurse = User::where('Username', 'nurse')->first();
        $this->actingAs($nurse);

        $nursePages = [
            '/nurse',
            '/nurse/vitals',
            '/nurse/patients',
            '/nurse/queue',
            '/nurse/tasks',
            '/nurse/notifications',
            '/nurse/settings',
        ];

        foreach ($nursePages as $page) {
            $res = $this->get($page);
            $this->assertEquals(200, $res->getStatusCode(), "Nurse page '$page' should return HTTP 200");
        }
    }

    /**
     * 9. Test Medical Technologist Portal
     */
    public function test_medtech_portal_renders(): void
    {
        $medtech = User::where('Username', 'medtech')->first();
        $this->actingAs($medtech);

        $res = $this->get('/medtech');
        $res->assertStatus(200);
    }

    /**
     * 10. Test Pharmacist Portal
     */
    public function test_pharmacy_portal_renders(): void
    {
        $pharmacist = User::where('Username', 'pharmacist')->first();
        $this->actingAs($pharmacist);

        $res = $this->get('/pharmacy');
        $res->assertStatus(200);
    }

    /**
     * 11. Test Billing & Cashier Portal and API
     */
    public function test_billing_portal_and_api(): void
    {
        $cashier = User::where('Username', 'cashier')->first();
        $this->actingAs($cashier);

        $res = $this->get('/billing');
        $res->assertStatus(200);

        $apiRes = $this->get('/billing/api/data');
        $apiRes->assertStatus(200);
        $apiRes->assertJsonStructure(['success', 'data' => ['metrics', 'patients', 'charges', 'payments']]);
    }

    /**
     * 12. Test Medical Records Officer Portal
     */
    public function test_medical_records_portal(): void
    {
        $recordsOfficer = User::where('Username', 'records')->first();
        $this->actingAs($recordsOfficer);

        $res = $this->get('/records');
        $res->assertStatus(200);
    }

    /**
     * 13. Comprehensive End-to-End FDD Hospital Workflow Test
     * Tests the complete multi-role clinical and operational pipeline:
     * Registration -> Queue -> Nurse Vitals -> Doctor SOAP/Rx/Lab -> MedTech Results -> Pharmacist Dispensing -> Cashier Billing -> Medical Records
     */
    public function test_complete_end_to_end_fdd_hospital_workflow(): void
    {
        // STEP 1: Registration Staff registers patient
        $registerStaff = User::where('Username', 'registrator')->first() ?? User::where('Role', 'Registration')->first();
        $this->actingAs($registerStaff);

        $uniqueCode = 'P-TEST-' . time();
        $patient = \App\Models\Patient::create([
            'PatientCode'     => $uniqueCode,
            'FirstName'       => 'Juan',
            'MiddleName'      => 'Protacio',
            'LastName'        => 'Dela Cruz',
            'DateOfBirth'     => '1990-05-15',
            'Age'             => 36,
            'Gender'          => 'Male',
            'CivilStatus'     => 'Married',
            'ContactNumber'   => '09171234567',
            'Email'           => 'juan.delacruz.' . time() . '@example.com',
            'Address'         => 'Poblacion, Tupi, South Cotabato',
            'BloodType'       => 'O+',
            'PatientCategory' => 'Outpatient',
            'Status'          => 'Active',
            'RegisteredBy'    => $registerStaff->UserID ?? 1,
        ]);

        $this->assertNotNull($patient->PatientID);
        $patientId = $patient->PatientID;

        // Queue generation
        $doctorProfile = \App\Models\Doctor::first();
        $doctorId = $doctorProfile->DoctorID;

        $queue = \App\Models\PatientQueue::create([
            'PatientID'   => $patientId,
            'DoctorID'    => $doctorId,
            'QueueNumber' => 'Q-' . rand(100, 999),
            'QueueDate'   => date('Y-m-d'),
            'QueueStatus' => 'Waiting',
            'Priority'    => 'Normal',
        ]);
        $this->assertNotNull($queue->QueueID);

        // STEP 2: Nurse on Duty takes Vitals
        $nurse = User::where('Username', 'nurse')->first();
        $this->actingAs($nurse);

        $vitals = \App\Models\PatientVital::create([
            'PatientID'        => $patientId,
            'BloodPressure'    => '120/80',
            'HeartRate'        => 75,
            'RespiratoryRate'  => 18,
            'Temperature'      => 36.6,
            'OxygenSaturation' => 99.0,
            'WeightKg'         => 68.5,
            'HeightCm'         => 172.0,
            'BMI'              => 23.15,
            'ClinicalNotes'    => 'Normal baseline vital signs',
            'RecordedBy'       => $nurse->UserID ?? 1,
            'RecordedByName'   => 'Nurse on Duty',
        ]);
        $this->assertNotNull($vitals->VitalID);

        // Update queue to In Consultation
        $queue->update(['QueueStatus' => 'In Consultation']);

        // STEP 3: Attending Physician consults, diagnoses, prescribes, and orders lab
        $doctor = User::where('Username', 'cardio')->first() ?? User::where('Role', 'Doctor')->first();
        $this->actingAs($doctor);

        $soapNote = \App\Models\ConsultationNote::create([
            'PatientID'     => $patientId,
            'DoctorID'      => $doctorId,
            'Subjective'    => 'Patient complains of fever and cough for 3 days.',
            'Objective'     => 'Pharyngeal erythema noted. Lungs clear to auscultation.',
            'Assessment'    => 'Upper Respiratory Tract Infection',
            'Plan'          => 'Prescribe antibiotics and order CBC with Platelet count.',
            'ClinicalNotes' => 'Patient advised on rest and hydration.',
        ]);
        $this->assertNotNull($soapNote->NoteID);

        $diagnosis = \App\Models\Diagnosis::create([
            'PatientID'     => $patientId,
            'DoctorID'      => $doctorId,
            'DiagnosisName' => 'Acute Upper Respiratory Infection',
            'ICD10Code'     => 'J06.9',
            'Type'          => 'Primary',
            'Severity'      => 'Moderate',
            'Status'        => 'Active',
            'DiagnosedDate' => date('Y-m-d'),
        ]);
        $this->assertNotNull($diagnosis->DiagnosisID);

        $prescription = \App\Models\Prescription::create([
            'PrescriptionCode' => 'RX-' . rand(1000, 9999),
            'PatientID'        => $patientId,
            'DoctorID'         => $doctorId,
            'MedicineName'     => 'Amoxicillin 500mg Capsule',
            'Dosage'           => '500mg',
            'Frequency'        => 'Three times a day (TID)',
            'Duration'         => '7 days',
            'Instructions'     => 'Take after meals',
            'Quantity'         => 21,
            'Status'           => 'Active',
            'IssuedDate'       => date('Y-m-d'),
        ]);
        $this->assertNotNull($prescription->PrescriptionID);

        $labRequest = \App\Models\LaboratoryRequest::create([
            'RequestCode'   => 'LAB-' . rand(1000, 9999),
            'PatientID'     => $patientId,
            'DoctorID'      => $doctorId,
            'TestType'      => 'Complete Blood Count (CBC)',
            'Priority'      => 'Routine',
            'ClinicalNotes' => 'Evaluate for leukocytosis',
            'Status'        => 'Pending',
            'RequestedDate' => date('Y-m-d'),
        ]);
        $this->assertNotNull($labRequest->RequestID);

        // STEP 4: Medical Technologist collects specimen and encodes result
        $medtech = User::where('Username', 'medtech')->first();
        $this->actingAs($medtech);

        $labRequest->update(['Status' => 'Completed']);

        $labResult = \App\Models\LaboratoryResult::create([
            'RequestID'      => $labRequest->RequestID,
            'PatientID'      => $patientId,
            'DoctorID'       => $doctorId,
            'TestName'       => 'Hemoglobin & WBC',
            'ResultValue'    => 'WBC: 8.5 x10^9/L, Hgb: 14.5 g/dL',
            'NormalRange'    => 'WBC: 4.5-11.0, Hgb: 13.5-17.5',
            'Units'          => 'Standard clinical units',
            'Interpretation' => 'Normal',
            'ResultDate'     => date('Y-m-d'),
        ]);
        $this->assertNotNull($labResult->ResultID);

        // STEP 5: Pharmacist verifies and dispenses prescription
        $pharmacist = User::where('Username', 'pharmacist')->first();
        $this->actingAs($pharmacist);

        $prescription->update(['Status' => 'Completed']);

        $dispense = \App\Models\DispensingRecord::create([
            'DispenseCode'       => 'DSP-' . rand(1000, 9999),
            'PrescriptionID'     => $prescription->PrescriptionID,
            'PatientID'          => $patientId,
            'DispensedBy'        => $pharmacist->UserID ?? 1,
            'DispenserName'      => 'Hospital Pharmacist',
            'QuantityDispensed'  => 21,
            'DosageInstructions' => 'Take 1 capsule every 8 hours for 7 days',
            'BatchNumber'        => 'BATCH-AMX-2026-01',
            'DispenseDate'       => date('Y-m-d H:i:s'),
            'Status'             => 'Dispensed',
        ]);
        $this->assertNotNull($dispense->DispenseID);

        // STEP 6: Cashier aggregates charges, issues bill and processes payment
        $cashier = User::where('Username', 'cashier')->first();
        $this->actingAs($cashier);

        $charge = \App\Models\BillingCharge::create([
            'PatientID'       => $patientId,
            'ChargeCategory'  => 'Consultation',
            'ItemDescription' => 'General Consultation + CBC Test Fee',
            'Quantity'        => 1,
            'UnitPrice'       => 500.00,
            'SubTotal'        => 500.00,
            'DiscountAmount'  => 0.00,
            'NetAmount'       => 500.00,
            'BillingStatus'   => 'Unbilled',
        ]);
        $this->assertNotNull($charge->ChargeID);

        $invoice = \App\Models\BillingInvoice::create([
            'InvoiceNumber'   => 'INV-' . time(),
            'PatientID'       => $patientId,
            'GrossAmount'     => 500.00,
            'DiscountType'    => 'None',
            'DiscountAmount'  => 0.00,
            'TotalPayable'    => 500.00,
            'AmountPaid'      => 500.00,
            'BalanceDue'      => 0.00,
            'PaymentStatus'   => 'Paid In Full',
            'DueDate'         => date('Y-m-d', strtotime('+30 days')),
            'BilledBy'        => $cashier->UserID ?? 1,
        ]);
        $this->assertNotNull($invoice->InvoiceID);

        $payment = \App\Models\BillingPayment::create([
            'ReceiptNumber' => 'OR-' . time(),
            'InvoiceID'     => $invoice->InvoiceID,
            'PatientID'     => $patientId,
            'AmountPaid'    => 500.00,
            'PaymentMethod' => 'Cash',
            'AmountInWords' => 'Five Hundred Pesos Only',
            'CashierName'   => 'Cashier Officer',
            'PaymentDate'   => date('Y-m-d H:i:s'),
        ]);
        $this->assertNotNull($payment->PaymentID);

        // STEP 7: Medical Records Officer reviews complete unified chart
        $recordsOfficer = User::where('Username', 'records')->first();
        $this->actingAs($recordsOfficer);

        $patientSummary = \App\Models\Patient::with([
            'consultationNotes',
            'diagnoses',
            'prescriptions',
            'laboratoryRequests',
            'vitals',
            'billingCharges',
        ])->find($patientId);

        $this->assertNotNull($patientSummary);
        $this->assertEquals('Juan', $patientSummary->FirstName);
        $this->assertEquals('Dela Cruz', $patientSummary->LastName);

        // Clean up test record to maintain database hygiene
        $payment->delete();
        $invoice->delete();
        $charge->delete();
        $dispense->delete();
        $labResult->delete();
        $labRequest->delete();
        $prescription->delete();
        $diagnosis->delete();
        $soapNote->delete();
        $vitals->delete();
        $queue->delete();
        $patient->delete();
    }

    /**
     * 16. Test Patient Registration Foreign Key Integrity (Issue 1)
     */
    public function test_patient_registration_submission_foreign_key_integrity(): void
    {
        $unique = time() . rand(10, 99);
        $payload = [
            'personal' => [
                'firstName'   => 'Intake' . $unique,
                'lastName'    => 'Integrity' . $unique,
                'dob'         => '1992-04-12',
                'age'         => 34,
                'gender'      => 'Male',
                'civilStatus' => 'Married',
                'phone'       => '09171234567',
                'email'       => "intake{$unique}@example.com",
                'address'     => 'Brays, Tupi, South Cotabato',
            ],
            'emergency' => [
                'name'         => 'Jane Integrity',
                'relationship' => 'Spouse',
                'phone'        => '09177654321',
            ],
            'medical' => [
                'bloodType'       => 'A+',
                'allergies'       => 'Penicillin',
                'conditions'      => 'Hypertension',
                'medications'     => 'Amlodipine 5mg',
                'hospitalization' => 'None',
            ],
            'complaint'       => 'Severe epigastric pain and acid reflux',
            'severity'        => 3,
            'duration'        => '1–3 days ago',
            'bodyLocation'    => 'abdomen',
            'bodySystemId'    => 1,
            'doctorId'        => 1,
            'appointmentDate' => date('Y-m-d', strtotime('+' . rand(100, 300) . ' days')),
            'selectedSlot'    => sprintf('%02d:%02d PM', rand(1, 5), rand(10, 59)),
        ];

        $registrator = User::where('Username', 'registrator')->first();
        $this->actingAs($registrator);

        $res = $this->postJson('/api/registration/submit.php', $payload);
        $res->assertStatus(201);
        $res->assertJson(['success' => true]);
        $this->assertNotEmpty($res->json('patient_code'));
        $this->assertNotEmpty($res->json('appointment_id'));
    }

    /**
     * 17. Test Pharmacy Medicine Catalog Formulary CRUD (Issue 3)
     */
    public function test_pharmacy_medicine_catalog_crud_and_status_toggle(): void
    {
        $pharmacist = User::where('Username', 'pharmacist')->first();
        $this->actingAs($pharmacist);

        // 1. Add Medicine
        $addRes = $this->postJson('/pharmacy/api/catalog/add', [
            'generic_name'  => 'Azithromycin Di-hydrate',
            'brand_name'    => 'Zithromax',
            'category'      => 'Antibiotic',
            'dosage_form'   => 'Capsule',
            'strength'      => '250mg',
            'route'         => 'Oral',
            'initial_stock' => 150,
        ]);
        $addRes->assertStatus(200);
        $addRes->assertJson(['success' => true]);
        $medId = $addRes->json('data.med_id');
        $this->assertNotEmpty($medId);

        // 2. Update Medicine
        $updateRes = $this->postJson('/pharmacy/api/catalog/update', [
            'med_id'       => $medId,
            'generic_name' => 'Azithromycin Di-hydrate',
            'brand_name'   => 'Zithromax Forte',
            'category'     => 'Antibiotic',
            'dosage_form'  => 'Capsule',
            'strength'     => '500mg',
            'route'        => 'Oral',
            'status'       => 'Active',
        ]);
        $updateRes->assertStatus(200);
        $updateRes->assertJson(['success' => true]);

        // 3. Toggle Status (Deactivate / Reactivate)
        $toggleRes = $this->postJson('/pharmacy/api/catalog/toggle', [
            'med_id'       => $medId,
            'generic_name' => 'Azithromycin Di-hydrate',
        ]);
        $toggleRes->assertStatus(200);
        $toggleRes->assertJson(['success' => true, 'new_status' => 'Inactive']);

        // Clean up test entry
        \App\Models\PharmacyInventory::where('ItemCode', $medId)->delete();
    }

    /**
     * 18. Test Real Portal Logout (Issue 2)
     */
    public function test_portal_logout_redirection(): void
    {
        $medtech = User::where('Username', 'medtech')->first();
        $this->actingAs($medtech);
        $this->assertAuthenticated();

        $res = $this->get('/logout');
        $res->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * 19. Test Doctor & Nurse Portal Clean and Legacy Routes
     */
    public function test_doctor_and_nurse_clean_and_legacy_routes(): void
    {
        $doctor = User::where('Username', 'cardio')->first();
        $this->actingAs($doctor);

        // Clean routes should return 200 OK
        $this->get('/doctor')->assertStatus(200);
        $this->get('/doctor/patients')->assertStatus(200);
        $this->get('/doctor/diagnosis')->assertStatus(200);
        $this->get('/doctor/prescriptions')->assertStatus(200);
        $this->get('/doctor/laboratory')->assertStatus(200);
        $this->get('/doctor/referrals')->assertStatus(200);
        $this->get('/doctor/certificates')->assertStatus(200);
        $this->get('/doctor/settings')->assertStatus(200);

        // Legacy routes (e.g. /doctor/views/patients/index.php) must redirect cleanly without 404
        $this->get('/doctor/views/patients/index.php')->assertRedirect('/doctor/patients');
        $this->get('/doctor/views/dashboard/index.php')->assertRedirect('/doctor');
        $this->get('/doctor/views/diagnosis/index.php')->assertRedirect('/doctor/diagnosis');
        $this->get('/doctor/views/prescriptions/index.php')->assertRedirect('/doctor/prescriptions');
        $this->get('/doctor/views/laboratory/index.php')->assertRedirect('/doctor/laboratory');
        $this->get('/doctor/views/referrals/index.php')->assertRedirect('/doctor/referrals');
        $this->get('/doctor/views/certificates/index.php')->assertRedirect('/doctor/certificates');
        $this->get('/doctor/views/settings/index.php')->assertRedirect('/doctor/settings');

        // Nurse Portal
        $nurse = User::where('Username', 'nurse')->first();
        $this->actingAs($nurse);

        $this->get('/nurse')->assertStatus(200);
        $this->get('/nurse/vitals')->assertStatus(200);
        $this->get('/nurse/patients')->assertStatus(200);
        $this->get('/nurse/views/vitals/index.php')->assertRedirect('/nurse/vitals');
        $this->get('/nurse/views/patients/index.php')->assertRedirect('/nurse/patients');
    }
}


