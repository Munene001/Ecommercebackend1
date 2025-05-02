<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;
    protected $table = 'Tenants';
    protected $primaryKey = 'tenant_id';
    public $incrementing = false;
    protected $keyType = 'String';

    protected $fillable = ['tenant_name', 'mobile_number', 'subscription', 'subscription_expiry'];
    protected $casts = [
        'subscription_expiry' => 'date',
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'tenant_id', 'tenant_id');
    }
    public function shops()
    {
        return $this->hasMany(Shop::class, 'tenant_id', 'tenant_id');
    }
    public function sales()
    {
        return $this->hasMany(Sale::class, 'tenant_id', 'tenant_id');
    }
}
