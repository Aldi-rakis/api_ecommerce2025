<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'images', 'size', 'price_flat', 'rating', 'category_id'];

    protected $casts = [
        'images' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    
 
    public function getImageAttribute($images)
    {
        return url('storage/products/' . $images);
    }

    public function sizes(){
        return $this->hasMany(ProductSize::class);

    }

    public function productSizes()
{
    return $this->hasMany(ProductSize::class);
}
}
