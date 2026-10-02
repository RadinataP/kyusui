<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'method',
        'status',
        'amount',
        'proof_path',
        'idempotency_key',
        'proof_size',
        'proof_mime_type',
        'proof_checksum',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'proof_size' => 'integer',
        'verified_at' => 'datetime',
    ];

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
        $fromStatus = $this->status instanceof PaymentStatus
            ? $this->status
            : PaymentStatus::tryFrom((string) $this->status);
        $allowedTransitions = match ($fromStatus) {
            PaymentStatus::PENDING => [PaymentStatus::WAITING_VERIFICATION, PaymentStatus::PAID],
            PaymentStatus::WAITING_VERIFICATION => [PaymentStatus::PAID, PaymentStatus::PENDING],
            PaymentStatus::PAID, null => [],
        };

        if (! in_array($status, $allowedTransitions, true)) {
            throw new \DomainException(sprintf(
                'Transisi pembayaran dari %s ke %s tidak diizinkan.',
                $fromStatus?->value ?? 'UNKNOWN',
                $status->value,
            ));
        }

        $this->update([
            'status' => $status->value,
            'verified_by' => $status === PaymentStatus::PAID ? $changedBy : null,
            'verified_at' => $status === PaymentStatus::PAID ? now() : null,
        ]);
        $this->statusHistories()->create([
            'from_status' => $fromStatus?->value,
            'to_status' => $status->value,
            'changed_by' => $changedBy,
        ]);
    }
}
