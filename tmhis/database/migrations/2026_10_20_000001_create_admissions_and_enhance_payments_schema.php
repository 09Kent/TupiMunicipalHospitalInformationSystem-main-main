<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create admissions table for doctor inpatient admission workflow
        if (!Schema::hasTable('admissions')) {
            Schema::create('admissions', function (Blueprint $table) {
                $table->increments('AdmissionID');
                $table->string('AdmissionNumber', 50)->unique();
                $table->unsignedInteger('PatientID')->index();
                $table->unsignedInteger('DoctorID')->index();
                $table->unsignedInteger('AppointmentID')->nullable()->index();
                $table->unsignedInteger('ConsultationID')->nullable();
                $table->string('Ward', 100);
                $table->string('RoomNumber', 50);
                $table->string('BedNumber', 50);
                $table->text('AdmissionReason');
                $table->dateTime('AdmissionDate');
                $table->enum('Status', ['Admitted', 'Discharged', 'Transferred', 'Cancelled'])->default('Admitted')->index();
                $table->dateTime('DischargeDate')->nullable();
                $table->string('DischargeDisposition', 100)->nullable();
                $table->text('DischargeNotes')->nullable();
                $table->unsignedBigInteger('AdmittedBy')->nullable();
                $table->string('AdmittedByName', 100)->nullable();
                $table->timestamps();
            });
        }

        // 2. Enhance consultation_notes with disposition and admission references
        if (Schema::hasTable('consultation_notes')) {
            Schema::table('consultation_notes', function (Blueprint $table) {
                if (!Schema::hasColumn('consultation_notes', 'Disposition')) {
                    $table->string('Disposition', 50)->nullable()->default('OUTPATIENT')->after('Plan');
                }
                if (!Schema::hasColumn('consultation_notes', 'AdmissionID')) {
                    $table->unsignedInteger('AdmissionID')->nullable()->after('Disposition');
                }
            });
        }

        // 3. Enhance billing_payments table with normalized and provider tracking fields
        if (Schema::hasTable('billing_payments')) {
            Schema::table('billing_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('billing_payments', 'currency')) {
                    $table->string('currency', 10)->default('PHP')->after('AmountPaid');
                }
                if (!Schema::hasColumn('billing_payments', 'payment_provider')) {
                    $table->string('payment_provider', 50)->default('cash')->after('PaymentMethod');
                }
                if (!Schema::hasColumn('billing_payments', 'transaction_reference')) {
                    $table->string('transaction_reference', 100)->nullable()->index()->after('ReferenceNumber');
                }
                if (!Schema::hasColumn('billing_payments', 'provider_transaction_id')) {
                    $table->string('provider_transaction_id', 100)->nullable()->index()->after('transaction_reference');
                }
                if (!Schema::hasColumn('billing_payments', 'request_reference_number')) {
                    $table->string('request_reference_number', 100)->nullable()->after('provider_transaction_id');
                }
                if (!Schema::hasColumn('billing_payments', 'status')) {
                    $table->string('status', 50)->default('PAID')->index()->after('PaymentStatus');
                }
                if (!Schema::hasColumn('billing_payments', 'payment_url')) {
                    $table->text('payment_url')->nullable()->after('status');
                }
                if (!Schema::hasColumn('billing_payments', 'qr_payload')) {
                    $table->mediumText('qr_payload')->nullable()->after('payment_url');
                }
                if (!Schema::hasColumn('billing_payments', 'paid_at')) {
                    $table->dateTime('paid_at')->nullable()->after('qr_payload');
                }
                if (!Schema::hasColumn('billing_payments', 'failed_at')) {
                    $table->dateTime('failed_at')->nullable()->after('paid_at');
                }
                if (!Schema::hasColumn('billing_payments', 'cancelled_at')) {
                    $table->dateTime('cancelled_at')->nullable()->after('failed_at');
                }
                if (!Schema::hasColumn('billing_payments', 'expires_at')) {
                    $table->dateTime('expires_at')->nullable()->after('cancelled_at');
                }
                if (!Schema::hasColumn('billing_payments', 'failure_reason')) {
                    $table->text('failure_reason')->nullable()->after('expires_at');
                }
                if (!Schema::hasColumn('billing_payments', 'provider_response')) {
                    $table->longText('provider_response')->nullable()->after('failure_reason');
                }
                if (!Schema::hasColumn('billing_payments', 'verified_by')) {
                    $table->unsignedBigInteger('verified_by')->nullable()->after('CashierName');
                }
                if (!Schema::hasColumn('billing_payments', 'verified_at')) {
                    $table->dateTime('verified_at')->nullable()->after('verified_by');
                }
                if (!Schema::hasColumn('billing_payments', 'updated_at')) {
                    $table->dateTime('updated_at')->nullable()->after('PaymentDate');
                }
            });
        }

        // 4. Modify billing_invoices.PaymentStatus to varchar(50) so it supports PENDING PAYMENT, PROCESSING, PARTIALLY PAID, PAID IN FULL, CANCELLED
        if (Schema::hasTable('billing_invoices')) {
            try {
                DB::statement("ALTER TABLE `billing_invoices` MODIFY `PaymentStatus` VARCHAR(50) NOT NULL DEFAULT 'Unpaid'");
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admissions');
        
        if (Schema::hasTable('consultation_notes')) {
            Schema::table('consultation_notes', function (Blueprint $table) {
                if (Schema::hasColumn('consultation_notes', 'Disposition')) {
                    $table->dropColumn('Disposition');
                }
                if (Schema::hasColumn('consultation_notes', 'AdmissionID')) {
                    $table->dropColumn('AdmissionID');
                }
            });
        }
    }
};
