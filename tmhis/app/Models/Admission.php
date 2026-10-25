<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admission extends Model
{
    use HasFactory;

    protected $table = 'admissions';
    protected $primaryKey = 'AdmissionID';

    protected $guarded = [];

    protected $casts = [
        'AdmissionDate' => 'datetime',
        'DischargeDate' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'PatientID', 'PatientID');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'DoctorID', 'DoctorID');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'AppointmentID', 'AppointmentID');
    }

    public function consultationNote()
    {
        return $this->belongsTo(ConsultationNote::class, 'ConsultationID', 'NoteID');
    }

    public static function generateAdmissionNumber(): string
    {
        $year = date('Y');
        $max = static::where('AdmissionNumber', 'like', "ADM-{$year}-%")->count();
        return sprintf("ADM-%s-%05d", $year, $max + 1);
    }
}
