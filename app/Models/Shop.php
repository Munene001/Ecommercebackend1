<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model

{
    use HasFactory;
    protected $table = 'Shops';
    protected $primaryKey = 'shop_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['shopname', 'tenant_id'];
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
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
