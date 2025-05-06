<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory;
    protected $table = 'Sale_items';
    protected $primaryKey = 'saleitem_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'sale_id',
        'product_id',
        'size_id',
        'quantity',
        'price'
    ];
    protected $casts = [
        'size_id' => 'integer',
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'created_at' => 'datetime',
    ];
    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
    public function product()
    {
        return $this->belongsTo(product::class, 'product_id');
    }
    public function productSize()
    {
        return $this->belongsTo(ProductSizes::class, 'size_id');
    }
}
