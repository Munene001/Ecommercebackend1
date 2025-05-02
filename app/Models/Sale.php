<?php

namespace App\Models;

use Dreadfulcode\EloquentModelGenerator\Model\BelongsTo;
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
        'mpese_transaction_id',
        'status',
        'tenant_id'

    ];
    protected $casts = [
        'total_amount' => 'deciaml:2',
        'payment_method' => 'string',
        'status' => 'string',
        'created_at' => 'datetime'
    ];
    public function user()
    {
        return $this->BelongsTo(User::class, 'user_id');
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
