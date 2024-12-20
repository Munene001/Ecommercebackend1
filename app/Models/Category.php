<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;
    protected $table = 'Categories';
    protected $primaryKey = 'category_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['shop_id ', 'categoryname'];
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }
    public function products()
    {
        return $this->belongsToMany(Product::class, 'Product_categories', 'category_id', 'product_id');
    }
}
