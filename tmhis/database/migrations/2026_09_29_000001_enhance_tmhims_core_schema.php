<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Billing Invoices: Add OtherDeductions and AmountCovered
        if (Schema::hasTable('billing_invoices')) {
            Schema::table('billing_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('billing_invoices', 'OtherDeductions')) {
                    $table->decimal('OtherDeductions', 10, 2)->default(0.00)->after('PhilHealthDeduction');
                }
                if (!Schema::hasColumn('billing_invoices', 'AmountCovered')) {
                    $table->decimal('AmountCovered', 10, 2)->default(0.00)->after('OtherDeductions');
                }
            });
        }

        // 2. Billing Payments: Add PaymentStatus, RecordedBy
        if (Schema::hasTable('billing_payments')) {
            Schema::table('billing_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('billing_payments', 'PaymentStatus')) {
                    $table->string('PaymentStatus', 50)->default('Completed')->after('AmountPaid');
                }
                if (!Schema::hasColumn('billing_payments', 'RecordedBy')) {
                    $table->string('RecordedBy', 100)->nullable()->after('CashierName');
                }
            });
            try {
                DB::statement("ALTER TABLE billing_payments MODIFY COLUMN PaymentMethod VARCHAR(50) NOT NULL DEFAULT 'Cash'");
            } catch (\Throwable $e) {}
        }

        // 3. Referrals: Add External Hospital Referral columns & Tracking
        if (Schema::hasTable('referrals')) {
            Schema::table('referrals', function (Blueprint $table) {
                if (!Schema::hasColumn('referrals', 'ReferralType')) {
                    $table->string('ReferralType', 20)->default('Internal')->after('ReferralCode');
                }
                if (!Schema::hasColumn('referrals', 'ReceivingHospital')) {
                    $table->string('ReceivingHospital', 255)->nullable()->after('TargetDoctorID');
                }
                if (!Schema::hasColumn('referrals', 'HospitalAddress')) {
                    $table->string('HospitalAddress', 255)->nullable()->after('ReceivingHospital');
                }
                if (!Schema::hasColumn('referrals', 'RequestedService')) {
                    $table->string('RequestedService', 255)->nullable()->after('HospitalAddress');
                }
                if (!Schema::hasColumn('referrals', 'ReferringDepartment')) {
                    $table->string('ReferringDepartment', 100)->nullable()->after('ReferringDoctorID');
                }
                if (!Schema::hasColumn('referrals', 'Urgency')) {
                    $table->string('Urgency', 50)->default('Routine')->after('Priority');
                }
                if (!Schema::hasColumn('referrals', 'TrackingStatus')) {
                    $table->string('TrackingStatus', 50)->default('Created')->after('Status');
                }
            });
        }

        // 4. Nurse Tasks: Add medication administration columns and schedule tracking
        if (Schema::hasTable('nurse_tasks')) {
            Schema::table('nurse_tasks', function (Blueprint $table) {
                if (!Schema::hasColumn('nurse_tasks', 'PrescriptionID')) {
                    $table->unsignedBigInteger('PrescriptionID')->nullable()->after('DoctorID');
                }
                if (!Schema::hasColumn('nurse_tasks', 'MedicationName')) {
                    $table->string('MedicationName', 255)->nullable()->after('PrescriptionID');
                }
                if (!Schema::hasColumn('nurse_tasks', 'Dosage')) {
                    $table->string('Dosage', 100)->nullable()->after('MedicationName');
                }
                if (!Schema::hasColumn('nurse_tasks', 'Route')) {
                    $table->string('Route', 50)->nullable()->after('Dosage');
                }
                if (!Schema::hasColumn('nurse_tasks', 'Frequency')) {
                    $table->string('Frequency', 50)->nullable()->after('Route');
                }
                if (!Schema::hasColumn('nurse_tasks', 'ScheduledTime')) {
                    $table->datetime('ScheduledTime')->nullable()->after('DueTime');
                }
                if (!Schema::hasColumn('nurse_tasks', 'AdministeredAt')) {
                    $table->datetime('AdministeredAt')->nullable()->after('CompletedAt');
                }
                if (!Schema::hasColumn('nurse_tasks', 'AdministeredBy')) {
                    $table->unsignedBigInteger('AdministeredBy')->nullable()->after('AdministeredAt');
                }
                if (!Schema::hasColumn('nurse_tasks', 'AdministeredByName')) {
                    $table->string('AdministeredByName', 100)->nullable()->after('AdministeredBy');
                }
                if (!Schema::hasColumn('nurse_tasks', 'AssignedBy')) {
                    $table->unsignedBigInteger('AssignedBy')->nullable()->after('NurseID');
                }
                if (!Schema::hasColumn('nurse_tasks', 'AssignedByName')) {
                    $table->string('AssignedByName', 100)->nullable()->after('AssignedBy');
                }
            });
            try {
                DB::statement("ALTER TABLE nurse_tasks MODIFY COLUMN Status VARCHAR(50) NOT NULL DEFAULT 'Pending'");
            } catch (\Throwable $e) {}
        }

        // 5. Doctor Assignments Table
        if (!Schema::hasTable('doctor_assignments')) {
            Schema::create('doctor_assignments', function (Blueprint $table) {
                $table->bigIncrements('AssignmentID');
                $table->unsignedBigInteger('PatientID');
                $table->unsignedBigInteger('DoctorID');
                $table->unsignedBigInteger('PreviousDoctorID')->nullable();
                $table->unsignedBigInteger('DepartmentID')->nullable();
                $table->string('Status', 50)->default('Assigned'); // Assigned, In Consultation, Completed, Reassigned, Cancelled
                $table->unsignedBigInteger('AssignedBy')->nullable();
                $table->string('AssignedByName', 100)->nullable();
                $table->timestamp('AssignedAt')->useCurrent();
                $table->text('Notes')->nullable();

                $table->index('PatientID');
                $table->index('DoctorID');
                $table->index('Status');
            });
        }

        // 6. Nurse Assignments Table
        if (!Schema::hasTable('nurse_assignments')) {
            Schema::create('nurse_assignments', function (Blueprint $table) {
                $table->bigIncrements('AssignmentID');
                $table->unsignedBigInteger('PatientID');
                $table->unsignedBigInteger('DoctorID')->nullable();
                $table->unsignedBigInteger('NurseID');
                $table->unsignedBigInteger('TaskID')->nullable();
                $table->string('TaskDescription', 255)->nullable();
                $table->datetime('ScheduledTime')->nullable();
                $table->string('Priority', 50)->default('Normal'); // Urgent, High, Normal, Low
                $table->string('Status', 50)->default('Assigned'); // Assigned, In Progress, Completed, Cancelled
                $table->unsignedBigInteger('AssignedBy')->nullable();
                $table->string('AssignedByName', 100)->nullable();
                $table->timestamp('AssignedAt')->useCurrent();
                $table->text('Notes')->nullable();

                $table->index('PatientID');
                $table->index('NurseID');
                $table->index('Status');
            });
        }

        // 7. Print Logs Table
        if (!Schema::hasTable('print_logs')) {
            Schema::create('print_logs', function (Blueprint $table) {
                $table->bigIncrements('LogID');
                $table->unsignedBigInteger('UserID')->nullable();
                $table->string('UserName', 100)->nullable();
                $table->string('UserRole', 50)->nullable();
                $table->string('DocumentType', 100);
                $table->unsignedBigInteger('PatientID')->nullable();
                $table->string('PatientName', 200)->nullable();
                $table->string('RecordID', 100)->nullable();
                $table->string('Action', 100)->default('Print');
                $table->string('IPAddress', 45)->nullable();
                $table->text('UserAgent')->nullable();
                $table->timestamp('CreatedAt')->useCurrent();

                $table->index('DocumentType');
                $table->index('PatientID');
                $table->index('CreatedAt');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('print_logs');
        Schema::dropIfExists('nurse_assignments');
        Schema::dropIfExists('doctor_assignments');
    }
};
