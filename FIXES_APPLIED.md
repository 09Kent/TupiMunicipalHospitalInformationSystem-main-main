# TMHIS — Applied Fixes, Refactoring & Security Enhancements

**System:** Tupi Municipal Hospital Information System (TMHIS)  
**Date:** October 14, 2026  

---

## 1. Fixed Issues

1. **Doctor Diagnosis "White Screen" Bug:**
   - Fixed white screen issue when jumping or scrolling to `#soapNoteSection`, `#diagnosisSection`, or `#treatmentPlanSection`.
   - Prevented AOS scroll library from keeping sub-sections permanently invisible at `opacity: 0` inside overflow containers.
   - Applied identical safety styling to `doctor/includes/header.php`, `nurse/includes/header.php`, `medtech/includes/header.php`, and `pharmacy/includes/header.php`.
   - Connected the "Clinical Care Progression Pathway" steps to anchor smoothly to each section.

2. **Vercel Serverless Entrypoint Error:**
   - Fixed fatal require error in `api/index.php` at repository root by updating path from `__DIR__ . '/tmhis/api/index.php'` to `__DIR__ . '/../tmhis/api/index.php'`.

3. **Migration Table Collision Errors:**
   - Wrapped schema creation in `0001_01_01_000000_create_users_table.php`, `0001_01_01_000001_create_cache_table.php`, and `0001_01_01_000002_create_jobs_table.php` with `if (!Schema::hasTable(...))` checks to allow idempotent `php artisan migrate` execution.

4. **Doctor User-to-Profile Mappings:**
   - Created migration `2026_10_14_000001_ensure_all_doctors_mapped_by_user_id.php` ensuring all doctor user accounts (Daniel Lewis, Elena Villanueva, James Wilson, Sophia Miller, Alexander Chen, Ramon Flores) possess active, valid doctor profiles linked by `UserID`.
   - Matched `Status` column to the authoritative enum definition (`Available`, `Busy`, `On Leave`, `Inactive`).

5. **Random Identity Fallback (Doctor::first() / Patient::first()):**
   - Removed `Doctor::first()` fallbacks from `DoctorController.php`, `LoginController.php`, and `legacy_bridge.php`.
   - Replaced `Patient::first()` fallback in `NurseController::createVital` with strict validation returning HTTP 422 if patient does not exist.
   - Prevented patient record leakage in `PrintController.php` and `MedTechController.php` by returning HTTP 404 when specific requested records do not exist.

6. **Fragile Role-Based Access Control (RBAC):**
   - Replaced username substring checks (`str_contains($username, 'nurse')`, `str_contains($username, 'medtech')`) with standardized `$user->hasRole(...)` checks.
   - Centralized role normalization in `App\Models\User::hasRole()`.

7. **Production Error Exception Sanitization:**
   - Replaced raw `$e->getMessage()` responses across `AdminController`, `BillingController`, `DirectorController`, `AssignmentApiController`, `MedicationAdministrationApiController`, `ReferralApiController`, and `RegisterApiController` with `safe_error_message($e, ...)`.
   - Full stack traces are safely logged to Laravel storage logs while clients receive sanitized messages in production.

8. **CSRF Exemption Whitelist Completeness (`bootstrap/app.php`):**
   - Added `medtech/*`, `records/*`, `/register/submit`, and `/register/classify` to `validateCsrfTokens(except: [...])`, resolving `419 Page Expired` failures during AJAX submissions in MedTech, Medical Records, and public intake forms.

9. **Referral Document Privacy Leak Hardening (`PrintController.php`):**
   - Added strict `if ($isSpecificId) { abort(404, ...); }` guard in `case 'referral'`, preventing unintentional leakage of other patients' referral records when a non-existent referral ID is requested.

10. **Diagnostic Lab Report Confidentiality (`MedTechController.php`):**
    - Removed arbitrary `$cleanId <= 1` fallback in `printReport()`, ensuring non-existent laboratory report IDs consistently return `HTTP 404`.

11. **Doctor Status Enum Alignment (`DirectorController.php` & `LabTechnologist.php`):**
    - Updated physician queries from `WHERE Status = 'Active'` to `WHERE Status != 'Inactive'` matching database enum definitions (`Available`, `Busy`, `On Leave`, `Inactive`), ensuring the Medical Director / CMO dashboard accurately reports active doctor counts.

12. **Residual Fallback Removal (`ReferralApiController.php`, `MedicationAdministrationApiController.php`, `NurseController.php`):**
    - Eliminated `Doctor::value('DoctorID') ?: 1` fallback on referral creation (now requires an active attending physician).
    - Removed hardcoded "Dr. Daniel Lewis, MD" fallback for unassigned nurse medication administration tasks.
    - Replaced hardcoded "Nurse Elena Gomez, RN" recorder fallback with "Nurse on Duty".
    - Added patient existence validation in `NurseController::createTask`.

13. **API 404 Status Enforcement (`PharmacyController.php` & `NurseController.php`):**
    - Enforced `HTTP 404` responses when updating non-existent medicine formulary items, nurse tasks, or queue entries instead of returning false-positive `success: true`.

---

## 2. Enhanced Features

1. **Interactive Clinical Care Progression Pathway:**
   - Pathway steps (1. Physical Examination → 2. Clinical SOAP Note → 3. Differential Diagnosis → 4. Treatment & Prescription Plan) on the Doctor Diagnosis interface are now interactive clickable anchors with smooth scrolling.

2. **Print System Confidentiality Hardening:**
   - `PrintController` now distinguishes between preview/sample requests and specific record requests. Specific requests for non-existent IDs cleanly return HTTP 404 rather than displaying someone else's sensitive medical record.

3. **Safe Auto-Provisioning for Registered Physicians:**
   - If an authenticated doctor user logs in without a pre-existing profile in `doctors`, `LoginController` and `DoctorController` safely auto-provision a dedicated profile for their specific `UserID` instead of assigning a random physician's record.

---

## 3. Security Improvements

1. **Zero Information Leakage in Production:**
   - Exception handling respects `APP_DEBUG=false`. Sensitive SQL query strings and credentials are never emitted into API responses.

2. **Strict Identity Isolation:**
   - Medical notes, vital signs, prescriptions, and diagnostic reports cannot be attributed to unassociated patients or doctors.

3. **Backup Directory Security:**
   - Created `tmhis/storage/app/backups/.gitignore` to ensure generated SQL database dumps cannot be committed to Git.
   - Verified that backup files are stored outside the public document root.

4. **Environment File Protection:**
   - Audited `.env.example` to remove hardcoded production passwords.
   - Verified `.env` is ignored by Git and never tracked.

---

## 4. Database Changes

1. **Migration `2026_10_14_000001_ensure_all_doctors_mapped_by_user_id.php`:**
   - Maps each doctor user in the `users` table to an exact record in `doctors` by `UserID`.
   - Populates license numbers, clinic rooms, specialties, and sets status to `Available`.

2. **Idempotent Standard Migrations:**
   - `users`, `cache`, and `jobs` migrations now safely check table existence prior to table creation.

---

## 5. Deployment Fixes

1. **Vercel Root & Subdirectory Dual Deployment:**
   - Corrected root `api/index.php` require path.
   - Verified `vercel.json` configurations both at repo root and inside `tmhis/`.

2. **Asset Path Normalization:**
   - Verified assets resolve properly in local environments, Laragon (`/section/...`), and production (`asset(...)`).

---

## 6. Compatibility & Legacy Code

- Preserved `legacy_bridge.php`, `PgsqlCompatPdo.php`, and legacy service classes (`PatientRegistration`, `BillingManager`, `BackupManager`, `AuditLog`, `HospitalInfo`).
- Maintained all existing legacy route endpoints (`/api/register/patients`, `/api/medtech/laboratory`, etc.) ensuring no disruption to frontend AJAX calls.

---

## 7. Remaining Items / Recommendations

1. **Session Driver for Multi-Instance Deployments:**
   - For serverless (Vercel) multi-region deployments, configure `SESSION_DRIVER=cookie` (as in `vercel.json`) or Redis/database session driver rather than `file` driver.
2. **Scheduled Cron Jobs:**
   - For automated daily backups or queue workers on VPS deployments, configure a cron job to invoke `php artisan schedule:run` every minute.
