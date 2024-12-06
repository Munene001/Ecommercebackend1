<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $primaryKey = 'product_id';
    public $incrementing = false;
    public $keyType = 'string';
    public $fillable = ['shop_id', 'category_id', 'productname', 'description', 'price', 'stock_quantity',];
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
    public function productvariants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }
    public function productdescriptions()
    {
        return $this->hasMany(ProductDescription::class, 'product_id');
    }
    public function images()
    {
        return $this->hasMany(Image::class, 'product_id');
    }
}
