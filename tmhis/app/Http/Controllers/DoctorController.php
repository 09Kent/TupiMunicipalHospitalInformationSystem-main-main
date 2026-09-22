<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

require_once app_path('Services/Doctor/Doctor.php');
require_once app_path('Services/Doctor/Patient.php');
require_once app_path('Services/Doctor/Consultation.php');
require_once app_path('Services/Doctor/Diagnosis.php');
if (file_exists(resource_path('views/doctor/includes/anatomy_model.php'))) {
    require_once resource_path('views/doctor/includes/anatomy_model.php');
}

class DoctorController extends Controller
{
    private function resolveDoctor()
    {
        $user = auth()->user();
        $doctorId = session('doctor_id');
        if (!$doctorId && $user) {
            $doctorRecord = \App\Models\Doctor::where('UserID', $user->UserID)->first();
            if (!$doctorRecord && !empty($user->Email)) {
                $doctorRecord = \App\Models\Doctor::where('Email', $user->Email)->first();
            }
            if (!$doctorRecord) {
                // For Admin or unlinked physician accounts, fallback to primary active doctor
                $doctorRecord = \App\Models\Doctor::where('Status', 'Active')->first() ?? \App\Models\Doctor::first();
            }
            if ($doctorRecord) {
                $doctorId = (int)$doctorRecord->DoctorID;
                $specialtyName = $doctorRecord->Specialty ?? 'Cardiologist';
                session([
                    'doctor_id' => $doctorId,
                    'specialty' => $specialtyName,
                    'specialty_id' => (int)($doctorRecord->SpecialtyID ?? 2),
                ]);
            }
        }

        if ($user) {
            $_SESSION['user_id'] = $user->UserID;
            $_SESSION['doctor_id'] = $doctorId;
            $_SESSION['role'] = 'Doctor';
            $_SESSION['username'] = $user->Username;
            $_SESSION['full_name'] = $user->FullName;
            $_SESSION['specialty'] = session('specialty', 'Cardiologist');
        }

        return $doctorId;
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
        } elseif (str_contains($file, 'print')) {
            $sub = 'print';
        }

        if ($request->isMethod('get')) {
            $cleanUrl = match($module) {
                'dashboard' => '/doctor',
                'patients' => ($sub === 'view') ? '/doctor/patients/view' : '/doctor/patients',
                'diagnosis' => '/doctor/diagnosis',
                'prescriptions' => ($sub === 'print') ? '/doctor/prescriptions/print' : '/doctor/prescriptions',
                'laboratory' => '/doctor/laboratory',
                'referrals' => '/doctor/referrals',
                'certificates' => ($sub === 'print') ? '/doctor/certificates/print' : '/doctor/certificates',
                'settings' => '/doctor/settings',
                default => '/doctor'
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
        $doctorId = $this->resolveDoctor();

        if (!$doctorId) {
            return redirect()->route('admin.dashboard')->with('error', 'No active physician profile found in the system.');
        }

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
            'dashboard' => resource_path('views/doctor/dashboard/index.php'),
            'patients' => ($sub === 'view' || $request->has('id')) ? resource_path('views/doctor/patients/view.php') : resource_path('views/doctor/patients/index.php'),
            'diagnosis' => resource_path('views/doctor/diagnosis/index.php'),
            'prescriptions' => ($sub === 'print') ? resource_path('views/doctor/prescriptions/print.php') : resource_path('views/doctor/prescriptions/index.php'),
            'laboratory' => resource_path('views/doctor/laboratory/index.php'),
            'referrals' => resource_path('views/doctor/referrals/index.php'),
            'certificates' => ($sub === 'print') ? resource_path('views/doctor/certificates/print.php') : resource_path('views/doctor/certificates/index.php'),
            'settings' => resource_path('views/doctor/settings/index.php'),
            default => resource_path('views/doctor/dashboard/index.php')
        };

        if (!file_exists($targetFile)) {
            abort(404, "Doctor page '$page' not found.");
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
}
