<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Patient;
use App\Models\RecordReleaseRequest;

class RecordsOfficerTest extends TestCase
{
    private function getRecordsUser(): User
    {
        $user = User::where('Role', 'Records')->first();
        if (!$user) {
            $user = User::where('Username', 'records_officer')->first();
        }
        if (!$user) {
            $user = User::create([
                'Username' => 'records_officer',
                'Email' => 'records@tmhis.gov.ph',
                'Password' => bcrypt('password'),
                'FullName' => 'Mark Anthony Valenzuela',
                'Role' => 'Records',
                'Status' => 'Active',
            ]);
        }
        return $user;
    }

    public function test_records_officer_dashboard_loads_live_data(): void
    {
        $user = $this->getRecordsUser();
        $res = $this->actingAs($user)->get('/records');
        $res->assertStatus(200);
        $res->assertViewHas('serverData');
        $res->assertSee('window.SERVER_RECORDS_DATA', false);
    }

    public function test_create_and_verify_and_archive_patient(): void
    {
        $user = $this->getRecordsUser();

        // 1. Create Patient
        $createRes = $this->actingAs($user)->postJson('/records/api/patients', [
            'FirstName' => 'TestPatient',
            'LastName' => 'FddAudit',
            'DateOfBirth' => '1995-03-20',
            'Gender' => 'Female',
            'ContactNumber' => '0912-345-6789',
            'Address' => 'Poblacion, Tupi, South Cotabato',
            'PatientCategory' => 'Outpatient'
        ]);
        $createRes->assertStatus(201);
        $patientId = $createRes->json('patient.PatientID');
        $this->assertNotNull($patientId);

        // 2. Verify Patient
        $verifyRes = $this->actingAs($user)->postJson("/records/api/patients/{$patientId}/verify");
        $verifyRes->assertStatus(200);
        $verifyRes->assertJsonFragment(['success' => true]);

        // 3. Archive Patient
        $archiveRes = $this->actingAs($user)->postJson("/records/api/patients/{$patientId}/archive", [
            'reason' => 'Audit test archive'
        ]);
        $archiveRes->assertStatus(200);
        $this->assertDatabaseHas('patients', [
            'PatientID' => $patientId,
            'Status' => 'Archived'
        ]);

        // 4. View Official Summary
        $summaryRes = $this->actingAs($user)->get("/records/summary/{$patientId}");
        $summaryRes->assertStatus(200);
        $summaryRes->assertSee('TestPatient');
        $summaryRes->assertSee('FddAudit');
    }
}
