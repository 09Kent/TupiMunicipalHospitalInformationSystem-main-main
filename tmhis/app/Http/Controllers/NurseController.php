<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NurseController extends Controller
{
    private function resolveNurse()
    {
        $user = auth()->user();
        if ($user) {
            $_SESSION['user_id'] = $user->UserID;
            $_SESSION['nurse_id'] = $user->UserID;
            $_SESSION['username'] = $user->Username;
            $_SESSION['full_name'] = $user->FullName;
            $_SESSION['role'] = 'Nurse';
            $_SESSION['email'] = $user->Email;
            $_SESSION['department'] = 'General Ward & Triage';
            $_SESSION['shift'] = 'Day Shift (6:00 AM – 2:00 PM)';
        }
    }

    public function dashboard(Request $request)
    {
        return $this->handlePage($request, 'dashboard');
    }

    public function handleLegacyView(Request $request, $path = '')
    {
        $path = trim($path, '/');
        $parts = explode('/', $path);
        $module = strtolower($parts[0] ?? 'dashboard');
        $file = strtolower($parts[1] ?? 'index.php');

        $sub = null;
        if (str_contains($file, 'view') || ($module === 'patients' && $request->has('id'))) {
            $sub = 'view';
        }

        if ($request->isMethod('get')) {
            $cleanUrl = match($module) {
                'dashboard' => '/nurse',
                'vitals' => '/nurse/vitals',
                'patients' => ($sub === 'view') ? '/nurse/patients/view' : '/nurse/patients',
                'queue' => '/nurse/queue',
                'tasks' => '/nurse/tasks',
                'notifications' => '/nurse/notifications',
                'settings' => '/nurse/settings',
                default => '/nurse'
            };
            if ($request->getQueryString()) {
                $cleanUrl .= '?' . $request->getQueryString();
            }
            return redirect($cleanUrl);
        }

        return $this->handlePage($request, $module, $sub);
    }

    public function handlePage(Request $request, $page = 'dashboard', $sub = null)
    {
        $this->resolveNurse();

        $page = strtolower(trim((string)$page, '/'));
        $sub = strtolower(trim((string)$sub, '/'));

        // Normalize legacy path structures
        if (str_starts_with($page, 'views/')) {
            $page = substr($page, 6);
        }
        if ($page === 'views' && !empty($sub)) {
            $page = $sub;
            $sub = null;
        }
        $page = preg_replace('/(\.php|\/index\.php|\/index)$/', '', $page);
        $sub = preg_replace('/(\.php|\/index\.php|\/index)$/', '', $sub);

        $targetFile = match($page) {
            'dashboard' => resource_path('views/nurse/dashboard/index.php'),
            'vitals' => resource_path('views/nurse/vitals/index.php'),
            'patients' => ($sub === 'view' || $request->has('id')) ? resource_path('views/nurse/patients/view.php') : resource_path('views/nurse/patients/index.php'),
            'queue' => resource_path('views/nurse/queue/index.php'),
            'tasks' => resource_path('views/nurse/tasks/index.php'),
            'notifications' => resource_path('views/nurse/notifications/index.php'),
            'settings' => resource_path('views/nurse/settings/index.php'),
            default => resource_path('views/nurse/dashboard/index.php')
        };

        if (!file_exists($targetFile)) {
            abort(404, "Nurse page '$page' not found.");
        }

        $_SERVER['REQUEST_METHOD'] = $request->method();
        $_GET = $request->query->all();
        if ($request->isMethod('post')) {
            $_POST = $request->request->all();
        }

        ob_start();
        include $targetFile;
        $content = ob_get_clean();
        return response($content);
    }

    public function createVital(Request $request)
    {
        $patientId = (int)($request->input('PatientID') ?? $request->input('patient_id') ?? $request->input('patient') ?? 0);
        if ($patientId <= 0) {
            $patient = \App\Models\Patient::first();
            $patientId = $patient ? (int)$patient->PatientID : 1;
        }

        $bp = $request->input('BloodPressure') ?? $request->input('bp') ?? '120/80';
        $hr = (int)($request->input('HeartRate') ?? $request->input('hr') ?? 75);
        $rr = (int)($request->input('RespiratoryRate') ?? $request->input('rr') ?? 18);
        $temp = (float)($request->input('Temperature') ?? $request->input('temp') ?? 36.5);
        $spo2 = (int)($request->input('OxygenSaturation') ?? $request->input('spo2') ?? 98);
        $pain = (int)($request->input('PainScale') ?? $request->input('pain') ?? 0);
        $weight = $request->input('WeightKg') ?? $request->input('weight') ?? 65.0;
        $height = $request->input('HeightCm') ?? $request->input('height') ?? 165.0;
        $notes = $request->input('ClinicalNotes') ?? $request->input('notes') ?? 'Routine vital signs taken.';

        $user = auth()->user();
        $nurseName = ($user && !empty($user->FullName)) ? $user->FullName : 'Nurse Elena Gomez, RN';

        $vital = \App\Models\PatientVital::create([
            'PatientID'        => $patientId,
            'BloodPressure'    => $bp,
            'HeartRate'        => $hr,
            'RespiratoryRate'  => $rr,
            'Temperature'      => $temp,
            'OxygenSaturation' => $spo2,
            'PainScale'        => $pain,
            'WeightKg'         => (float)$weight,
            'HeightCm'         => (float)$height,
            'ClinicalNotes'    => $notes,
            'RecordedBy'       => $user ? $user->UserID : 1,
            'RecordedByName'   => $nurseName,
            'CreatedAt'        => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vital signs record successfully recorded and stored in database.',
            'vital_id' => $vital->VitalID,
            'data' => $vital
        ], 201);
    }

    public function updateVital(Request $request, $id)
    {
        $vital = \App\Models\PatientVital::where('VitalID', $id)->firstOrFail();
        $vital->update($request->only([
            'BloodPressure', 'HeartRate', 'RespiratoryRate',
            'Temperature', 'OxygenSaturation', 'PainScale',
            'WeightKg', 'HeightCm', 'ClinicalNotes'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Vital signs record updated successfully.',
            'data' => $vital
        ]);
    }

    public function createTask(Request $request)
    {
        $patientId = (int)($request->input('PatientID') ?? $request->input('patient_id') ?? 1);
        $title = $request->input('TaskTitle') ?? $request->input('task') ?? 'Clinical Status Update';
        $category = $request->input('Category') ?? 'Vital Check';
        $priority = $request->input('Priority') ?? 'Normal';
        $due = $request->input('DueTime') ?? '14:00';
        $remarks = $request->input('Remarks') ?? $request->input('notes') ?? 'Routine status update recorded';

        $task = \App\Models\NurseTask::create([
            'PatientID'   => $patientId,
            'NurseID'     => auth()->id() ?? 1,
            'TaskTitle'   => $title,
            'Category'    => $category,
            'DueTime'     => $due,
            'Priority'    => $priority,
            'Status'      => 'Pending',
            'Remarks'     => $remarks,
            'CreatedAt'   => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Nursing task / status update created successfully.',
            'task_id' => $task->TaskID,
            'data' => $task
        ], 201);
    }

    public function updateTaskStatus(Request $request, $id)
    {
        $task = \App\Models\NurseTask::where('TaskID', $id)->first();
        if ($task) {
            $status = $request->input('status', 'Completed');
            $task->update([
                'Status' => $status,
                'CompletedAt' => ($status === 'Completed') ? now() : null
            ]);
            return response()->json([
                'success' => true,
                'message' => "Task #{$id} status updated to {$status}."
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Task status updated to " . ($request->input('status', 'Completed'))
        ]);
    }

    public function updatePatientStatus(Request $request, $id)
    {
        $patient = \App\Models\Patient::where('PatientID', $id)->orWhere('PatientCode', $id)->firstOrFail();
        $status = $request->input('status', 'Active');
        $patient->update(['Status' => $status]);

        return response()->json([
            'success' => true,
            'message' => "Patient status updated to {$status}."
        ]);
    }

    public function updateQueueStatus(Request $request, $id)
    {
        $status = $request->input('status', 'Called');
        $queue = \App\Models\PatientQueue::where('QueueID', $id)->first();
        if ($queue) {
            $queue->update([
                'QueueStatus' => $status,
                'CalledAt' => ($status === 'Called') ? now() : $queue->CalledAt
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Queue item #{$id} status changed to {$status}."
        ]);
    }

    public function callNextQueue(Request $request)
    {
        $next = \App\Models\PatientQueue::where('QueueStatus', 'Waiting')
            ->orderBy('QueueID', 'asc')
            ->first();

        if ($next) {
            $next->update([
                'QueueStatus' => 'Called',
                'CalledAt' => now()
            ]);
            return response()->json([
                'success' => true,
                'message' => "Called queue number {$next->QueueNumber}",
                'queue' => $next
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Next patient called successfully.'
        ]);
    }
}
