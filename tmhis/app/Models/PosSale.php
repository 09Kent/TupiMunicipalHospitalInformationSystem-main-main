<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosSale extends Model
{
    use HasFactory;

    protected $table = 'pos_sales';
    protected $primaryKey = 'SaleID';

    protected $fillable = [
        'ReceiptNumber',
        'CustomerType',
        'PatientID',
        'CustomerName',
        'CustomerPhone',
        'SubTotal',
        'DiscountType',
        'DiscountAmount',
        'TotalAmount',
        'AmountReceived',
        'ChangeAmount',
        'PaymentMethod',
        'ReferenceNumber',
        'CashierID',
        'CashierName',
        'Status',
        'Notes',
    ];

    public function items()
    {
        return $this->hasMany(PosSaleItem::class, 'SaleID', 'SaleID');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'PatientID', 'PatientID');
    }
}

