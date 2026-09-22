<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemAuditLog extends Model
{
    use HasFactory;

    protected $table = 'system_audit_logs';
    protected $primaryKey = 'LogID';

    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $user = auth()->user();
            if (empty($model->UserName)) {
                $model->UserName = $user ? ($user->FullName ?? $user->Username ?? 'System Staff') : 'System Staff';
            }
            if (empty($model->UserRole)) {
                $model->UserRole = $user ? ($user->Role ?? 'Staff') : 'Staff';
            }
            if (empty($model->Module)) {
                $model->Module = 'ClinicalSystem';
            }
        });
    }
}

