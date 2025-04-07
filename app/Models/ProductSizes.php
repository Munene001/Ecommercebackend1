<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSizes extends Model
{
    use HasFactory;
    protected $table = 'Product_sizes';

    // Define the primary key
    protected $primaryKey = 'size_id';

    // Indicate that the primary key is auto-incrementing
    public $incrementing = true;

    // Primary key is an integer (per your DESC)
    public $keyType = 'int';

    // Define fillable fields for mass assignment
    protected $fillable = [
        'product_id',
        'size',
        'stock_quantity',
        'sku',
    ];

    // Timestamps are managed manually (only created_at exists)
    public $timestamps = false;

    // Define the relationship with Product
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
}
