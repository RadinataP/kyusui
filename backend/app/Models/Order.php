<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['customer_id', 'status', 'subtotal', 'total', 'delivery_address'];

    protected $casts = ['subtotal' => 'decimal:2', 'total' => 'decimal:2'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function assignments()
    {
        return $this->hasMany(CourierAssignment::class);
    }

    public function histories()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }
}
