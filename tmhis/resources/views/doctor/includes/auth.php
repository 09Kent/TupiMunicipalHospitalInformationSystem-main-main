<?php
// Doctor/includes/auth.php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Doctor.php';

// Enforce legitimate authentication
function require_doctor_auth(): void
{
    if (!Session::isLoggedIn() || Session::get('role') !== 'Doctor' || !Session::has('doctor_id')) {
        $loginUrl = function_exists('route') ? route('login') : doctor_url('views/auth/login.php');
        header('Location: ' . $loginUrl);
        exit;
    }
}