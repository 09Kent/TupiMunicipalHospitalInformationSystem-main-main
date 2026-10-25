<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_payments')) {
            Schema::table('billing_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('billing_payments', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('RecordedBy');
                }
                if (!Schema::hasColumn('billing_payments', 'created_at')) {
                    $table->dateTime('created_at')->nullable()->after('PaymentDate');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('billing_payments')) {
            Schema::table('billing_payments', function (Blueprint $table) {
                if (Schema::hasColumn('billing_payments', 'created_by')) {
                    $table->dropColumn('created_by');
                }
                if (Schema::hasColumn('billing_payments', 'created_at')) {
                    $table->dropColumn('created_at');
                }
            });
        }
    }
};
