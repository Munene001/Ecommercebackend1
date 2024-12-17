<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

    protected $table = 'Users';

    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['name', 'email', 'password', 'role'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    const ROLE_BUYER = 'buyer';
    const ROLE_SHOP_OWNER = 'shop_owner';
    public function isBuyer()
    {
        return $this->role === self::ROLE_BUYER;
    }

    public function isShopOwner()
    {
        return $this->role === self::ROLE_SHOP_OWNER;
    }
}
