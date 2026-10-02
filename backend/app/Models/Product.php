<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'description', 'price', 'availability'];

    protected $casts = ['price' => 'decimal:2', 'availability' => 'boolean'];

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
