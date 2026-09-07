<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DeliveryOrder extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'name',
        'last_name',
        'address',
        'reference',
        'country',
        'city',
        'state',
        'district',
        // Otros atributos que desees agregar
    ];
    public function saleOrder() : BelongsTo
    {
        return $this->BelongsTo(SaleOrder::class,'order_id');
    }
}
