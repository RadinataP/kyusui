<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Histori lokasi courier (`docs/05_KYUSUI_DATABASE_SCHEMA_REBUILT.md` section 17).
 *
 * `courier_assignment_id` memakai composite index `(courier_assignment_id, recorded_at)`
 * untuk query latest-location.
 */
class CourierLocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['courier_assignment_id', 'courier_id', 'latitude', 'longitude', 'accuracy_meters', 'recorded_at'];

    protected $casts = ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'accuracy_meters' => 'decimal:2', 'recorded_at' => 'datetime'];

    public function assignment()
    {
        return $this->belongsTo(CourierAssignment::class, 'courier_assignment_id');
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }
}
