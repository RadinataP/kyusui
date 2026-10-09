<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi singleton Berkah Water.
 *
 * `docs/13_KYUSUI_DATABASE_FINALIZATION.md` section 21.2 menetapkan satu
 * logical record dengan `id = 1` yang menyimpan `qris_image`; section 22
 * mewajibkan `updated_by` sebagai jejak audit actor saat QRIS diganti.
 *
 * Karena singleton, baris `id = 1` dibuat otomatis oleh migration bila belum
 * ada, sehingga pemanggilan `active()` tidak pernah menghasilkan `null` dari
 * ketiadaan baris.
 */
class BusinessSetting extends Model
{
    public const SINGLETON_ID = 1;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = ['id', 'qris_image', 'updated_by'];

    protected $casts = ['id' => 'integer'];

    public static function active(): self
    {
        return static::query()->findOrFail(self::SINGLETON_ID);
    }

    public function hasActiveQris(): bool
    {
        return filled($this->qris_image);
    }

    /**
     * `updated_by` adalah audit actor penggantian QRIS, bukan payment
     * verification actor (13_DB section 22).
     */
    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
