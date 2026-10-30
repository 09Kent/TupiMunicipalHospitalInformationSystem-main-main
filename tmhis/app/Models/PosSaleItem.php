<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosSaleItem extends Model
{
    use HasFactory;

    protected $table = 'pos_sale_items';
    protected $primaryKey = 'ItemID';

    protected $fillable = [
        'SaleID',
        'InventoryID',
        'ItemCode',
        'MedicineName',
        'DosageForm',
        'Strength',
        'UnitPrice',
        'Quantity',
        'SubTotal',
    ];

    public function sale()
    {
        return $this->belongsTo(PosSale::class, 'SaleID', 'SaleID');
    }

    public function inventory()
    {
        return $this->belongsTo(PharmacyInventory::class, 'InventoryID', 'InventoryID');
    }
}

