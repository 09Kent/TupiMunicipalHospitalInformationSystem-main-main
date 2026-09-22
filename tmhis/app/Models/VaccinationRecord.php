<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaccinationRecord extends Model
{
    protected $table = 'vaccination_records';
    protected $primaryKey = 'VaccinationID';
    public $timestamps = false;

    protected $fillable = [
        'PatientID',
        'DoctorID',
        'VaccineName',
        'DoseNumber',
        'AdministeredDate',
        'BatchNumber',
        'Manufacturer',
        'AdverseReactions',
        'NextDueDate',
        'Notes',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'PatientID', 'PatientID');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'DoctorID', 'DoctorID');
    }
}
