<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Patient;
use App\Models\PatientVital;
use App\Models\NurseTask;
use App\Models\PatientQueue;

class NurseTest extends TestCase
{
    private function getNurseUser(): User
    {
        $user = User::where('Role', 'Nurse')->first();
        if (!$user) {
            $user = User::where('Username', 'nurse')->first();
        }
        return $user;
    }

    public function test_nurse_pages_load_successfully(): void
    {
        $nurse = $this->getNurseUser();
        $this->actingAs($nurse)->get('/nurse')->assertStatus(200);
        $this->actingAs($nurse)->get('/nurse/vitals')->assertStatus(200);
        $this->actingAs($nurse)->get('/nurse/tasks')->assertStatus(200);
        $this->actingAs($nurse)->get('/nurse/queue')->assertStatus(200);
    }

    public function test_nurse_vital_recording_and_task_management(): void
    {
        $nurse = $this->getNurseUser();
        $patient = Patient::first();
        $this->assertNotNull($patient);

        // 1. Create Vital Entry
        $vitalRes = $this->actingAs($nurse)->postJson('/nurse/api/vitals', [
            'PatientID'        => $patient->PatientID,
            'BloodPressure'    => '130/85',
            'HeartRate'        => 82,
            'RespiratoryRate'  => 19,
            'Temperature'      => 37.2,
            'OxygenSaturation' => 97,
            'PainScale'        => 2,
            'ClinicalNotes'    => 'Post-op observation vitals'
        ]);
        $vitalRes->assertStatus(201);
        $vitalId = $vitalRes->json('vital_id');
        $this->assertNotNull($vitalId);
        $this->assertDatabaseHas('patient_vitals', [
            'VitalID' => $vitalId,
            'BloodPressure' => '130/85'
        ]);

        // 2. Create Nursing Task
        $taskRes = $this->actingAs($nurse)->postJson('/nurse/api/tasks/create', [
            'PatientID' => $patient->PatientID,
            'TaskTitle' => 'Administer IV Ceftriaxone',
            'Category'  => 'Urgent Care',
            'Priority'  => 'High',
            'DueTime'   => '15:00',
            'Remarks'   => 'Reconstitute with 10ml sterile water'
        ]);
        $taskRes->assertStatus(201);
        $taskId = $taskRes->json('task_id');
        $this->assertNotNull($taskId);

        // 3. Update Task Status
        $updateTaskRes = $this->actingAs($nurse)->postJson("/nurse/api/tasks/{$taskId}/status", [
            'status' => 'Completed'
        ]);
        $updateTaskRes->assertStatus(200);
        $this->assertDatabaseHas('nurse_tasks', [
            'TaskID' => $taskId,
            'Status' => 'Completed'
        ]);

        // 4. Call Next in Queue
        $queueItem = PatientQueue::first();
        if ($queueItem) {
            $queueRes = $this->actingAs($nurse)->postJson('/nurse/api/queue/call');
            $queueRes->assertStatus(200);
        }
    }
}
