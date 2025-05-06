<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;
    protected $table = 'Guests';
    protected $primaryKey = 'guest_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'location'
    ];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    public function sales()
    {
        return $this->hasMany(Sale::class, 'guest_id');
    }
}
