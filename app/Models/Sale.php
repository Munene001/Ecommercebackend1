<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;
    protected $table = 'Sales';
    protected $primaryKey = 'sale_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'shop_id',
        'total_amount',
        'payment_method',
        'mpesa_transaction_id',
        'status',
        'tenant_id',
        'guest_id',


    ];
    protected $casts = [
        'total_amount' => 'decimal:2',
        'payment_method' => 'string',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function guest()
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }
    public function saleItems()
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
