<?php
/**
 * Tupi Municipal Hospital Information Management System
 * Global Legacy Compatibility Bridge
 */

namespace TMHIS {
    use PDO;

    if (!class_exists('TMHIS\Database', false)) {
        class Database
        {
            public static function getConnection(): PDO
            {
                return \Database::getConnection();
            }
        }
    }
}

namespace {

    require_once __DIR__ . '/PgsqlCompatPdo.php';

    if (!class_exists('Database', false)) {
        class Database
        {
            private static ?PDO $instance = null;

            public static function getConnection(): PDO
            {
                if (self::$instance !== null) {
                    return self::$instance;
                }

                try {
                    $conn = \Illuminate\Support\Facades\DB::connection();
                    if ($conn->getDriverName() === 'pgsql') {
                        $config = $conn->getConfig();
                        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']};sslmode=" . ($config['sslmode'] ?? 'require');
                        self::$instance = new \PgsqlCompatPdo($dsn, $config['username'], $config['password'], [
                            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES   => false,
                        ]);
                        return self::$instance;
                    }
                    self::$instance = $conn->getPdo();
                    return self::$instance;
                } catch (\Throwable $e) {
                    if (self::$instance === null) {
                        $driver = function_exists('env') ? env('DB_CONNECTION', 'pgsql') : (getenv('DB_CONNECTION') ?: 'pgsql');
                        $host   = function_exists('env') ? env('DB_HOST') : getenv('DB_HOST');
                        $port   = function_exists('env') ? env('DB_PORT') : getenv('DB_PORT');
                        $db     = function_exists('env') ? env('DB_DATABASE') : getenv('DB_DATABASE');
                        $user   = function_exists('env') ? env('DB_USERNAME') : getenv('DB_USERNAME');
                        $pass   = function_exists('env') ? env('DB_PASSWORD') : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '');

                        if ($driver === 'pgsql' && !empty($host)) {
                            $port = $port ?: 5432;
                            $sslmode = function_exists('env') ? env('DB_SSLMODE', 'require') : (getenv('DB_SSLMODE') ?: 'require');
                            $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode={$sslmode}";
                            self::$instance = new \PgsqlCompatPdo($dsn, $user, $pass, [
                                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                                PDO::ATTR_EMULATE_PREPARES   => false,
                            ]);
                            return self::$instance;
                        }

                        throw new \RuntimeException("Central database connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
                    }
                    return self::$instance;
                }
            }
        }
    }

    if (!class_exists('Session', false)) {
        class Session
        {
            public static function set(string $key, mixed $value): void
            {
                session([$key => $value]);
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION[$key] = $value;
                }
            }

            public static function get(string $key, mixed $default = null): mixed
            {
                return session($key, $_SESSION[$key] ?? $default);
            }

            public static function has(string $key): bool
            {
                return session()->has($key) || isset($_SESSION[$key]);
            }

            public static function remove(string $key): void
            {
                session()->forget($key);
                unset($_SESSION[$key]);
            }

            public static function setFlash(string $type, string $message): void
            {
                $_SESSION['flash'] = [
                    'type' => $type,
                    'message' => $message
                ];
            }

            public static function getFlash(): ?array
            {
                if (isset($_SESSION['flash'])) {
                    $flash = $_SESSION['flash'];
                    unset($_SESSION['flash']);
                    return $flash;
                }
                return null;
            }

            public static function isLoggedIn(): bool
            {
                return \Illuminate\Support\Facades\Auth::check() || !empty(session('user_id'));
            }

            public static function isAdmin(): bool
            {
                $role = strtolower((string)session('role', ''));
                return self::isLoggedIn() && ($role === 'admin' || $role === 'system administrator');
            }

            public static function isDoctor(): bool
            {
                $role = strtolower((string)session('role', ''));
                return self::isLoggedIn() && ($role === 'doctor' || !empty(session('doctor_id')));
            }

            public static function isNurse(): bool
            {
                $role = strtolower((string)session('role', ''));
                return self::isLoggedIn() && (in_array($role, ['nurse', 'triage nurse']) || !empty(session('nurse_id')));
            }

            public static function isMedTech(): bool
            {
                $role = strtolower((string)session('role', ''));
                return self::isLoggedIn() && (in_array($role, ['medical technologist', 'medtech', 'med tech', 'laboratory']) || !empty(session('medtech_id')));
            }

            public static function isPharmacist(): bool
            {
                $role = strtolower((string)session('role', ''));
                return self::isLoggedIn() && (in_array($role, ['pharmacist', 'pharmacy']) || !empty(session('pharmacist_id')));
            }

            public static function isRegistrar(): bool
            {
                $role = strtolower((string)session('role', ''));
                return self::isLoggedIn() && (in_array($role, ['registrar', 'registrator', 'registration']) || !empty(session('registrar_id')));
            }

            public static function isCashier(): bool
            {
                $role = strtolower((string)session('role', ''));
                return self::isLoggedIn() && (in_array($role, ['cashier', 'billing', 'accountant']) || !empty(session('cashier_id')));
            }

            public static function logout(): void
            {
                if (\Illuminate\Support\Facades\Auth::check()) {
                    \Illuminate\Support\Facades\Auth::logout();
                }
                if (function_exists('session')) {
                    session()->flush();
                    session()->regenerate();
                }
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION = [];
                }
            }

            public static function getCurrentUser(): ?array
            {
                if (!self::isLoggedIn()) {
                    return null;
                }

                $u = \Illuminate\Support\Facades\Auth::user();
                $role = session('role', $u->Role ?? 'Staff');
                $fullName = session('full_name', $u ? ($u->FirstName . ' ' . $u->LastName) : 'Hospital Staff');

                $doctorId = session('doctor_id');
                if (!$doctorId && $u) {
                    try {
                        $doc = \App\Models\Doctor::where('UserID', $u->UserID)->first();
                        if (!$doc && !empty($u->Email)) {
                            $doc = \App\Models\Doctor::where('Email', $u->Email)->first();
                        }
                        if (!$doc && (strtolower($role) === 'doctor' || strtolower($u->Username ?? '') === 'cardio')) {
                            $doc = \App\Models\Doctor::where('Status', 'Active')->first() ?? \App\Models\Doctor::first();
                        }
                        if ($doc) {
                            $doctorId = (int)$doc->DoctorID;
                            session([
                                'doctor_id' => $doctorId,
                                'specialty' => $doc->Specialty ?? session('specialty', 'Cardiologist'),
                                'specialty_id' => (int)($doc->SpecialtyID ?? session('specialty_id', 2)),
                                'license_number' => $doc->LicenseNumber ?? session('license_number', ''),
                            ]);
                        }
                    } catch (\Throwable $e) {
                        // ignore DB lookup error
                    }
                }

                return [
                    'user_id'       => (int)session('user_id', $u->UserID ?? 0),
                    'doctor_id'     => $doctorId ? (int)$doctorId : (session('doctor_id') ? (int)session('doctor_id') : null),
                    'username'      => (string)session('username', $u->Username ?? 'staff'),
                    'name'          => (string)$fullName,
                    'full_name'     => (string)$fullName,
                    'role'          => (string)$role,
                    'email'         => (string)session('email', $u->Email ?? 'staff@tupihospital.gov.ph'),
                    'specialty'     => (string)session('specialty', ''),
                    'specialty_id'  => (int)session('specialty_id', 0),
                    'license'       => (string)session('license_number', ''),
                    'license_number'=> (string)session('license_number', ''),
                    'license_no'    => (string)session('license_no', session('license_number', '')),
                    'title'         => (string)session('title', 'Hospital Staff'),
                    'department'    => (string)session('department', 'Clinical Services'),
                    'shift'         => (string)session('shift', 'Regular Shift (08:00 AM – 05:00 PM)'),
                    'initials'      => (string)session('initials', 'TM'),
                    'nurse_id'      => (string)session('nurse_id', ''),
                    'medtech_id'    => (string)session('medtech_id', ''),
                    'pharmacist_id' => (string)session('pharmacist_id', ''),
                    'avatar'        => (string)session('profile_image', ''),
                    'clinic'        => (string)session('clinic', ''),
                ];
            }
        }
    }
}
