<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['customer_name', 'customer_email', 'customer_phone', 'status', 'total_amount', 'payment_url','order_unique_number'];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orderItems()
{
    return $this->hasMany(OrderItem::class);
}
}
