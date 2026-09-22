<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientQueue extends Model
{
    use HasFactory;

    protected $table = 'patient_queue';
    protected $primaryKey = 'QueueID';

    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = null;

    protected $guarded = [];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'PatientID', 'PatientID');
    }

    public function getStatusAttribute()
    {
        return $this->attributes['QueueStatus'] ?? ($this->attributes['Status'] ?? 'Waiting');
    }

    public function setStatusAttribute($value)
    {
        $this->attributes['QueueStatus'] = $value;
    }
}
