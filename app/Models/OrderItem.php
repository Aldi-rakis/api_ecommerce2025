<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'product_id','product_size_id', 'quantity','size'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function productSize()
    {
        return $this->belongsTo(ProductSize::class);
    }

    public function sizes()
{
    return $this->hasMany(self::class, 'id', 'id')
                ->select(['id', 'size', 'quantity']);
}
}
