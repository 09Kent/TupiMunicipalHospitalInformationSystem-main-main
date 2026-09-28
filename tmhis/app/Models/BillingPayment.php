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

    public function invoice()
    {
        return $this->belongsTo(BillingInvoice::class, 'InvoiceID', 'InvoiceID');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'PatientID', 'PatientID');
    }
}

