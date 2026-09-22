<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Register\Report;
use App\Services\Register\Patient;
use App\Services\Register\Appointment;
use App\Services\Register\Queue;
use App\Services\Register\Doctor;
use App\Services\Register\BodyLocation;
use App\Services\Register\BodySystem;
use App\Services\Register\Symptom;
use App\Services\Register\SymptomClassifier;
use App\Services\Register\PatientRegistration;

class RegisterController extends Controller
{
    protected Report $reportService;
    protected Patient $patientService;
    protected Appointment $appointmentService;
    protected Queue $queueService;
    protected Doctor $doctorService;

    public function __construct()
    {
        $this->reportService = new Report();
        $this->patientService = new Patient();
        $this->appointmentService = new Appointment();
        $this->queueService = new Queue();
        $this->doctorService = new Doctor();
    }

    public function dashboard()
    {
        $kpis = $this->reportService->getDashboardKPIs();
        $recentPatients = $this->patientService->getPaginated(6, 0);
        $todayAppointments = $this->appointmentService->getTodayAppointments();
        $nowServing = $this->queueService->getNowServing();
        $todayQueue = $this->queueService->getTodayQueue();

        $user = [
            'name' => session('full_name', auth()->user()->FirstName ?? 'Registrator'),
        ];

        return view('register.dashboard', compact(
            'kpis',
            'recentPatients',
            'todayAppointments',
            'nowServing',
            'todayQueue',
            'user'
        ));
    }

    public function patients(Request $request)
    {
        $search = trim($request->query('search', ''));
        $category = trim($request->query('category', ''));
        $status = trim($request->query('status', ''));
        $page = max(1, (int)$request->query('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $patients = $this->patientService->getPaginated($limit, $offset, $search, $category, $status);
        $totalPatients = $this->patientService->countTotal($search, $category, $status);
        $totalPages = max(1, ceil($totalPatients / $limit));

        return view('register.patients.index', compact(
            'patients',
            'totalPatients',
            'totalPages',
            'page',
            'search',
            'category',
            'status',
            'limit',
            'offset'
        ));
    }

    public function viewPatient($id)
    {
        $patient = $this->patientService->getFullProfile((int)$id);
        if (!$patient) {
            abort(404, 'Patient record not found.');
        }

        return view('register.patients.view', compact('patient'));
    }

    public function editPatient($id)
    {
        $patient = $this->patientService->getFullProfile((int)$id);
        if (!$patient) {
            abort(404, 'Patient record not found.');
        }

        return view('register.patients.edit', compact('patient'));
    }

    public function updatePatient(Request $request, $id)
    {
        $input = $request->all();
        if (isset($input['DateOfBirth']) && !isset($input['BirthDate'])) {
            $input['BirthDate'] = $input['DateOfBirth'];
        } elseif (isset($input['BirthDate']) && !isset($input['DateOfBirth'])) {
            $input['DateOfBirth'] = $input['BirthDate'];
        }

        $validator = \Illuminate\Support\Facades\Validator::make($input, [
            'FirstName'       => 'required|string|max:100',
            'LastName'        => 'required|string|max:100',
            'BirthDate'       => 'nullable|date',
            'DateOfBirth'     => 'nullable|date',
            'Gender'          => 'nullable|in:Male,Female,Other',
            'ContactNumber'   => 'nullable|string|max:20',
            'Address'         => 'nullable|string|max:255',
            'PatientCategory' => 'nullable|in:Outpatient,Emergency,Admitted,Inpatient',
            'CivilStatus'     => 'nullable|string|max:50',
            'BloodType'       => 'nullable|string|max:10',
            'Status'          => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $data = array_filter($validator->validated(), fn($v) => !is_null($v));
        $updated = $this->patientService->update((int)$id, $data);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => (bool)$updated, 'message' => $updated ? 'Patient record updated successfully.' : 'Failed to update patient record.']);
        }

        if ($updated) {
            return redirect()->route('register.patients.view', $id)->with('success', 'Patient record updated successfully.');
        }

        return back()->with('error', 'Failed to update patient record.');
    }

    public function appointments(Request $request)
    {
        $date = $request->query('date', date('Y-m-d'));
        $status = $request->query('status', '');
        $doctorId = (int)$request->query('doctor_id', 0);
        $doctor = $request->query('doctor', $doctorId);
        $docParam = $doctor ? (string)$doctor : '';

        $page = max(1, (int)$request->query('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $appointments = $this->appointmentService->getPaginated($limit, $offset, $date, $docParam, $status);
        $totalAppointments = $this->appointmentService->countTotal($date, $docParam, $status);
        $totalPages = max(1, ceil($totalAppointments / $limit));

        $doctors = $this->doctorService->getAll();
        $allDoctors = $doctors;
        $summary = $this->appointmentService->getDailySummary($date);

        return view('register.appointments.index', compact(
            'appointments',
            'doctors',
            'allDoctors',
            'summary',
            'date',
            'status',
            'doctorId',
            'doctor',
            'totalAppointments',
            'page',
            'totalPages',
            'limit',
            'offset'
        ));
    }

    public function queue(Request $request)
    {
        $nowServing = $this->queueService->getNowServing();
        $todayQueue = $this->queueService->getTodayQueue();
        $queueStats = $this->queueService->getQueueStats();
        $doctors = $this->doctorService->getAll();

        return view('register.queue.index', compact(
            'nowServing',
            'todayQueue',
            'queueStats',
            'doctors'
        ));
    }

    public function registration()
    {
        $dbSymptoms = (new Symptom())->getAll();
        $dbSystems = (new BodySystem())->getAll();
        $dbLocations = (new BodyLocation())->getAll();
        $dbDoctors = $this->doctorService->getAll();

        return view('register.registration.index', compact(
            'dbSymptoms',
            'dbSystems',
            'dbLocations',
            'dbDoctors'
        ));
    }

    public function reports(Request $request)
    {
        $kpis = $this->reportService->getDashboardKPIs();
        $trend = $this->reportService->getRegistrationTrend();
        $systems = $this->reportService->getBodySystemDistribution();
        $categories = $this->reportService->getPatientCategories();
        $consultations = $this->reportService->getConsultationStats();
        $workload = $this->reportService->getDoctorWorkload();

        return view('register.reports.index', compact(
            'kpis',
            'trend',
            'systems',
            'categories',
            'consultations',
            'workload'
        ));
    }

    public function rescheduleAppointment(Request $request, $id)
    {
        $date = $request->input('date', date('Y-m-d'));
        $time = $request->input('time', '09:00 AM');
        $doctorId = (int)$request->input('doctor_id', 0);
        $reason = $request->input('reason', 'Patient requested reschedule');
        $userId = auth()->id() ?: 1;

        $success = $this->appointmentService->reschedule((int)$id, $date, $time, $doctorId ?: null, $reason, $userId);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => $success, 'message' => $success ? 'Appointment rescheduled successfully.' : 'Failed to reschedule appointment.']);
        }

        return redirect()->back()->with($success ? 'success' : 'error', $success ? 'Appointment rescheduled successfully.' : 'Failed to reschedule appointment.');
    }

    public function cancelAppointment(Request $request, $id)
    {
        $userId = auth()->id() ?: 1;
        $success = $this->appointmentService->updateStatus((int)$id, 'Cancelled', $userId);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => $success, 'message' => $success ? 'Appointment cancelled.' : 'Failed to cancel appointment.']);
        }

        return redirect()->back()->with($success ? 'success' : 'error', $success ? 'Appointment cancelled.' : 'Failed to cancel appointment.');
    }
}
