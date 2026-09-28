<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorAssignment extends Model
{
    protected $table = 'doctor_assignments';
    protected $primaryKey = 'AssignmentID';
    public $timestamps = false;

    protected $fillable = [
        'PatientID',
        'DoctorID',
        'PreviousDoctorID',
        'DepartmentID',
        'Status',
        'AssignedBy',
        'AssignedByName',
        'AssignedAt',
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

    public function previousDoctor()
    {
        return $this->belongsTo(Doctor::class, 'PreviousDoctorID', 'DoctorID');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'DepartmentID', 'DepartmentID');
    }
}
