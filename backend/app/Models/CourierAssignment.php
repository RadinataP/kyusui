<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Model;

class CourierAssignment extends Model
{
    protected $fillable = ['order_id', 'courier_id', 'status', 'assigned_at', 'started_at', 'completed_at'];

    protected $casts = [
        'status' => AssignmentStatus::class,
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public function locations()
    {
        return $this->hasMany(CourierLocation::class, 'courier_assignment_id');
    }

    /**
     * Lokasi terbaru untuk assignment ini.
     *
     * Audit P2-06: `latest_location` sebelumnya memakai `->last()` pada
     * relasi `locations` yang tidak berurutan, sehingga bisa mengembalikan
     * baris tertua. Audit P2-05: relasi itu juga memuat seluruh riwayat
     * lokasi ke dalam setiap item daftar assignment. `latestOfMany()` dengan
     * primary key sebagai pengurutan kedua membuat hasilnya deterministik
     * sekaligus membatasi payload ke satu baris.
     */
    public function latestLocation()
    {
        return $this->hasOne(CourierLocation::class, 'courier_assignment_id')
            ->latestOfMany('recorded_at');
    }
}
