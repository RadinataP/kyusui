<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $timestamps = false;

    /**
     * `product_name` adalah snapshot historis
     * (`docs/05_KYUSUI_DATABASE_SCHEMA_REBUILT.md` section 12) supaya histori
     * order tetap terbaca walaupun produk diubah atau dihapus setelahnya.
     */
    protected $fillable = ['order_id', 'product_id', 'product_name', 'quantity', 'unit_price', 'line_total'];

    protected $casts = ['quantity' => 'integer', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
