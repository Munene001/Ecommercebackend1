<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;
    protected $table = 'Images';
    protected $primaryKey = 'image_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['product_id', 'image_url', 'position'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
