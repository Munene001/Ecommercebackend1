<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model

{
    use HasFactory;
    protected $table = 'Shops';
    protected $primaryKey = 'shop_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['shopname', 'tenant_id', 'mpesa_payment_method', 'mpesa_paybill_number', 'mpesa_till_number', 'mpesa_phone_number', 'mpesa_business_shortcode', 'mpesa_passkey', 'mpesa_consumer_key', 'mpesa_consumer_secret'];
    protected $casts = [
        'mpesa_payment_method' => 'string',
        'created_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
    public function categories()
    {
        return $this->hasMany(Category::class, 'shop_id');
    }
    public function products()
    {
        return $this->hasMany(Product::class, 'shop_id');
    }
    public function mpesaTransactions()
    {
        return $this->hasMany(MpesaTransaction::class, 'shop_id');
    }
}
