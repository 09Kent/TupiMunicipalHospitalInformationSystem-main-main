<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Patient;
use App\Models\LaboratoryRequest;
use App\Models\LaboratorySample;
use App\Models\LaboratoryResult;
use App\Models\TestCatalog;

class MedTechTest extends TestCase
{
    protected $medtechUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->medtechUser = User::where('Role', 'MedTech')->first();
        if (!$this->medtechUser) {
            $this->medtechUser = User::where('Email', 'medtech_test@hospital.gov.ph')->first();
        }
        if (!$this->medtechUser) {
            $this->medtechUser = User::create([
                'FirstName' => 'Clarisse Mae',
                'LastName' => 'Santos',
                'Username' => 'medtech_clarisse',
                'Email' => 'medtech_test@hospital.gov.ph',
                'PasswordHash' => bcrypt('password'),
                'Role' => 'MedTech',
                'Status' => 'Active',
            ]);
        }
    }

    public function test_medtech_dashboard_and_report_load_successfully()
    {
        $response = $this->actingAs($this->medtechUser)->get('/medtech');
        $response->assertStatus(200);

        $printResponse = $this->actingAs($this->medtechUser)->get('/medtech/report/1/print');
        $printResponse->assertStatus(200);
        $printResponse->assertSee('Tupi Municipal Hospital');
        $printResponse->assertSee('Official Diagnostic Report');
    }

    public function test_medtech_sample_and_result_workflow()
    {
        $patient = Patient::first();
        if (!$patient) {
            $patient = Patient::create([
                'PatientCode' => 'PAT-LABTEST-01',
                'FirstName' => 'Clara',
                'LastName' => 'Ocampo',
                'Email' => 'clara.ocampo@gmail.com',
                'Address' => 'Poblacion, Tupi',
                'DateOfBirth' => '1990-05-15',
                'Age' => 36,
                'Gender' => 'Female',
                'ContactNumber' => '09170009999',
                'Status' => 'Active',
            ]);
        }

        $labReq = LaboratoryRequest::firstOrCreate(
            ['RequestCode' => 'LAB-TEST-001'],
            [
                'PatientID' => $patient->PatientID,
                'DoctorID' => 1,
                'TestType' => 'Complete Blood Count (CBC)',
                'Priority' => 'STAT',
                'Status' => 'Pending',
                'RequestedDate' => now()->toDateString(),
            ]
        );

        // 1. Update Request Status
        $resReq = $this->actingAs($this->medtechUser)->postJson("/medtech/api/requests/{$labReq->RequestID}/status", [
            'status' => 'Received',
            'remarks' => 'Specimen received in diagnostic bay'
        ]);
        $resReq->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('laboratory_requests', [
            'RequestID' => $labReq->RequestID,
            'Status' => 'Received'
        ]);

        // 2. Create Sample Record
        $resSample = $this->actingAs($this->medtechUser)->postJson('/medtech/api/samples/create', [
            'request_id' => $labReq->RequestID,
            'patient_id' => $patient->PatientID,
            'specimen_type' => 'Whole Blood (EDTA)',
            'collected_by' => 'Nurse On Duty, RN',
            'notes' => 'Clean venipuncture right antecubital'
        ]);
        $resSample->assertStatus(200)->assertJson(['success' => true]);
        $sampleId = $resSample->json('data.SampleID');

        // 3. Update Sample Status
        $resSampStat = $this->actingAs($this->medtechUser)->postJson("/medtech/api/samples/{$sampleId}/status", [
            'status' => 'Processing'
        ]);
        $resSampStat->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('laboratory_samples', [
            'SampleID' => $sampleId,
            'ProcessingStatus' => 'Processing'
        ]);

        // 4. Create Laboratory Test Result
        $resResult = $this->actingAs($this->medtechUser)->postJson('/medtech/api/results/create', [
            'request_id' => $labReq->RequestID,
            'test_name' => 'Hemoglobin',
            'result_value' => '14.5',
            'normal_range' => '12.0 - 15.5',
            'units' => 'g/dL',
            'interpretation' => 'Normal',
            'notes' => 'Analyzed on Sysmex automated analyzer.'
        ]);
        $resResult->assertStatus(200)->assertJson(['success' => true]);
        $resultId = $resResult->json('data.ResultID');

        // 5. Update Laboratory Result
        $resUpdate = $this->actingAs($this->medtechUser)->postJson("/medtech/api/results/{$resultId}/update", [
            'result_value' => '14.6',
            'notes' => 'Verified on repeat run.'
        ]);
        $resUpdate->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('laboratory_results', [
            'ResultID' => $resultId,
            'ResultValue' => '14.6'
        ]);

        // 6. Test Catalog Management
        $resCat = $this->actingAs($this->medtechUser)->postJson('/medtech/api/catalog/create', [
            'test_name' => 'D-Dimer Quantitative Assay',
            'category' => 'Hematology',
            'specimen_type' => 'Citrated Plasma',
            'turnaround_time' => '1 Hour',
            'standard_price' => 750.00
        ]);
        $resCat->assertStatus(200)->assertJson(['success' => true]);
        $catalogId = $resCat->json('data.CatalogID');

        $resToggle = $this->actingAs($this->medtechUser)->postJson("/medtech/api/catalog/{$catalogId}/toggle");
        $resToggle->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('test_catalog', [
            'CatalogID' => $catalogId,
            'Status' => 'Inactive'
        ]);
    }
}
