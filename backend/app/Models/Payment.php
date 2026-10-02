<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['order_id', 'method', 'status', 'proof_path', 'verified_by', 'verified_at'];

    protected $casts = ['verified_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function statusHistories()
    {
        return $this->hasMany(PaymentStatusHistory::class);
    }

    public function transitionTo(PaymentStatus $status, ?int $changedBy = null): void
    {
        $fromStatus = $this->status;
        $this->update(['status' => $status->value]);
        $this->statusHistories()->create([
            'from_status' => $fromStatus,
            'to_status' => $status->value,
            'changed_by' => $changedBy,
        ]);
    }
}
