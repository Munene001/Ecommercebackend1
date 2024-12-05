<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;
    protected $primaryKey = 'shop_id';
    public $incrementing = false;
    protected $Keytype = 'string';
    protected $fillable = ['shopname', 'shopowner_id'];
    public function user()
    {
        return $this->belongsTo(User::class, 'shopowner_id');
    }
    public function categories()
    {
        return $this->hasMany(Category::class, 'shop_id');
    }
    public function products()
    {
        return $this->hasMany(Product::class, 'shop_id');
    }
}
