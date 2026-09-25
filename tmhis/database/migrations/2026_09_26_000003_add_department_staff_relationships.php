<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('doctors', 'DepartmentID')) {
            Schema::table('doctors', function (Blueprint $table) {
                $table->unsignedInteger('DepartmentID')->nullable()->after('SpecialtyID');
                $table->index('DepartmentID', 'idx_doctors_department');
            });
        }

        if (!Schema::hasTable('department_specialties')) {
            Schema::create('department_specialties', function (Blueprint $table) {
                $table->unsignedInteger('DepartmentID');
                $table->unsignedInteger('SpecialtyID');
                $table->boolean('IsPrimary')->default(false);
                $table->timestamp('CreatedAt')->useCurrent();
                $table->primary(['DepartmentID', 'SpecialtyID']);
                $table->index('SpecialtyID');
            });
        }

        if (!Schema::hasTable('user_departments')) {
            Schema::create('user_departments', function (Blueprint $table) {
                $table->unsignedInteger('UserID');
                $table->unsignedInteger('DepartmentID');
                $table->boolean('IsPrimary')->default(true);
                $table->timestamp('CreatedAt')->useCurrent();
                $table->primary(['UserID', 'DepartmentID']);
                $table->index('DepartmentID');
            });
        }

        if (!Schema::hasColumn('billing_charges', 'source_type')) {
            Schema::table('billing_charges', function (Blueprint $table) {
                $table->string('source_type', 50)->nullable()->index()->after('BillingStatus');
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            });
        }

        $this->seedDepartmentSpecialties();
        $this->assignExistingDoctors();
        $this->assignExistingStaff();
    }

    public function down(): void
    {
        if (Schema::hasTable('user_departments')) {
            Schema::drop('user_departments');
        }
        if (Schema::hasTable('department_specialties')) {
            Schema::drop('department_specialties');
        }
        if (Schema::hasColumn('doctors', 'DepartmentID')) {
            Schema::table('doctors', function (Blueprint $table) {
                $table->dropIndex('idx_doctors_department');
                $table->dropColumn('DepartmentID');
            });
        }
    }

    private function seedDepartmentSpecialties(): void
    {
        $mappings = [
            ['department' => 'HOSP-ER', 'specialties' => ['gp', 'surgeon'], 'primary' => 'gp'],
            ['department' => 'HOSP-OPD', 'specialties' => ['gp', 'pedia', 'obgyn'], 'primary' => 'gp'],
            ['department' => 'HOSP-IM', 'specialties' => ['cardio', 'neuro', 'pulmo', 'gastro', 'ortho', 'derma', 'ent', 'uro', 'psych', 'opht', 'nephro'], 'primary' => 'cardio'],
            ['department' => 'HOSP-OR', 'specialties' => ['surgeon', 'ortho'], 'primary' => 'surgeon'],
        ];

        foreach ($mappings as $mapping) {
            $departmentId = DB::table('departments')->where('DepartmentCode', $mapping['department'])->value('DepartmentID');
            if (!$departmentId) {
                continue;
            }

            foreach ($mapping['specialties'] as $code) {
                $specialtyId = DB::table('specialties')->where('SpecialtyCode', $code)->value('SpecialtyID');
                if ($specialtyId) {
                    DB::table('department_specialties')->updateOrInsert(
                        ['DepartmentID' => $departmentId, 'SpecialtyID' => $specialtyId],
                        ['IsPrimary' => $code === $mapping['primary'], 'CreatedAt' => now()]
                    );
                }
            }
        }
    }

    private function assignExistingDoctors(): void
    {
        $opdId = DB::table('departments')->where('DepartmentCode', 'HOSP-OPD')->value('DepartmentID');
        $internalMedicineId = DB::table('departments')->where('DepartmentCode', 'HOSP-IM')->value('DepartmentID');
        $doctorMappings = [
            'cardio' => $internalMedicineId,
            'gp' => $opdId,
        ];

        foreach ($doctorMappings as $specialtyCode => $departmentId) {
            if (!$departmentId) {
                continue;
            }

            $specialtyId = DB::table('specialties')->where('SpecialtyCode', $specialtyCode)->value('SpecialtyID');
            if ($specialtyId) {
                DB::table('doctors')->where('SpecialtyID', $specialtyId)->whereNull('DepartmentID')->update(['DepartmentID' => $departmentId]);
            }
        }

        DB::table('doctors as d')
            ->join('users as u', 'u.UserID', '=', 'd.UserID')
            ->whereNull('d.DepartmentID')
            ->where('u.Role', 'Doctor')
            ->update(['d.DepartmentID' => $opdId]);

        DB::table('doctors')->whereNotNull('DepartmentID')->whereNotNull('UserID')->get()->each(function ($doctor) {
            DB::table('user_departments')->updateOrInsert(
                ['UserID' => $doctor->UserID, 'DepartmentID' => $doctor->DepartmentID],
                ['IsPrimary' => true, 'CreatedAt' => now()]
            );
        });
    }

    private function assignExistingStaff(): void
    {
        $roleDepartments = [
            'Admin' => 'HOSP-ADM',
            'Chief' => 'HOSP-ADM',
            'Records' => 'HOSP-REC',
            'Register' => 'HOSP-OPD',
            'Registrator' => 'HOSP-OPD',
            'Nurse' => 'HOSP-NUR',
            'MedTech' => 'HOSP-LAB',
            'Pharmacist' => 'HOSP-PHAR',
            'Billing' => 'HOSP-BIL',
            'Cashier' => 'HOSP-BIL',
            'Accountant' => 'HOSP-BIL',
        ];

        foreach ($roleDepartments as $role => $departmentCode) {
            $departmentId = DB::table('departments')->where('DepartmentCode', $departmentCode)->value('DepartmentID');
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
};