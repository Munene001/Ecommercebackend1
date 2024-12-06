<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductDescription extends Model
{
    use HasFactory;
    protected $primaryKey = 'description_id';
    protected $Keytype = 'string';
    protected $incrementing = false;
    public $fillable = ['product_id', 'section_name', 'description'];
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
