<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table) {
                if (!Schema::hasColumn('patients', 'PhilHealthMemberType')) {
                    $table->string('PhilHealthMemberType', 50)->default('None')->after('PhilHealthNumber');
                }
                if (!Schema::hasColumn('patients', 'PhilHealthMemberName')) {
                    $table->string('PhilHealthMemberName', 150)->nullable()->after('PhilHealthMemberType');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table) {
                if (Schema::hasColumn('patients', 'PhilHealthMemberName')) {
                    $table->dropColumn('PhilHealthMemberName');
                }
                if (Schema::hasColumn('patients', 'PhilHealthMemberType')) {
                    $table->dropColumn('PhilHealthMemberType');
                }
            });
        }
    }
};
