<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\DirectorController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\NurseController;
use App\Http\Controllers\RecordsController;
use App\Http\Controllers\MedTechController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\Api\RegisterApiController;

// Public Landing Page
Route::get('/', [LandingController::class, 'index'])->name('landing');

// Authentication
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::get('/logout', [LoginController::class, 'logout'])->name('logout');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout.post');

// =========================================================================
// ROLE 4: REGISTRATION & APPOINTMENTS (Public Intake & Staff Management)
// =========================================================================
Route::prefix('register')->name('register.')->group(function () {
    // Patient Intake & Appointment Booking (Open to Public and all Hospital Staff)
    Route::get('/intake', [RegisterController::class, 'registration'])->name('registration.index');
    Route::get('/registration', [RegisterController::class, 'registration'])->name('registration.alt');

    // Clinical Appointments Schedule (Accessible to all authenticated hospital staff)
    Route::middleware(['auth'])->group(function () {
        Route::get('/appointments', [RegisterController::class, 'appointments'])->name('appointments.index');
        Route::match(['get', 'post'], '/appointments/{id}/reschedule', [RegisterController::class, 'rescheduleAppointment'])->name('appointments.reschedule');
        Route::match(['get', 'post'], '/appointments/{id}/cancel', [RegisterController::class, 'cancelAppointment'])->name('appointments.cancel');
    });

    // Registrator & Admin Clinical Management Portal
    Route::middleware(['auth', 'role:Register,Registrator,Admin'])->group(function () {
        Route::get('/', [RegisterController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard', [RegisterController::class, 'dashboard'])->name('dashboard.index');
        
        // Patients
        Route::get('/patients', [RegisterController::class, 'patients'])->name('patients.index');
        Route::get('/patients/{id}', [RegisterController::class, 'viewPatient'])->name('patients.view');
        Route::get('/patients/{id}/edit', [RegisterController::class, 'editPatient'])->name('patients.edit');
        Route::post('/patients/{id}/update', [RegisterController::class, 'updatePatient'])->name('patients.update');
        
        // Live Queue
        Route::get('/queue', [RegisterController::class, 'queue'])->name('queue.index');
        
        // Reports
        Route::get('/reports', [RegisterController::class, 'reports'])->name('reports.index');
    });
});

// Register API Routes
Route::prefix('api/register')->name('api.register.')->group(function () {
    Route::post('/classify', [RegisterApiController::class, 'classify'])->name('classify');
    Route::post('/submit', [RegisterApiController::class, 'submitRegistration'])->name('submit');
    Route::get('/symptoms', [RegisterApiController::class, 'getSymptoms'])->name('symptoms');
    Route::get('/doctors', [RegisterApiController::class, 'getDoctors'])->name('doctors');
    Route::get('/patients', [RegisterApiController::class, 'getPatients'])->name('patients');
    Route::get('/queue', [RegisterApiController::class, 'getQueue'])->name('queue');
    Route::post('/queue/call', [RegisterApiController::class, 'callQueueNext'])->name('queue.call');
});

// =========================================================================
// DEPARTMENT ROLE DASHBOARDS (Strict RBAC Protected)
// =========================================================================

Route::middleware(['auth'])->group(function () {
    // Admin
    Route::get('/admin', [AdminController::class, 'dashboard'])
        ->middleware('role:Admin')
        ->name('admin.dashboard');
    
    // Director / Chief Medical Officer
    Route::get('/director', [DirectorController::class, 'dashboard'])
        ->middleware('role:Chief,Director,Admin')
        ->name('director.dashboard');
    
    // Medical Records Officer
    Route::get('/records', [RecordsController::class, 'dashboard'])
        ->middleware('role:Records,Admin')
        ->name('records.dashboard');
    Route::middleware('role:Records,Admin')->prefix('records')->name('records.')->group(function () {
        Route::get('/api/patients', [RecordsController::class, 'getPatients'])->name('api.patients');
        Route::post('/api/patients', [RecordsController::class, 'createPatient'])->name('api.patients.create');
        Route::post('/api/patients/{id}/update', [RecordsController::class, 'updatePatient'])->name('api.patients.update');
        Route::post('/api/patients/{id}/archive', [RecordsController::class, 'archivePatient'])->name('api.patients.archive');
        Route::post('/api/patients/{id}/verify', [RecordsController::class, 'verifyPatient'])->name('api.patients.verify');
        Route::get('/api/requests', [RecordsController::class, 'getRequests'])->name('api.requests');
        Route::post('/api/requests/{id}/process', [RecordsController::class, 'processRequest'])->name('api.requests.process');
        Route::get('/api/history/{id}', [RecordsController::class, 'patientHistory'])->name('api.history');
        Route::get('/summary/{id}', [RecordsController::class, 'summary'])->name('summary');
    });
    
    // Doctor Consultation
    Route::middleware('role:Doctor,Admin')->prefix('doctor')->name('doctor.')->group(function () {
        Route::get('/', [DoctorController::class, 'dashboard'])->name('dashboard');
        Route::match(['get', 'post'], '/views/{path}', [DoctorController::class, 'handleLegacyView'])->where('path', '.*')->name('legacy');
        Route::match(['get', 'post'], '/{page}', [DoctorController::class, 'handlePage'])->name('page');
        Route::match(['get', 'post'], '/{page}/{sub}', [DoctorController::class, 'handlePage'])->name('page.sub');
    });
    
    // Nursing Station
    Route::middleware('role:Nurse,Admin')->prefix('nurse')->name('nurse.')->group(function () {
        Route::get('/', [NurseController::class, 'dashboard'])->name('dashboard');
        Route::post('/api/vitals', [NurseController::class, 'createVital'])->name('api.vitals.create');
        Route::post('/api/vitals/{id}/update', [NurseController::class, 'updateVital'])->name('api.vitals.update');
        Route::post('/api/tasks/create', [NurseController::class, 'createTask'])->name('api.tasks.create');
        Route::post('/api/tasks/{id}/status', [NurseController::class, 'updateTaskStatus'])->name('api.tasks.status');
        Route::post('/api/patients/{id}/status', [NurseController::class, 'updatePatientStatus'])->name('api.patients.status');
        Route::post('/api/queue/{id}/status', [NurseController::class, 'updateQueueStatus'])->name('api.queue.status');
        Route::post('/api/queue/call', [NurseController::class, 'callNextQueue'])->name('api.queue.call');
        Route::match(['get', 'post'], '/views/{path}', [NurseController::class, 'handleLegacyView'])->where('path', '.*')->name('legacy');
        Route::match(['get', 'post'], '/{page}', [NurseController::class, 'handlePage'])->name('page');
        Route::match(['get', 'post'], '/{page}/{sub}', [NurseController::class, 'handlePage'])->name('page.sub');
    });

    
    // Medical Technologist / Laboratory
    Route::middleware('role:MedTech,Admin')->prefix('medtech')->name('medtech.')->group(function () {
        Route::get('/', [MedTechController::class, 'dashboard'])->name('dashboard');
        Route::post('/api/requests/{id}/status', [MedTechController::class, 'updateRequestStatus'])->name('requests.status');
        Route::post('/api/samples/create', [MedTechController::class, 'createSample'])->name('samples.create');
        Route::post('/api/samples/{id}/status', [MedTechController::class, 'updateSampleStatus'])->name('samples.status');
        Route::post('/api/results/create', [MedTechController::class, 'createResult'])->name('results.create');
        Route::post('/api/results/{id}/update', [MedTechController::class, 'updateResult'])->name('results.update');
        Route::post('/api/catalog/create', [MedTechController::class, 'createCatalog'])->name('catalog.create');
        Route::post('/api/catalog/{id}/update', [MedTechController::class, 'updateCatalog'])->name('catalog.update');
        Route::post('/api/catalog/{id}/toggle', [MedTechController::class, 'toggleCatalog'])->name('catalog.toggle');
        Route::post('/api/ranges/{id}/update', [MedTechController::class, 'updateRange'])->name('ranges.update');
        Route::get('/report/{id}/print', [MedTechController::class, 'printReport'])->name('report.print');
    });
    
    // Pharmacy
    Route::get('/pharmacy', [PharmacyController::class, 'dashboard'])
        ->middleware('role:Pharmacist,Pharmacy,Admin')
        ->name('pharmacy.dashboard');
    
    // Billing & Cashier
    Route::get('/billing', [BillingController::class, 'dashboard'])
        ->middleware('role:Billing,Cashier,Accountant,Admin')
        ->name('billing.dashboard');
});

// Pharmacy API Routes
Route::middleware(['auth', 'role:Pharmacist,Pharmacy,Admin'])->prefix('pharmacy/api')->name('pharmacy.api.')->group(function () {
    Route::post('/catalog/add', [PharmacyController::class, 'addCatalog'])->name('catalog.add');
    Route::post('/catalog/update', [PharmacyController::class, 'updateCatalog'])->name('catalog.update');
    Route::post('/catalog/toggle', [PharmacyController::class, 'toggleCatalogStatus'])->name('catalog.toggle');
});

// Billing API Routes
Route::middleware(['auth', 'role:Billing,Cashier,Accountant,Admin'])->prefix('billing/api')->name('billing.api.')->group(function () {
    Route::get('/data', [BillingController::class, 'apiData'])->name('data');
    Route::post('/charges', [BillingController::class, 'createCharge'])->name('charges.create');
    Route::post('/discounts/compute', [BillingController::class, 'computeDiscounts'])->name('discounts.compute');
    Route::post('/payments', [BillingController::class, 'processPayment'])->name('payments.process');
    Route::post('/aggregate/{patientId}', [BillingController::class, 'aggregateUnbilled'])->name('aggregate');
});

// Director / CMO API Routes (Protected, supports both /director/api/ and root /api/ prefixes)
$registerDirectorApiRoutes = function ($prefix, $as) {
    Route::middleware(['auth', 'role:Chief,Director,Admin'])->prefix($prefix)->name($as)->group(function () {
        Route::get('/department-performance.php', [DirectorController::class, 'departmentPerformance'])->name('dept.perf');
        Route::get('/department-performance', [DirectorController::class, 'departmentPerformance'])->name('dept.perf.clean');
        Route::get('/doctor-stats.php', [DirectorController::class, 'doctorStats'])->name('doctor.stats');
        Route::get('/doctor-stats', [DirectorController::class, 'doctorStats'])->name('doctor.stats.clean');
        Route::get('/dashboard-stats.php', [DirectorController::class, 'dashboardStats'])->name('dashboard.stats');
        Route::get('/dashboard-stats', [DirectorController::class, 'dashboardStats'])->name('dashboard.stats.clean');
        Route::get('/reports.php', [DirectorController::class, 'reports'])->name('reports');
        Route::get('/reports', [DirectorController::class, 'reports'])->name('reports.clean');
        Route::get('/staff-activity.php', [DirectorController::class, 'staffActivity'])->name('staff.activity');
        Route::get('/staff-activity', [DirectorController::class, 'staffActivity'])->name('staff.activity.clean');
        Route::match(['get', 'post'], '/notifications.php', [DirectorController::class, 'notifications'])->name('notifications');
        Route::match(['get', 'post'], '/notifications', [DirectorController::class, 'notifications'])->name('notifications.clean');
        Route::get('/audit-trail.php', [DirectorController::class, 'auditTrail'])->name('audit.trail');
        Route::get('/audit-trail', [DirectorController::class, 'auditTrail'])->name('audit.trail.clean');
        Route::get('/export-report.php', [DirectorController::class, 'exportReport'])->name('export.report');
        Route::get('/export-report', [DirectorController::class, 'exportReport'])->name('export.report.clean');
    });
};

$registerDirectorApiRoutes('director/api', 'director.api.');
$registerDirectorApiRoutes('api', 'api.director.');

// Admin API Routes (Accessible under both /admin/api/ and root /api/)
$registerAdminApiRoutes = function ($prefix, $as) {
    Route::middleware(['auth', 'role:Admin'])->prefix($prefix)->name($as)->group(function () {
        Route::match(['get', 'post'], '/users.php', [AdminController::class, 'usersApi'])->name('users');
        Route::match(['get', 'post'], '/users', [AdminController::class, 'usersApi'])->name('users.clean');
        Route::match(['get', 'post'], '/config.php', [AdminController::class, 'configApi'])->name('config');
        Route::match(['get', 'post'], '/config', [AdminController::class, 'configApi'])->name('config.clean');
        Route::match(['get', 'post'], '/departments.php', [AdminController::class, 'departmentsApi'])->name('depts');
        Route::match(['get', 'post'], '/departments', [AdminController::class, 'departmentsApi'])->name('depts.clean');
        Route::match(['get', 'post'], '/service_fees.php', [AdminController::class, 'serviceFeesApi'])->name('fees');
        Route::match(['get', 'post'], '/service_fees', [AdminController::class, 'serviceFeesApi'])->name('fees.clean');
        Route::match(['get', 'post'], '/backups.php', [AdminController::class, 'backupsApi'])->name('backups');
        Route::match(['get', 'post'], '/backups', [AdminController::class, 'backupsApi'])->name('backups.clean');
        Route::match(['get', 'post'], '/backup.php', [AdminController::class, 'backupsApi'])->name('backup');
        Route::match(['get', 'post'], '/backup', [AdminController::class, 'backupsApi'])->name('backup.clean');
        Route::match(['get', 'post'], '/roles.php', [AdminController::class, 'rolesApi'])->name('roles');
        Route::match(['get', 'post'], '/roles', [AdminController::class, 'rolesApi'])->name('roles.clean');
        Route::match(['get', 'post'], '/logs.php', [AdminController::class, 'logsApi'])->name('logs');
        Route::match(['get', 'post'], '/logs', [AdminController::class, 'logsApi'])->name('logs.clean');
    });
};

$registerAdminApiRoutes('admin/api', 'admin.api.');
$registerAdminApiRoutes('api', 'api.admin.');

// Legacy API aliases for Registration and Clinical
Route::match(['get', 'post'], '/api/registration/classify.php', [RegisterApiController::class, 'classify']);
Route::match(['get', 'post'], '/api/registration/submit.php', [RegisterApiController::class, 'submitRegistration']);
Route::match(['get', 'post'], '/api/vitals.php', [NurseController::class, 'createVital']);
