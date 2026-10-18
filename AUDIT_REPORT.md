# Tupi Municipal Hospital Information System (TMHIS)
## Comprehensive Repository Audit & Architectural Assessment Report

**Date:** October 14, 2026  
**Target System:** Tupi Municipal Hospital Information System (TMHIS)  
**Status:** Audit Completed & Remediated  

---

### 1. Executive Summary

A comprehensive, ground-up audit was conducted on the TMHIS codebase. The hospital system is a hybrid enterprise Laravel application supporting multi-role clinical workflows across Outpatient and Inpatient services at Tupi Municipal Hospital.

The audit verified that the core hospital workflow:
```
PATIENT REGISTRATION
        ↓
PATIENT QUEUE
        ↓
NURSE VITALS
        ↓
DOCTOR CONSULTATION
        ↓
DIAGNOSIS
        ↓
PRESCRIPTION
        ↓
LABORATORY REQUEST
        ↓
LABORATORY RESULT
        ↓
PHARMACY
        ↓
BILLING / PAYMENT
        ↓
MEDICAL RECORDS
```
was fundamentally complete in design, but suffered from critical identity mapping vulnerabilities, fragile role-based access control (RBAC), raw exception exposure, broken Vercel serverless entrypoints, and frontend scroll-lock issues that prevented users from progressing through clinical procedures.

All functional workflows, database schemas, and hospital roles have been preserved without rebuilding from scratch.

---

### 2. Architecture & Component Mapping

#### 2.1 Route to Database Trace
The system follows a Dual-Layer Architecture:
1. **Primary Modern Layer:** Laravel 13 routing (`tmhis/routes/web.php` and `tmhis/routes/api.php`) backed by controllers (`DoctorController`, `NurseController`, `BillingController`, etc.) and Eloquent Models (`Patient`, `Doctor`, `LaboratoryRequest`, etc.).
2. **Compatibility Layer (`legacy_bridge.php` & legacy services):** Provides seamless backwards compatibility with earlier procedural PHP modules without breaking API contracts.

```
Route (web.php / api.php)
  ↓
Middleware (auth, RoleMiddleware, StartSession)
  ↓
Form Request / Controller Validation
  ↓
Controller (e.g. DoctorController, BillingController)
  ↓
Service (e.g. PatientRegistration, BillingManager)
  ↓
Eloquent Model / PDO Database Connection
  ↓
MySQL (MedicalRegistrationDB) / PostgreSQL
  ↓
Blade View / JSON API Response
```

---

### 3. Key Findings & Vulnerability Audit

#### 3.1 Doctor & Staff Identity Mapping (CRITICAL VULNERABILITY - REMEDIATED)
- **Vulnerability:** Previously, when a logged-in doctor account did not match a specific record in the `doctors` table, the system executed `Doctor::where('Status', 'Active')->first() ?? Doctor::first()`.
- **Impact:** Any doctor who logged in whose `UserID` wasn't manually mapped would silently adopt Dr. Alexander Chen's identity or Dr. Ramon Flores's identity. Prescriptions and medical notes would be issued under another physician's name and license number.
- **Similar Issues Found:**
  - `NurseController::createVital`: Fallback `Patient::first()` if `patient_id <= 0`.
  - `MedTechController::printReport`: Fallback `LaboratoryResult::first()` and `Patient::first()` if report ID was not found.
  - `PrintController::index`: Fallback to most recent patient record if requested document was missing.
- **Remediation:**
  - Created idempotent migration `2026_10_14_000001_ensure_all_doctors_mapped_by_user_id.php` linking all doctor user accounts (Daniel Lewis, Elena Villanueva, James Wilson, Sophia Miller, Alexander Chen, Ramon Flores) to dedicated `doctors` records.
  - Eliminated all silent `Doctor::first()` and `Patient::first()` fallbacks.
  - Nurse vital signs creation now strictly requires a valid existing patient or returns HTTP 422.
  - Print report controllers strictly return HTTP 404 if a specific document ID is not found, protecting patient confidentiality.

#### 3.2 Role-Based Access Control Fragility (HIGH RISK - REMEDIATED)
- **Vulnerability:** Authorization in `RoleMiddleware.php` and `LoginController.php` relied heavily on username substrings (`str_contains($username, 'nurse')`, `str_contains($username, 'medtech')`) and hardcoded username arrays (`duty.nurse`, `staff.reyes`).
- **Remediation:**
  - Added native `User::hasRole(...$roles)` method on `App\Models\User` with normalized role group mappings.
  - Refactored `RoleMiddleware.php` to authorize strictly by authenticated user role.
  - Refactored `LoginController::redirectUser` to route deterministically based on `$user->hasRole()`.

#### 3.3 Production Exception & Information Leakage (MEDIUM RISK - REMEDIATED)
- **Vulnerability:** Over 30 API catch blocks in `AdminController`, `BillingController`, `DirectorController`, `AssignmentApiController`, and `RegisterApiController` returned raw `$e->getMessage()`.
- **Impact:** Leaked database table names, SQL syntax errors, and connection parameters to client browsers in error states.
- **Remediation:**
  - Added global `safe_error_message(\Throwable $e, string $fallback)` helper in `legacy_bridge.php`.
  - In production (`APP_DEBUG=false`), exceptions are logged securely with full stack trace to `storage/logs/laravel.log`, while clients receive a sanitized, user-friendly message.

#### 3.4 Vercel Serverless Deployment Failure (CRITICAL BUG - REMEDIATED)
- **Vulnerability:** The repository root `api/index.php` contained `require __DIR__ . '/tmhis/api/index.php'`. On Vercel, `__DIR__` is `/var/task/api`, causing the serverless function to attempt to load `/var/task/api/tmhis/api/index.php` (which does not exist), resulting in a fatal 500 error on every request.
- **Remediation:** Corrected relative path to `require __DIR__ . '/../tmhis/api/index.php';`.

#### 3.5 Doctor Diagnosis "White Screen" on Progression (UX/BUG - REMEDIATED)
- **Vulnerability:** Clicking the clinical progression pathway steps ("Physical Exam", "SOAP Note", "Diagnosis", "Treatment Plan") on `/doctor/diagnosis` caused the entire screen to turn blank white.
- **Root Cause:** The page container used `h-screen overflow-y-auto`, meaning the inner `div` scrolled while `window.scrollY` remained 0. AOS (Animate On Scroll) initialized with `once: true`, but never triggered animations for elements below the viewport fold, leaving them permanently styled with `opacity: 0`.
- **Remediation:**
  - Added CSS override in `doctor/includes/header.php`, `nurse/includes/header.php`, `medtech/includes/header.php`, and `pharmacy/includes/header.php` forcing `[data-aos] { opacity: 1 !important; transform: none !important; visibility: visible !important; }`.
  - Added smooth anchor scrolling with `scroll-mt-24` and interactive clickable steps on the Clinical Care Progression Pathway.

---

### 4. Database Source of Truth & Consolidation

- **Primary Source of Truth:** `MedicalRegistrationDB` (MySQL 3306 / MariaDB) and Supabase Cloud PostgreSQL.
- **Migration Idempotency:** Guarded boilerplate migrations (`create_users_table`, `create_cache_table`, `create_jobs_table`) with `Schema::hasTable(...)` checks so `php artisan migrate` executes cleanly without table collision errors.
- **Integrity Validation:** Validated foreign key relations and status enums across `doctors`, `patients`, `patient_queue`, `consultation_notes`, `laboratory_requests`, `prescriptions`, `pharmacy_inventory`, and `billing_invoices`.
- **Backup Storage Security:** Confirmed backups are saved outside `public/` in `storage/app/backups/` and created `.gitignore` to prevent database dumps from ever being committed to source control.

---

### 5. Test Suite Verification

- **Laravel Feature Tests (`php artisan test`):** 34 tests, 336 assertions — **100% Passed**.
- **End-to-End Workflow Tests (`php test_e2e.php`):** 118 tests — **100% Passed**.
- All 9 roles (Admin, Chief/CMO, Doctor, Nurse, MedTech, Pharmacist, Billing, Records, Register) verified end-to-end.
