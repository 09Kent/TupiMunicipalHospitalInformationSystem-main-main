<?php
// Doctor/includes/functions.php

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
    if (function_exists('app') && app()->environment('testing')) {
        return true;
    }
    if (!empty($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    if (!empty($token) && function_exists('session') && $token === session('_token')) {
        return true;
    }
    return false;
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
 * Get category badge color styling
 */
if (!function_exists('get_category_badge')) {
function get_category_badge(?string $category): string
{
    switch ($category) {
        case 'Emergency':
            return 'bg-rose-50 text-rose-700 border border-rose-200';
        case 'Admitted':
            return 'bg-amber-50 text-amber-700 border border-amber-200';
        case 'Consultation':
            return 'bg-blue-50 text-blue-700 border border-blue-200';
        case 'Inpatient':
            return 'bg-purple-50 text-purple-700 border border-purple-200';
        case 'Outpatient':
        default:
            return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    }
}
}

/**
 * Get status badge color styling
 */
if (!function_exists('get_status_badge')) {
function get_status_badge(?string $status): string
{
    switch ($status) {
        case 'In Consultation':
            return 'bg-blue-600 text-white shadow-sm font-semibold';
        case 'Waiting':
            return 'bg-amber-100 text-amber-800 border border-amber-200 font-semibold';
        case 'Completed':
            return 'bg-emerald-100 text-emerald-800 border border-emerald-200 font-semibold';
        case 'Cancelled':
        case 'No Show':
        case 'Declined':
            return 'bg-rose-100 text-rose-800 border border-rose-200 font-semibold';
        case 'Confirmed':
        case 'Accepted':
            return 'bg-teal-100 text-teal-800 border border-teal-200 font-semibold';
        case 'Pending':
            return 'bg-orange-100 text-orange-800 border border-orange-200 font-semibold';
        case 'Scheduled':
        default:
            return 'bg-slate-100 text-slate-700 border border-slate-200 font-semibold';
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
        case 'STAT':
        case 'Emergency':
            return 'bg-red-500 text-white animate-pulse';
        case 'Urgent':
        case 'High Priority':
        case 'Priority':
            return 'bg-rose-100 text-rose-700 border border-rose-200 font-bold';
        case 'Follow-up':
            return 'bg-indigo-100 text-indigo-700 border border-indigo-200';
        case 'Routine':
        case 'Normal':
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
 * Doctor portal base URL
 */
if (!function_exists('doctor_url')) {
function doctor_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $parts = explode('?', $path, 2);
    $main = $parts[0];
    $query = isset($parts[1]) ? '?' . $parts[1] : '';

    if (empty($main) || strpos($main, 'views/dashboard') !== false) {
        return (function_exists('url') ? url('/doctor') : '/doctor') . $query;
    }
    if (strpos($main, 'views/patients/view.php') !== false || $main === 'patients/view') {
        return (function_exists('url') ? url('/doctor/patients/view') : '/doctor/patients/view') . $query;
    }
    if (strpos($main, 'views/patients') !== false || $main === 'patients') {
        return (function_exists('url') ? url('/doctor/patients') : '/doctor/patients') . $query;
    }
    if (strpos($main, 'views/diagnosis') !== false || $main === 'diagnosis') {
        return (function_exists('url') ? url('/doctor/diagnosis') : '/doctor/diagnosis') . $query;
    }
    if (strpos($main, 'views/prescriptions/print.php') !== false || $main === 'prescriptions/print') {
        return (function_exists('url') ? url('/doctor/prescriptions/print') : '/doctor/prescriptions/print') . $query;
    }
    if (strpos($main, 'views/prescriptions') !== false || $main === 'prescriptions') {
        return (function_exists('url') ? url('/doctor/prescriptions') : '/doctor/prescriptions') . $query;
    }
    if (strpos($main, 'views/laboratory') !== false || $main === 'laboratory') {
        return (function_exists('url') ? url('/doctor/laboratory') : '/doctor/laboratory') . $query;
    }
    if (strpos($main, 'views/referrals') !== false || $main === 'referrals') {
        return (function_exists('url') ? url('/doctor/referrals') : '/doctor/referrals') . $query;
    }
    if (strpos($main, 'views/certificates/print.php') !== false || $main === 'certificates/print') {
        return (function_exists('url') ? url('/doctor/certificates/print') : '/doctor/certificates/print') . $query;
    }
    if (strpos($main, 'views/certificates') !== false || $main === 'certificates') {
        return (function_exists('url') ? url('/doctor/certificates') : '/doctor/certificates') . $query;
    }
    if (strpos($main, 'views/settings') !== false || $main === 'settings') {
        return (function_exists('url') ? url('/doctor/settings') : '/doctor/settings') . $query;
    }

    return (function_exists('url') ? url('/doctor/' . $path) : '/doctor/' . $path);
}
}

/**
 * System root / Register URL helper
 */
if (!function_exists('register_url')) {
function register_url(string $path = ''): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $prefix = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/main') === 0 || strpos($_SERVER['REQUEST_URI'] ?? '', '/main') === 0) ? '/main' : '';
    
    $path = ltrim($path, '/');
    return $protocol . $host . $prefix . '/Section/Register/' . $path;
}
}
