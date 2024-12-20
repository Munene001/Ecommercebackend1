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
    protected $Keytype = 'String';

    protected $fillable = ['tenant_name', 'mobile_number', 'subscription', 'subscription_expiry'];
}