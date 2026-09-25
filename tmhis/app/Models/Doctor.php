<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;

    protected $table = 'doctors';
    protected $primaryKey = 'DoctorID';

    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = null;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'DepartmentID', 'DepartmentID');
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class, 'SpecialtyID', 'SpecialtyID');
    }

    public function specialties()
    {
        return $this->belongsToMany(Specialty::class, 'doctor_specialties', 'DoctorID', 'SpecialtyID')
            ->withPivot('IsPrimary');
    }
}
