<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NurseAssignment extends Model
{
    protected $table = 'nurse_assignments';
    protected $primaryKey = 'AssignmentID';
    public $timestamps = false;

    protected $fillable = [
        'PatientID',
        'DoctorID',
        'NurseID',
        'TaskID',
        'TaskDescription',
        'ScheduledTime',
        'Priority',
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

    public function nurse()
    {
        return $this->belongsTo(User::class, 'NurseID', 'UserID');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'DoctorID', 'DoctorID');
    }

    public function task()
    {
        return $this->belongsTo(NurseTask::class, 'TaskID', 'TaskID');
    }
}
