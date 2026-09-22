<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaboratoryRequest extends Model
{
    use HasFactory;

    protected $table = 'laboratory_requests';
    protected $primaryKey = 'RequestID';

    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = null;

    protected $guarded = [];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'PatientID', 'PatientID');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'DoctorID', 'DoctorID');
    }

    public function samples()
    {
        return $this->hasMany(LaboratorySample::class, 'RequestID', 'RequestID');
    }

    public function results()
    {
        return $this->hasMany(LaboratoryResult::class, 'RequestID', 'RequestID');
    }
}
