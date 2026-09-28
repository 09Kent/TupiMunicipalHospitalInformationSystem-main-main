<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasFactory;

    protected $table = 'referrals';
    protected $primaryKey = 'ReferralID';

    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $guarded = [];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'PatientID', 'PatientID');
    }

    public function referringDoctor()
    {
        return $this->belongsTo(Doctor::class, 'ReferringDoctorID', 'DoctorID');
    }

    public function targetDoctor()
    {
        return $this->belongsTo(Doctor::class, 'TargetDoctorID', 'DoctorID');
    }

    public function targetSpecialty()
    {
        return $this->belongsTo(Specialty::class, 'TargetSpecialtyID', 'SpecialtyID');
    }
}

