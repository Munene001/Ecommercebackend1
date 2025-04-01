<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductDescription extends Model
{
    use HasFactory;
    protected $table = 'Product_descriptions';
    protected $primaryKey = 'description_id';
    protected $Keytype = 'string';
    public $incrementing = false;
    public $fillable = ['product_id', 'short_description', 'additional_information'];
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
