<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->has('logout') || $request->has('switch')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION = [];
            }
        }

        if ($request->has('continue') && Auth::check()) {
            return $this->redirectUser(Auth::user());
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $input = trim($request->input('email', ''));
        $password = $request->input('password', '');

        if (empty($input) || empty($password)) {
            return back()->withInput()->with('error', 'Please provide your email/username and password.');
        }

        // Match user by Email or Username (case-insensitive)
        $user = User::where(function ($query) use ($input) {
            $query->where('Email', $input)
                  ->orWhere('Username', $input);
        })->where('Status', 'Active')->first();

        $userHash = $user?->PasswordHash ?? '';
        $hasValidBcryptHash = is_string($userHash) && str_starts_with($userHash, '$2') && strlen($userHash) === 60;
        $passwordMatches = false;

        if ($user) {
            if ($hasValidBcryptHash) {
                $passwordMatches = Hash::check($password, $userHash);
            }

            if (!$passwordMatches) {
                $passwordMatches = password_verify($password, $userHash);
            }
        }

        if ($user && $passwordMatches) {
            // Cleanly flush previous session to prevent role and data cross-contamination
            $request->session()->flush();
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION = [];
            }

            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }

            // Populate clean session variables for the authenticated user
            session([
                'user_id' => (int)$user->UserID,
                'username' => $user->Username,
                'full_name' => $user->Role === 'Doctor' ? 'Dr. ' . $user->FirstName . ' ' . $user->LastName : $user->FirstName . ' ' . $user->LastName,
                'role' => $user->Role,
                'email' => $user->Email,
            ]);

            // If doctor, load doctor-specific session variables
            $doctor = Doctor::where('UserID', $user->UserID)->first();
            if (!$doctor && (strtolower($user->Role ?? '') === 'doctor' || strtolower($user->Username ?? '') === 'cardio')) {
                $doctor = Doctor::where('Status', 'Active')->first() ?? Doctor::first();
            }
            if ($doctor) {
                $specialty = Specialty::find($doctor->SpecialtyID);
                session([
                    'doctor_id' => (int)$doctor->DoctorID,
                    'specialty' => $specialty->SpecialtyName ?? ($doctor->Specialty ?: 'General Practitioner'),
                    'specialty_id' => (int)$doctor->SpecialtyID,
                    'body_system_id' => (int)($specialty->BodySystemID ?? 0),
                    'license_number' => $doctor->LicenseNumber,
                    'profile_image' => $doctor->ProfileImage,
                    'clinic' => ($doctor->Clinic ?? '') . ($doctor->ClinicRoom ? ' (' . $doctor->ClinicRoom . ')' : ''),
                ]);
            }

            // Clear any stale intended URLs so each account routes strictly to its accurate dashboard
            $request->session()->forget('url.intended');

            return $this->redirectUser($user);
        }

        return back()->withInput()->with('error', 'Invalid credentials. Please check your email and password.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }

        return redirect()->route('login')->with('success', 'You have been logged out successfully.');
    }

    public function redirectUser(User $user)
    {
        $username = strtolower(trim($user->Username ?? ''));
        $role = strtolower(trim($user->Role ?? ''));

        $legacyDoctorAliases = ['cardio', 'doctor', 'dr.chen', 'dr.flores', 'dr.lewis', 'dr.villanueva', 'dr.wilson', 'dr.miller'];
        $legacyChiefAliases = ['chief', 'director', 'cmo', 'chief.medical'];
        $legacyRecordsAliases = ['records', 'records2', 'records_officer', 'records.officer'];
        $legacyRegisterAliases = ['register', 'registrator', 'registrar.main', 'registrar', 'register1'];
        $legacyNurseAliases = ['nurse', 'staff.reyes', 'staff.bautista', 'duty.nurse'];
        $legacyMedtechAliases = ['medtech', 'medtech2', 'medtech.supervisor'];
        $legacyPharmacyAliases = ['pharmacist', 'pharmacy', 'pharmacy.supervisor'];
        $legacyBillingAliases = ['billing', 'cashier', 'accountant', 'cashier2', 'cashier.supervisor'];

        $isDoctorAccount = $role === 'doctor' || in_array($username, $legacyDoctorAliases, true) || Doctor::where('UserID', $user->UserID)->exists();
        $isChiefAccount = in_array($role, ['chief', 'director', 'cmo']) || in_array($username, $legacyChiefAliases, true);
        $isRecordsAccount = in_array($role, ['records', 'mro']) || in_array($username, $legacyRecordsAliases, true);
        $isRegisterAccount = in_array($role, ['register', 'registrator']) || in_array($username, $legacyRegisterAliases, true);
        $isNurseAccount = in_array($role, ['nurse']) || in_array($username, $legacyNurseAliases, true);
        $isMedtechAccount = in_array($role, ['medtech']) || in_array($username, $legacyMedtechAliases, true);
        $isPharmacyAccount = in_array($role, ['pharmacist', 'pharmacy']) || in_array($username, $legacyPharmacyAliases, true);
        $isBillingAccount = in_array($role, ['billing', 'cashier', 'accountant']) || in_array($username, $legacyBillingAliases, true);

        // 1. Hospital Chief / Medical Director / Chief Medical Officer
        if ($isChiefAccount) {
            return redirect()->route('director.dashboard');
        }

        // 2. Nurse on Duty
        if ($isNurseAccount || str_contains($username, 'nurse')) {
            return redirect()->route('nurse.dashboard');
        }

        // 3. System Administrator
        if ($role === 'admin' || $username === 'admin' || $username === 'admin.official') {
            return redirect()->route('admin.dashboard');
        }

        // 4. Medical Records Officer
        if ($isRecordsAccount) {
            return redirect()->route('records.dashboard');
        }

        // 5. Admitting / Registration Staff
        if ($isRegisterAccount || str_contains($username, 'registrator') || str_contains($username, 'register')) {
            return redirect()->route('register.dashboard');
        }

        // 6. Attending Physician / Doctor
        if ($isDoctorAccount) {
            if (!session('doctor_id')) {
                $doc = Doctor::where('UserID', $user->UserID)->first() ?? Doctor::where('Status', 'Active')->first() ?? Doctor::first();
                if ($doc) {
                    session(['doctor_id' => (int)$doc->DoctorID]);
                }
            }
            return redirect()->route('doctor.dashboard');
        }

        // 7. Medical Technologist
        if ($isMedtechAccount || str_contains($username, 'medtech')) {
            return redirect()->route('medtech.dashboard');
        }

        // 8. Pharmacist
        if ($isPharmacyAccount || str_contains($username, 'pharmacist')) {
            return redirect()->route('pharmacy.dashboard');
        }

        // 9. Billing / Cashier
        if ($isBillingAccount || str_contains($username, 'cashier') || str_contains($username, 'billing')) {
            return redirect()->route('billing.dashboard');
        }

        return redirect()->route('doctor.dashboard');
    }
}
