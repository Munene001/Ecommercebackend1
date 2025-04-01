<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecentlyViewed extends Model
{
    use HasFactory;
    protected $table = 'Recently_viewed';

    protected $primaryKey = 'view_id';

    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'viewed_at' => 'datetime'
    ];
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
}
