<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;
    protected $table = 'Product_variants';

    protected $primaryKey = 'variant_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['product_id', 'size', 'stock_quantity',];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
