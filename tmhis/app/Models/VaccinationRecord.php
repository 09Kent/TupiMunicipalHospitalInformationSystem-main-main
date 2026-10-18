<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DEPRECATED / OUT OF SCOPE
 * Per TMHIS Chapter 1 (System Scope & Delimitations, Section 1.5):
 * "Vaccination and Immunization Registries: The system does not maintain national
 * immunization registries or track childhood vaccination schedules, which are
 * administered through separate Rural Health Unit (RHU) reporting pipelines."
 * Retained strictly for backwards-database reference; not actively routed or exposed in menus.
 */
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
