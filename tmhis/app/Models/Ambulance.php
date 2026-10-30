<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ambulance extends Model
{
    use HasFactory;

    protected $table = 'ambulances';
    protected $primaryKey = 'AmbulanceID';

    protected $guarded = [];

    public function currentPatient()
    {
        return $this->belongsTo(Patient::class, 'CurrentPatientID', 'PatientID');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'AmbulanceID', 'AmbulanceID');
    }
}

