<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
    use HasFactory;

    protected $table = 'specialties';
    protected $primaryKey = 'SpecialtyID';

    public $timestamps = false;

    protected $guarded = [];

    public function doctors()
    {
        return $this->hasMany(Doctor::class, 'SpecialtyID', 'SpecialtyID');
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_specialties', 'SpecialtyID', 'DepartmentID')
            ->withPivot('IsPrimary');
    }
}
