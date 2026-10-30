<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Register\PatientRegistration;
use App\Services\Register\SymptomClassifier;
use App\Services\Register\Symptom;
use App\Services\Register\Doctor;
use App\Services\Register\Patient;
use App\Services\Register\Queue;
use App\Services\Register\Appointment;
use App\Services\Register\BodySystem;
use App\Services\Register\BodyLocation;
use Illuminate\Support\Facades\Auth;

class RegisterApiController extends Controller
{
    private function asTrimmedString(mixed $val): string
    {
        if (is_array($val)) {
            $first = reset($val);
            return is_scalar($first) ? trim((string) $first) : '';
        }
        return is_scalar($val) ? trim((string) $val) : '';
    }

    public function classify(Request $request)
    {
        $complaint = $this->asTrimmedString($request->input('complaint', ''));
        $symptoms = $request->input('symptoms', []);
        $locationCode = $this->asTrimmedString($request->input('bodyLocation')) ?: null;

        if ($complaint === '' && empty($symptoms)) {
            return response()->json([
                'success' => false,
                'message' => 'Complaint description or symptoms are required.'
            ], 400);
        }

        try {
            $classifier = new SymptomClassifier();
            $result = $classifier->classifyComplaint($complaint, (array) $symptoms, $locationCode);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => safe_error_message($e, 'Symptom triage classification failed.')
            ], 500);
        }
    }

    public function submitRegistration(Request $request)
    {
        $data = $request->isJson() ? $request->json()->all() : $request->all();

        $firstName = $this->asTrimmedString($data['personal']['firstName'] ?? $data['firstName'] ?? '');
        $lastName = $this->asTrimmedString($data['personal']['lastName'] ?? $data['lastName'] ?? '');
        $dob = $this->asTrimmedString($data['personal']['dob'] ?? $data['dob'] ?? '');
        $gender = $this->asTrimmedString($data['personal']['gender'] ?? $data['gender'] ?? '');
        $phone = $this->asTrimmedString($data['personal']['phone'] ?? $data['phone'] ?? '');
        $email = $this->asTrimmedString($data['personal']['email'] ?? $data['email'] ?? '');
        $address = $this->asTrimmedString($data['personal']['address'] ?? $data['address'] ?? '');
        $complaint = is_array($data['complaint'] ?? null)
            ? $this->asTrimmedString($data['complaint']['description'] ?? $data['complaint']['text'] ?? '')
            : $this->asTrimmedString($data['complaint'] ?? '');

        $errors = [];
        if ($firstName === '')
            $errors['firstName'] = 'First name is required.';
        if ($lastName === '')
            $errors['lastName'] = 'Last name is required.';
        if ($dob === '')
            $errors['dob'] = 'Date of birth is required.';
        if ($gender === '')
            $errors['gender'] = 'Gender is required.';
        if ($phone === '')
            $errors['phone'] = 'Contact number is required.';
        if ($address === '')
            $errors['address'] = 'Address is required.';
        if ($complaint === '')
            $errors['complaint'] = 'Chief complaint description is required.';

        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed on server.',
                'errors' => $errors
            ], 422);
        }

        $userId = session('user_id') ?: (Auth::id() ?: 1);

        try {
            $regService = new PatientRegistration();
            $result = $regService->registerCompletePatient($data, (int) $userId);
            $status = $result['success'] ? 201 : (!empty($result['duplicate_warning']) ? 409 : 400);
            return response()->json($result, $status);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => safe_error_message($e, 'Server encountered an error while processing patient registration.')
            ], 500);
        }
    }

    public function getSymptoms(Request $request)
    {
        try {
            $symptomService = new Symptom();
            $bodySystemService = new BodySystem();
            $bodyLocationService = new BodyLocation();

            $symptoms = $symptomService->getAll();
            $systems = $bodySystemService->getAll();
            $locations = $bodyLocationService->getAll();

            return response()->json([
                'success' => true,
                'symptoms' => $symptoms,
                'systems' => $systems,
                'locations' => $locations,
                'data' => $symptoms
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => safe_error_message($e, 'Error retrieving symptoms metadata.')
            ], 500);
        }
    }

    public function getDoctors(Request $request)
    {
        $doctorService = new Doctor();
        // Constrain to Dr. Alexander Chen only as required
        $doctors = $doctorService->getAll();

        return response()->json([
            'success' => true,
            'data' => $doctors
        ]);
    }

    public function getPatients(Request $request)
    {
        $search = trim($request->query('search', ''));
        $patientService = new Patient();
        $patients = $patientService->getPaginated(10, 0, $search);

        return response()->json([
            'success' => true,
            'data' => $patients
        ]);
    }

    public function getQueue(Request $request)
    {
        $queueService = new Queue();
        return response()->json([
            'success' => true,
            'now_serving' => $queueService->getNowServing(),
            'today_queue' => $queueService->getTodayQueue(),
            'stats' => $queueService->getQueueStats()
        ]);
    }

    public function callQueueNext(Request $request)
    {
        $queueId = (int) $request->input('queue_id');
        $queueService = new Queue();
        $result = $queueService->updateStatus($queueId, 'In Consultation');

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Patient called into consultation.' : 'Failed to update queue status.'
        ]);
    }
}
