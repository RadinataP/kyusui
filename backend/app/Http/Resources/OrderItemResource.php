<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Order Item Resource.
 *
 * Bentuk mengikuti `docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 7.4:
 * setiap item menyimpan `product_name` sebagai snapshot supaya histori order
 * tetap terbaca walaupun produk dihapus atau diubah setelahnya
 * (`docs/05_KYUSUI_DATABASE_SCHEMA_REBUILT.md` section 12).
 */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'quantity' => (int) $this->quantity,
            'unit_price' => $this->unit_price,
            'line_total' => $this->line_total,
        ];
    }
}
