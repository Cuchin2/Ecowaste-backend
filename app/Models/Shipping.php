<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipping extends Model
{
    use HasFactory;
    protected $fillable=[
        'name',
        'description',
        'order',
        'price',
        'url',
        'title',
        'state',
        'latitude',
        'longitude',
    ];
    public function saleOrder()
    {
        return $this->hasOne(SaleOrder::class, 'shipping_id');
    }
    public function spanish(){
        switch ($this->attributes['state']) {
            case 'district':
                return 'distrital';
            case 'nacional':
                    return 'nacional';
            default:
                return 'internacional';
        }
    }
    public function currency(){
        switch ($this->attributes['state']) {
            case 'internacional':
                return '$';
            default:
                return 'S/.';
        }
    }
}
