<?php
// Nurse/includes/functions.php

/**
 * Escape string for HTML
 */
if (!function_exists('e')) {
function e(?string $string): string
{
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}
}

/**
 * Generate or retrieve CSRF token
 */
if (!function_exists('csrf_token')) {
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
}

/**
 * Validate CSRF token
 */
if (!function_exists('validate_csrf')) {
function validate_csrf(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
}

/**
 * Format Date nicely (e.g. 16 Aug 2026)
 */
if (!function_exists('format_date')) {
function format_date(?string $dateString, string $format = 'd M Y'): string
{
    if (!$dateString || $dateString === '0000-00-00') return '—';
    try {
        $date = new DateTime($dateString);
        return $date->format($format);
    } catch (Exception $e) {
        return $dateString;
    }
}
}

/**
 * Format Time nicely (e.g. 09:30 AM)
 */
if (!function_exists('format_time')) {
function format_time(?string $timeString): string
{
    if (!$timeString) return '—';
    try {
        $time = new DateTime($timeString);
        return $time->format('h:i A');
    } catch (Exception $e) {
        return $timeString;
    }
}
}

/**
 * Get patient status badge styling
 */
if (!function_exists('get_patient_status_badge')) {
function get_patient_status_badge(?string $status): string
{
    switch ($status) {
        case 'Critical':
            return 'bg-red-50 text-red-700 border border-red-200';
        case 'Needs Attention':
            return 'bg-amber-50 text-amber-700 border border-amber-200';
        case 'Under Observation':
            return 'bg-blue-50 text-blue-700 border border-blue-200';
        case 'For Discharge':
            return 'bg-purple-50 text-purple-700 border border-purple-200';
        case 'Stable':
        default:
            return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    }
}
}

/**
 * Get vital sign status color
 */
if (!function_exists('get_vital_status_class')) {
function get_vital_status_class(?string $status): string
{
    switch ($status) {
        case 'Critical':
            return 'text-red-600';
        case 'Warning':
            return 'text-amber-600';
        case 'Normal':
        default:
            return 'text-emerald-600';
    }
}
}

/**
 * Determine clinical status of vital sign measurement
 */
if (!function_exists('getVitalStatus')) {
function getVitalStatus(string $type, float|int|string $value): string
{
    $val = (float)$value;
    switch ($type) {
        case 'heart_rate':
            if ($val < 50 || $val > 120) return 'Critical';
            if ($val < 60 || $val > 100) return 'Warning';
            return 'Normal';
        case 'temperature':
            if ($val < 35.0 || $val >= 39.0) return 'Critical';
            if ($val < 36.5 || $val >= 37.8) return 'Warning';
            return 'Normal';
        case 'spo2':
            if ($val < 90) return 'Critical';
            if ($val < 95) return 'Warning';
            return 'Normal';
        case 'bp_systolic':
            if ($val >= 160 || $val < 85) return 'Critical';
            if ($val >= 130 || $val < 90) return 'Warning';
            return 'Normal';
        case 'pain_level':
            if ($val >= 7) return 'Critical';
            if ($val >= 4) return 'Warning';
            return 'Normal';
        case 'respiratory_rate':
            if ($val < 10 || $val > 28) return 'Critical';
            if ($val < 12 || $val > 20) return 'Warning';
            return 'Normal';
        default:
            return 'Normal';
    }
}
}

/**
 * Get queue status badge
 */
if (!function_exists('get_queue_badge')) {
function get_queue_badge(?string $status): string
{
    switch ($status) {
        case 'Called':
            return 'bg-blue-100 text-blue-800 border border-blue-200 font-semibold';
        case 'In Progress':
            return 'bg-indigo-100 text-indigo-800 border border-indigo-200 font-semibold';
        case 'Completed':
            return 'bg-emerald-100 text-emerald-800 border border-emerald-200 font-semibold';
        case 'Cancelled':
            return 'bg-rose-100 text-rose-800 border border-rose-200 font-semibold';
        case 'Waiting':
        default:
            return 'bg-amber-100 text-amber-800 border border-amber-200 font-semibold';
    }
}
}

/**
 * Get task status badge
 */
if (!function_exists('get_task_badge')) {
function get_task_badge(?string $status): string
{
    switch ($status) {
        case 'In Progress':
            return 'bg-blue-600 text-white shadow-sm font-semibold';
        case 'Completed':
            return 'bg-emerald-100 text-emerald-800 border border-emerald-200 font-semibold';
        case 'Cancelled':
            return 'bg-rose-100 text-rose-800 border border-rose-200 font-semibold';
        case 'Pending':
        default:
            return 'bg-amber-100 text-amber-800 border border-amber-200 font-semibold';
    }
}
}

/**
 * Priority badge styling
 */
if (!function_exists('get_priority_badge')) {
function get_priority_badge(?string $priority): string
{
    switch ($priority) {
        case 'Urgent':
        case 'High':
            return 'bg-rose-100 text-rose-700 border border-rose-200 font-bold';
        case 'Medium':
            return 'bg-amber-100 text-amber-700 border border-amber-200';
        case 'Low':
        default:
            return 'bg-slate-100 text-slate-600 border border-slate-200';
    }
}
}

/**
 * JSON response helper
 */
if (!function_exists('json_response')) {
function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
}

/**
 * Nurse portal base URL
 */
if (!function_exists('nurse_url')) {
function nurse_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $parts = explode('?', $path, 2);
    $main = $parts[0];
    $query = isset($parts[1]) ? '?' . $parts[1] : '';

    if (empty($main) || strpos($main, 'views/dashboard') !== false) {
        return (function_exists('url') ? url('/nurse') : '/nurse') . $query;
    }
    if (strpos($main, 'views/vitals') !== false || $main === 'vitals') {
        return (function_exists('url') ? url('/nurse/vitals') : '/nurse/vitals') . $query;
    }
    if (strpos($main, 'views/patients/view.php') !== false || $main === 'patients/view') {
        return (function_exists('url') ? url('/nurse/patients/view') : '/nurse/patients/view') . $query;
    }
    if (strpos($main, 'views/patients') !== false || $main === 'patients') {
        return (function_exists('url') ? url('/nurse/patients') : '/nurse/patients') . $query;
    }
    if (strpos($main, 'views/queue') !== false || $main === 'queue') {
        return (function_exists('url') ? url('/nurse/queue') : '/nurse/queue') . $query;
    }
    if (strpos($main, 'views/tasks') !== false || $main === 'tasks') {
        return (function_exists('url') ? url('/nurse/tasks') : '/nurse/tasks') . $query;
    }
    if (strpos($main, 'views/notifications') !== false || $main === 'notifications') {
        return (function_exists('url') ? url('/nurse/notifications') : '/nurse/notifications') . $query;
    }
    if (strpos($main, 'views/settings') !== false || $main === 'settings') {
        return (function_exists('url') ? url('/nurse/settings') : '/nurse/settings') . $query;
    }
    if (strpos($main, 'views/auth/login.php') !== false) {
        return (function_exists('url') ? url('/login?switch=1') : '/login?switch=1') . $query;
    }

    return (function_exists('url') ? url('/nurse/' . $path) : '/nurse/' . $path);
}
}

/**
 * Doctor portal URL helper
 */
if (!function_exists('doctor_url')) {
function doctor_url(string $path = ''): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $prefix = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/main') === 0 || strpos($_SERVER['REQUEST_URI'] ?? '', '/main') === 0) ? '/main/tmhis/public' : '';
    
    $path = ltrim($path, '/');
    return $protocol . $host . $prefix . '/doctor' . ($path ? '/' . $path : '');
}
}

/**
 * Register portal URL helper
 */
if (!function_exists('register_url')) {
function register_url(string $path = ''): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $prefix = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/main') === 0 || strpos($_SERVER['REQUEST_URI'] ?? '', '/main') === 0) ? '/main/tmhis/public' : '';
    
    $path = ltrim($path, '/');
    return $protocol . $host . $prefix . '/register' . ($path ? '/' . $path : '');
}
}
