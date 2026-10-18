<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'UserID';
    public $incrementing = true;
    protected $keyType = 'int';

    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = ['FirstName', 'LastName', 'Username', 'PasswordHash', 'Role', 'Email', 'Status', 'remember_token', 'CreatedAt', 'UpdatedAt'];

    protected $hidden = [
        'PasswordHash',
    ];

    public function getAuthPassword()
    {
        return $this->PasswordHash;
    }

    public function doctor()
    {
        return $this->hasOne(Doctor::class, 'UserID', 'UserID');
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'user_departments', 'UserID', 'DepartmentID')
            ->withPivot('IsPrimary');
    }

    public function getFullNameAttribute()
    {
        $name = trim(($this->FirstName ?? '') . ' ' . ($this->LastName ?? ''));
        return $name ?: ($this->Username ?? 'User #' . $this->UserID);
    }

    public function hasRole(...$roles): bool
    {
        $userRole = strtolower(trim($this->Role ?? ''));
        if (empty($userRole)) {
            return false;
        }

        $flattened = [];
        foreach ($roles as $role) {
            if (is_array($role)) {
                foreach ($role as $r) {
                    $flattened[] = strtolower(trim((string)$r));
                }
            } else {
                $flattened[] = strtolower(trim((string)$role));
            }
        }

        foreach ($flattened as $req) {
            if ($userRole === $req) {
                return true;
            }
            $aliases = match ($req) {
                'admin' => ['admin'],
                'chief', 'director', 'cmo' => ['chief', 'director', 'cmo'],
                'records', 'records_officer', 'mro' => ['records', 'records_officer', 'mro'],
                'register', 'registrator', 'admitting' => ['register', 'registrator', 'admitting'],
                'doctor', 'physician' => ['doctor', 'physician'],
                'nurse' => ['nurse'],
                'medtech', 'laboratory' => ['medtech', 'medical_technologist', 'laboratory'],
                'pharmacy', 'pharmacist' => ['pharmacy', 'pharmacist'],
                'billing', 'cashier', 'accountant' => ['billing', 'cashier', 'accountant'],
                default => [$req]
            };
            if (in_array($userRole, $aliases, true)) {
                return true;
            }
        }

        return false;
    }
}
