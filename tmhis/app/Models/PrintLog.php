<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintLog extends Model
{
    protected $table = 'print_logs';
    protected $primaryKey = 'LogID';
    public $timestamps = false;

    protected $fillable = [
        'UserID',
        'UserName',
        'UserRole',
        'DocumentType',
        'PatientID',
        'PatientName',
        'RecordID',
        'Action',
        'IPAddress',
        'UserAgent',
        'CreatedAt',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'PatientID', 'PatientID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }
}
