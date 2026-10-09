<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit trail perubahan status order
 * (`docs/05_KYUSUI_DATABASE_SCHEMA_REBUILT.md` section 13).
 *
 * `changed_by_user_id` boleh NULL untuk perubahan yang dilakukan system
 * process, dan `note` menyimpan alasan opsional dari owner.
 */
class OrderStatusHistory extends Model
{
    protected $fillable = ['order_id', 'from_status', 'to_status', 'changed_by_user_id', 'note', 'changed_at'];

    protected $casts = ['changed_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
