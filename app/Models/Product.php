<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $table = 'Products';
    protected $primaryKey = 'product_id';
    public $incrementing = false;
    public $keyType = 'string';
    public $fillable = ['shop_id', 'category_id', 'productname', 'description', 'price', 'discountprice', 'status', 'product_type'];
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'Product_categories', 'product_id', 'category_id');
    }
    public function productdescriptions()
    {
        return $this->hasMany(ProductDescription::class, 'product_id');
    }
    public function images()
    {
        return $this->hasMany(Image::class, 'product_id');
    }
    public function recentlyviewed()
    {
        return $this->hasMany(RecentlyViewed::class, 'product_id', 'product_id');
    }
    public function productsizes()
    {
        return $this->hasMany(ProductSizes::class, 'product_id');
    }
    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id', 'product_id')->whereNull('parent_id');
    }
    public function getAverageRatingAttribute()
    {
        return $this->reviews()->avg('rating') ?: 0;
    }
    public function getReviewCountAttribute()
    {
        return $this->reviews()->count();
    }
}
