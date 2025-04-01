<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'Users';

    protected $primaryKey = 'user_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = ['username', 'email', 'password', 'google_id', 'role', 'email_verified_at', 'remember_token', 'tenant_id'];
    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    const ROLE_BUYER = 'buyer';
    const ROLE_SHOP_OWNER = 'shopowner';

    public function isBuyer()
    {
        return $this->role === self::ROLE_BUYER;
    }

    public function isShopOwner()
    {
        return $this->role === self::ROLE_SHOP_OWNER;
    }
}
