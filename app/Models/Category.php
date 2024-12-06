<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;
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
        return $this->hasMany(Product::class, 'category_id');
    }
}
