<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaboratorySample extends Model
{
    use HasFactory;

    protected $table = 'laboratory_samples';
    protected $primaryKey = 'SampleID';

    public $timestamps = false;

    protected $guarded = [];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'PatientID', 'PatientID');
    }

    public function request()
    {
        return $this->belongsTo(LaboratoryRequest::class, 'RequestID', 'RequestID');
    }
}
