<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;
    protected $table = "Reviews";
    protected $primaryKey = 'review_id';

    public $incrementing = true;
    public $keyType = 'int';
    protected $fillable = ['user_id', 'product_id', 'rating', 'comment', 'parent_id'];
    protected $casts = [
        'rating' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
    public function parent()
    {
        return $this->belongsTo(Review::class, 'parent_id', 'review_id');
    }
    public function replies()
    {
        return $this->hasMany(Review::class, 'parent_id', 'review_id');
    }
    public function isTopLevelReview()
    {
        return $this->parent_id === null && $this->product_id !== null;
    }
}
