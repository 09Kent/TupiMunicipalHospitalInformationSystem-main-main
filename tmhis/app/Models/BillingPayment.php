<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingPayment extends Model
{
    use HasFactory;

    protected $table = 'billing_payments';
    protected $primaryKey = 'PaymentID';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'paid_at'       => 'datetime',
        'failed_at'     => 'datetime',
        'cancelled_at'  => 'datetime',
        'expires_at'    => 'datetime',
        'verified_at'   => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(BillingInvoice::class, 'InvoiceID', 'InvoiceID');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'PatientID', 'PatientID');
    }
}

