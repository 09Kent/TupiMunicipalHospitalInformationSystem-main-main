<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('appointments as a')
            ->join('doctors as d', 'd.DoctorID', '=', 'a.DoctorID')
            ->join('patients as p', 'p.PatientID', '=', 'a.PatientID')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('billing_charges as bc')
                    ->where('bc.source_type', 'consultation')
                    ->whereColumn('bc.source_id', 'a.AppointmentID');
            })
            ->select(['a.AppointmentID', 'a.PatientID', 'd.DoctorID', 'd.FirstName', 'd.LastName', 'd.SpecialtyID'])
            ->orderBy('a.AppointmentID')
            ->get()
            ->each(function ($appointment) {
                $feeCode = (int)$appointment->SpecialtyID === 1 ? 'FEE-CON-GP' : 'FEE-CON-SP';
                $fee = DB::table('service_fees')
                    ->where('ServiceCode', $feeCode)
                    ->where('Category', 'Consultation')
                    ->where('Status', 'Active')
                    ->first();

                if (!$fee) {
                    return;
                }

                $rate = (float)$fee->StandardRate;
                DB::table('billing_charges')->insert([
                    'PatientID' => $appointment->PatientID,
                    'AppointmentID' => $appointment->AppointmentID,
                    'FeeID' => $fee->FeeID,
                    'ChargeCategory' => 'Consultation',
                    'ItemDescription' => $fee->ServiceName . ' - Dr. ' . $appointment->FirstName . ' ' . $appointment->LastName,
                    'Quantity' => 1,
                    'UnitPrice' => $rate,
                    'SubTotal' => $rate,
                    'DiscountAmount' => 0,
                    'NetAmount' => $rate,
                    'BillingStatus' => 'Unbilled',
                    'source_type' => 'consultation',
                    'source_id' => $appointment->AppointmentID,
                    'CreatedBy' => 'Consultation Charge Backfill',
                    'CreatedAt' => now(),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('billing_charges')
            ->where('source_type', 'consultation')
            ->where('CreatedBy', 'Consultation Charge Backfill')
            ->delete();
    }
};