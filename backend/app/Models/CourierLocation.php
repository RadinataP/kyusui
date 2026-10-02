<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierLocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['assignment_id', 'courier_id', 'latitude', 'longitude', 'accuracy_meters', 'recorded_at', 'idempotency_key'];

    protected $casts = ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'accuracy_meters' => 'decimal:2', 'recorded_at' => 'datetime'];

    public function assignment()
    {
        return $this->belongsTo(CourierAssignment::class, 'assignment_id');
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }
}
