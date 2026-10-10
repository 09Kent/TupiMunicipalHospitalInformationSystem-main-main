<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('referrals')) {
            try {
                DB::statement('ALTER TABLE referrals MODIFY TargetSpecialtyID INT NULL DEFAULT NULL');
            } catch (\Throwable $e) {
                // If using SQLite or PostgreSQL, handle gracefully
                try {
                    Schema::table('referrals', function (Blueprint $table) {
                        $table->unsignedBigInteger('TargetSpecialtyID')->nullable()->change();
                    });
                } catch (\Throwable $ex) {}
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('referrals')) {
            try {
                DB::statement('ALTER TABLE referrals MODIFY TargetSpecialtyID INT NOT NULL');
            } catch (\Throwable $e) {}
        }
    }
};
