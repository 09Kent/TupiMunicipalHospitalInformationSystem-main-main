<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Aligns TMHIS database with Chapter 3 Data Dictionary (Tables 3.19, 3.22, 3.23, 3.28)
     */
    public function up(): void
    {
        // 1. Table 3.19: Data Dictionary of Patients - add philhealth_number / PhilHealthNumber & expand PatientCategory
        if (Schema::hasTable('patients')) {
            if (!Schema::hasColumn('patients', 'PhilHealthNumber')) {
                Schema::table('patients', function (Blueprint $table) {
                    $table->string('PhilHealthNumber', 30)->nullable()->after('BloodType');
                });
            }
            DB::statement("ALTER TABLE `patients` MODIFY `PatientCategory` VARCHAR(50) NOT NULL DEFAULT 'Outpatient'");
        }

        // 2. Table 3.23: Data Dictionary of Patient Vitals - add mean_arterial_pressure / MeanArterialPressure
        if (Schema::hasTable('patient_vitals') && !Schema::hasColumn('patient_vitals', 'MeanArterialPressure')) {
            Schema::table('patient_vitals', function (Blueprint $table) {
                $table->decimal('MeanArterialPressure', 5, 2)->nullable()->after('BMI');
            });
        }

        // 3. Table 3.22: Data Dictionary of Appointments Queue - expand QueueStatus to varchar(50)
        // to support full documented workflow: Waiting, Waiting for Triage, Waiting for Consultation, In Consultation, Laboratory, Pharmacy, For Billing, Completed, Cancelled
        if (Schema::hasTable('patient_queue')) {
            DB::statement("ALTER TABLE `patient_queue` MODIFY `QueueStatus` VARCHAR(50) NOT NULL DEFAULT 'Waiting'");
        }

        // 4. Table 3.28: Laboratory Results - add verification tracking fields and expand Interpretation to varchar(100)
        if (Schema::hasTable('laboratory_results')) {
            DB::statement("ALTER TABLE `laboratory_results` MODIFY `Interpretation` VARCHAR(100) NOT NULL DEFAULT 'Normal'");
            Schema::table('laboratory_results', function (Blueprint $table) {
                if (!Schema::hasColumn('laboratory_results', 'IsVerified')) {
                    $table->boolean('IsVerified')->default(1)->after('Interpretation');
                }
                if (!Schema::hasColumn('laboratory_results', 'VerifiedBy')) {
                    $table->unsignedBigInteger('VerifiedBy')->nullable()->after('IsVerified');
                }
                if (!Schema::hasColumn('laboratory_results', 'VerifiedAt')) {
                    $table->timestamp('VerifiedAt')->nullable()->after('VerifiedBy');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('patients') && Schema::hasColumn('patients', 'PhilHealthNumber')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropColumn('PhilHealthNumber');
            });
        }

        if (Schema::hasTable('patient_vitals') && Schema::hasColumn('patient_vitals', 'MeanArterialPressure')) {
            Schema::table('patient_vitals', function (Blueprint $table) {
                $table->dropColumn('MeanArterialPressure');
            });
        }

        if (Schema::hasTable('laboratory_results')) {
            Schema::table('laboratory_results', function (Blueprint $table) {
                if (Schema::hasColumn('laboratory_results', 'VerifiedAt')) {
                    $table->dropColumn('VerifiedAt');
                }
                if (Schema::hasColumn('laboratory_results', 'VerifiedBy')) {
                    $table->dropColumn('VerifiedBy');
                }
                if (Schema::hasColumn('laboratory_results', 'IsVerified')) {
                    $table->dropColumn('IsVerified');
                }
            });
        }
    }
};
