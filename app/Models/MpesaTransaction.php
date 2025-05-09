<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpesaTransaction extends Model
{
    use HasFactory;
    protected $table = 'Mpesa_transactions';
    protected $primaryKey = 'transaction_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'transaction_id',
        'sale_id',
        'mpesa_checkout_request_id',
        'status',
        'result_desc',
        'amount',
        'phone',
        'shop_id',

    ];
    protected $casts = [
        'status' => 'string',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
    public function shop()
    {
        return $this->belongsto(Shop::class, 'shop_id');
    }
}
