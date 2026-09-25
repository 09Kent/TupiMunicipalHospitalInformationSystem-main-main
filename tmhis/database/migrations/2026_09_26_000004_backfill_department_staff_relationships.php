<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $departments = DB::table('departments')->pluck('DepartmentID', 'DepartmentCode');
        $specialties = DB::table('specialties')->pluck('SpecialtyID', 'SpecialtyCode');

        $mappings = [
            'HOSP-ER' => ['gp', 'surgeon'],
            'HOSP-OPD' => ['gp', 'pedia', 'obgyn'],
            'HOSP-IM' => ['cardio', 'neuro', 'pulmo', 'gastro', 'ortho', 'derma', 'ent', 'uro', 'psych', 'opht', 'nephro'],
            'HOSP-OR' => ['surgeon', 'ortho'],
        ];

        foreach ($mappings as $departmentCode => $specialtyCodes) {
            $departmentId = $departments[$departmentCode] ?? null;
            if (!$departmentId) {
                continue;
            }
            foreach ($specialtyCodes as $specialtyCode) {
                $specialtyId = $specialties[$specialtyCode] ?? null;
                if ($specialtyId) {
                    DB::table('department_specialties')->updateOrInsert(
                        ['DepartmentID' => $departmentId, 'SpecialtyID' => $specialtyId],
                        ['IsPrimary' => $specialtyCode === 'gp' || $specialtyCode === 'cardio' || $specialtyCode === 'surgeon', 'CreatedAt' => now()]
                    );
                }
            }
        }

        $doctorDepartments = [
            'cardio' => $departments['HOSP-IM'] ?? null,
            'gp' => $departments['HOSP-OPD'] ?? null,
        ];
        foreach ($doctorDepartments as $specialtyCode => $departmentId) {
            if ($departmentId && isset($specialties[$specialtyCode])) {
                DB::table('doctors')->where('SpecialtyID', $specialties[$specialtyCode])->whereNull('DepartmentID')->update(['DepartmentID' => $departmentId]);
            }
        }

        DB::table('doctors')->whereNotNull('DepartmentID')->whereNotNull('UserID')->get()->each(function ($doctor) {
            DB::table('user_departments')->updateOrInsert(
                ['UserID' => $doctor->UserID, 'DepartmentID' => $doctor->DepartmentID],
                ['IsPrimary' => true, 'CreatedAt' => now()]
            );
        });

        $roleDepartments = [
            'Admin' => 'HOSP-ADM', 'Chief' => 'HOSP-ADM', 'Records' => 'HOSP-REC',
            'Register' => 'HOSP-OPD', 'Registrator' => 'HOSP-OPD', 'Nurse' => 'HOSP-NUR',
            'MedTech' => 'HOSP-LAB', 'Pharmacist' => 'HOSP-PHAR', 'Billing' => 'HOSP-BIL',
            'Cashier' => 'HOSP-BIL', 'Accountant' => 'HOSP-BIL',
        ];
        foreach ($roleDepartments as $role => $departmentCode) {
            $departmentId = $departments[$departmentCode] ?? null;
            if (!$departmentId) {
                continue;
            }
            DB::table('users')->where('Role', $role)->get()->each(function ($user) use ($departmentId) {
                DB::table('user_departments')->updateOrInsert(
                    ['UserID' => $user->UserID, 'DepartmentID' => $departmentId],
                    ['IsPrimary' => true, 'CreatedAt' => now()]
                );
            });
        }
    }

    public function down(): void
    {
        DB::table('user_departments')->delete();
        DB::table('department_specialties')->delete();
        DB::table('doctors')->update(['DepartmentID' => null]);
    }
};