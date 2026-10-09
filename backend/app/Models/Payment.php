<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Business payment record. Satu order memiliki paling banyak satu payment
 * record karena `payments.order_id` UNIQUE
 * (`docs/13_KYUSUI_DATABASE_FINALIZATION.md` section 10.1).
 *
 * Kolom mengikuti `docs/13_KYUSUI_DATABASE_FINALIZATION.md` section 9.2.
 * Tidak ada provider field, `idempotency_key`, maupun `payment_transactions`
 * (section 9.3). Retry dan re-upload proof menulis pada row yang sama
 * (section 10.2).
 */
class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'payment_method',
        'payment_status',
        'amount',
        'proof_image',
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

    /**
     * Satu-satunya writer status pembayaran. Transisi di bawah mengikuti
     * `docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 12.3 dan
     * `docs/13_KYUSUI_DATABASE_FINALIZATION.md` section 10.2:
     *
     * ```text
     * PENDING              → WAITING_VERIFICATION | PAID
     * WAITING_VERIFICATION → PAID | PENDING
     * ```
     *
     * `verified_by` dan `verified_at` hanya diisi saat payment menjadi `PAID`.
     */
    public function transitionTo(PaymentStatus $status, ?int $changedBy = null): void
    {
        $fromStatus = $this->payment_status instanceof PaymentStatus
            ? $this->payment_status
            : PaymentStatus::tryFrom((string) $this->payment_status);
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

        // Audit P3-07: tabel transisi mengizinkan `PENDING -> PAID` untuk kedua
        // metode, tetapi QRIS tidak boleh diverifikasi tanpa bukti transfer.
        // `CASH` sengaja tetap tanpa proof karena kurir yang mengonfirmasi di
        // lapangan (spec 06 section 12.4).
        if ($this->payment_method === PaymentMethod::QRIS->value
            && $status !== PaymentStatus::PENDING
            && blank($this->proof_image)) {
            throw new \DomainException('Pembayaran QRIS tidak dapat diverifikasi tanpa bukti transfer.');
        }

        $this->update([
            'payment_status' => $status->value,
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
