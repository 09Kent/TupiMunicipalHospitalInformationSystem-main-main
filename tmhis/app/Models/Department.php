<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $table = 'departments';
    protected $primaryKey = 'DepartmentID';

    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $guarded = [];

    public function doctors()
    {
        return $this->hasMany(Doctor::class, 'DepartmentID', 'DepartmentID');
    }

    public function specialties()
    {
        return $this->belongsToMany(Specialty::class, 'department_specialties', 'DepartmentID', 'SpecialtyID')
            ->withPivot('IsPrimary');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_departments', 'DepartmentID', 'UserID')
            ->withPivot('IsPrimary');
    }
}
