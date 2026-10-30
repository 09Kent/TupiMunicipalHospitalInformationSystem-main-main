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
        if (!Schema::hasTable('ambulances')) {
            Schema::create('ambulances', function (Blueprint $table) {
                $table->increments('AmbulanceID');
                $table->string('PlateNumber', 30)->unique();
                $table->string('UnitName', 100);
                $table->string('VehicleModel', 100)->nullable();
                $table->string('EquipmentLevel', 60)->default('Basic Life Support (BLS)');
                $table->string('Status', 40)->default('Available')->index(); // Available, On Transfer, Maintenance, Reserved
                $table->string('CurrentDriver', 100)->nullable();
                $table->string('ContactNumber', 50)->nullable();
                $table->string('CurrentDestination', 255)->nullable();
                $table->unsignedInteger('CurrentPatientID')->nullable();
                $table->string('EstimatedReturnTime', 100)->nullable();
                $table->text('Notes')->nullable();
                $table->timestamps();
            });

            // Seed default active municipal ambulance fleet
            DB::table('ambulances')->insert([
                [
                    'PlateNumber'        => 'SGA-1041',
                    'UnitName'           => 'Ambulance Unit 01 (ALS - Advanced Life Support)',
                    'VehicleModel'       => 'Toyota HiAce Mobile ICU Van',
                    'EquipmentLevel'     => 'Advanced Life Support (ALS)',
                    'Status'             => 'Available',
                    'CurrentDriver'      => 'Reynaldo Gomez (EMT-B / Driver)',
                    'ContactNumber'      => '0917-882-9011',
                    'CurrentDestination' => null,
                    'CurrentPatientID'   => null,
                    'EstimatedReturnTime'=> null,
                    'Notes'              => 'Equipped with transport ventilator, multi-parameter monitor, suction unit, and emergency drug box.',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ],
                [
                    'PlateNumber'        => 'SKD-5520',
                    'UnitName'           => 'Ambulance Unit 02 (BLS - Basic Life Support)',
                    'VehicleModel'       => 'Nissan NV350 Urvan Emergency Unit',
                    'EquipmentLevel'     => 'Basic Life Support (BLS)',
                    'Status'             => 'Available',
                    'CurrentDriver'      => 'Danilo Santos (Driver / First Responder)',
                    'ContactNumber'      => '0920-554-1290',
                    'CurrentDestination' => null,
                    'CurrentPatientID'   => null,
                    'EstimatedReturnTime'=> null,
                    'Notes'              => 'Equipped with AED, oxygen cylinders, trauma dressing kit, backboard, and extrication gear.',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ],
                [
                    'PlateNumber'        => 'SMM-9832',
                    'UnitName'           => 'Ambulance Unit 03 (Rapid Inter-Facility Transport)',
                    'VehicleModel'       => 'Hyundai Staria Transport Unit',
                    'EquipmentLevel'     => 'Patient Transport',
                    'Status'             => 'Available',
                    'CurrentDriver'      => 'Eduardo Morales (Paramedic Driver)',
                    'ContactNumber'      => '0939-112-7744',
                    'CurrentDestination' => null,
                    'CurrentPatientID'   => null,
                    'EstimatedReturnTime'=> null,
                    'Notes'              => 'Inter-hospital stable patient transfers and routine hemodialysis/outreach transport.',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]
            ]);
        }

        // Add ambulance tracking columns to referrals table if missing
        if (Schema::hasTable('referrals')) {
            Schema::table('referrals', function (Blueprint $table) {
                if (!Schema::hasColumn('referrals', 'AmbulanceID')) {
                    $table->unsignedInteger('AmbulanceID')->nullable()->after('RequestedService');
                }
                if (!Schema::hasColumn('referrals', 'RequiresAmbulance')) {
                    $table->boolean('RequiresAmbulance')->default(false)->after('AmbulanceID');
                }
                if (!Schema::hasColumn('referrals', 'AmbulanceStatus')) {
                    $table->string('AmbulanceStatus', 50)->nullable()->after('RequiresAmbulance');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('referrals')) {
            Schema::table('referrals', function (Blueprint $table) {
                if (Schema::hasColumn('referrals', 'AmbulanceStatus')) {
                    $table->dropColumn('AmbulanceStatus');
                }
                if (Schema::hasColumn('referrals', 'RequiresAmbulance')) {
                    $table->dropColumn('RequiresAmbulance');
                }
                if (Schema::hasColumn('referrals', 'AmbulanceID')) {
                    $table->dropColumn('AmbulanceID');
                }
            });
        }

        Schema::dropIfExists('ambulances');
    }
};

