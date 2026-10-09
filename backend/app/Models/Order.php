<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Order extends Model
{
    use SoftDeletes;

    /**
     * Kolom canonical mengikuti `docs/05_KYUSUI_DATABASE_SCHEMA_REBUILT.md`
     * section 11. Nama kolom database dan nama field API sengaja dibuat sama
     * supaya `App\Http\Resources\OrderResource` tidak perlu translate ulang.
     */
    protected $fillable = [
        'order_number',
        'customer_id',
        'order_status',
        'subtotal_amount',
        'delivery_fee',
        'total_amount',
        'delivery_address',
        'delivery_latitude',
        'delivery_longitude',
        'placed_at',
        'processed_at',
        'completed_at',
    ];

    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'delivery_latitude' => 'decimal:7',
        'delivery_longitude' => 'decimal:7',
        'placed_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * `order_number` wajib ada dan unik (05 section 11). Penomoran memakai
     * prediksi auto-increment sehingga tetap mengikuti bentuk
     * `ORD-YYYYMMDD-NNNN` pada contoh 06 section 7.4, dan tabrakan tetap
     * ditolak oleh unique index.
     */
    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if (filled($order->order_number)) {
                return;
            }

            $order->order_number = self::generateOrderNumber($order->placed_at ?? now());
        });
    }

    public static function generateOrderNumber(DateTimeInterface $placedAt): string
    {
        $sequence = (int) static::query()->max('id') + 1;

        return sprintf('ORD-%s-%04d', Carbon::instance($placedAt)->format('Ymd'), $sequence);
    }

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

    public function statusHistories()
    {
        return $this->histories();
    }

    public function scopeLatestPlaced(Builder $query): Builder
    {
        return $query->orderByDesc('placed_at')->orderByDesc('id');
    }
}
