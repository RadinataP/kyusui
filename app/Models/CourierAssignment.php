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
        return $this->hasMany(CourierLocation::class, 'assignment_id');
    }
}
