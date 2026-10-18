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
        // 1. Ensure UserID column on doctors table has proper index
        if (Schema::hasTable('doctors') && Schema::hasColumn('doctors', 'UserID')) {
            // Mapping specifications for predefined doctor accounts
            $doctorSpecs = [
                'dr.lewis' => [
                    'SpecialtyID' => 2,
                    'Specialty' => 'Cardiologist',
                    'Title' => 'MD, FACC',
                    'LicenseNumber' => 'LIC-MED-101',
                    'ClinicRoom' => 'Room 501, Heart Center'
                ],
                'dr.villanueva' => [
                    'SpecialtyID' => 3,
                    'Specialty' => 'Neurologist',
                    'Title' => 'MD, FCN',
                    'LicenseNumber' => 'LIC-MED-102',
                    'ClinicRoom' => 'Room 302, North Wing'
                ],
                'dr.wilson' => [
                    'SpecialtyID' => 8,
                    'Specialty' => 'General Surgeon',
                    'Title' => 'MD, FACS',
                    'LicenseNumber' => 'LIC-MED-104',
                    'ClinicRoom' => 'Room 601, Surgical Pavilion'
                ],
                'dr.miller' => [
                    'SpecialtyID' => 7,
                    'Specialty' => 'Pediatrician',
                    'Title' => 'MD, FAAP',
                    'LicenseNumber' => 'LIC-MED-103',
                    'ClinicRoom' => 'Room 104, Children Wing'
                ],
            ];

            $doctorUsers = DB::table('users')->where('Role', 'Doctor')->get();

            foreach ($doctorUsers as $u) {
                $existingDoc = DB::table('doctors')->where('UserID', $u->UserID)->first();

                if (!$existingDoc && !empty($u->Email)) {
                    $existingDoc = DB::table('doctors')->where('Email', $u->Email)->first();
                    if ($existingDoc) {
                        DB::table('doctors')->where('DoctorID', $existingDoc->DoctorID)->update(['UserID' => $u->UserID]);
                    }
                }

                if (!$existingDoc) {
                    $spec = $doctorSpecs[$u->Username] ?? [
                        'SpecialtyID' => 1,
                        'Specialty' => 'General Practitioner',
                        'Title' => 'MD',
                        'LicenseNumber' => 'LIC-MED-' . $u->UserID,
                        'ClinicRoom' => 'Room 101, Outpatient Clinic'
                    ];

                    DB::table('doctors')->insert([
                        'UserID'          => $u->UserID,
                        'FirstName'       => $u->FirstName,
                        'LastName'        => $u->LastName,
                        'SpecialtyID'     => $spec['SpecialtyID'],
                        'DepartmentID'    => 16,
                        'Title'           => $spec['Title'],
                        'Specialty'       => $spec['Specialty'],
                        'LicenseNumber'   => $spec['LicenseNumber'],
                        'ContactNumber'   => '+63 917 000 ' . str_pad((string)$u->UserID, 4, '0', STR_PAD_LEFT),
                        'Email'           => $u->Email,
                        'ExperienceYears' => 10,
                        'Clinic'          => 'Tupi Municipal Hospital',
                        'ClinicRoom'      => $spec['ClinicRoom'],
                        'ProfileImage'    => '/assets/doctors/default-doctor.png',
                        'Rating'          => 4.90,
                        'ReviewsCount'    => 50,
                        'ConsultationFee' => '500.00',
                        'Bio'             => 'Licensed medical physician at Tupi Municipal Hospital dedicated to clinical healthcare and outpatient consultation.',
                        'Education'       => 'Doctor of Medicine',
                        'Languages'       => 'English, Tagalog, Hiligaynon',
                        'Status'          => 'Available',
                        'CreatedAt'       => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe no-op to protect patient data
    }
};
