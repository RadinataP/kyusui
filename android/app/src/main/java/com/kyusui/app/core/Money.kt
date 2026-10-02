package com.kyusui.app.core

import java.math.BigDecimal
import java.math.RoundingMode
import java.text.DecimalFormat
import java.text.DecimalFormatSymbols
import java.util.Locale

/**
 * Format nilai uang yang datang dari backend sebagai decimal string, misalnya
 * `"16000.00"`.
 *
 * Android tidak pernah menjadi authority untuk nilai uang. Nilai yang tampil
 * selalu nilai yang dikirim backend. Fungsi [previewSubtotal] hanya untuk
 * preview di layar sebelum order dikirim, dan harus selalu ditandai sebagai
 * estimasi.
 */
object Money {

    private val symbols = DecimalFormatSymbols(Locale("id", "ID"))

    private val plainFormat = DecimalFormat("#,##0", symbols)

    fun format(raw: String?): String {
        val value = raw?.trim()?.toBigDecimalOrNullSafe() ?: return "-"
        return "Rp ${plainFormat.format(value.setScale(0, RoundingMode.HALF_UP))}"
    }

    /**
     * Estimasi subtotal untuk preview UX. Bukan nilai authoritative; subtotal,
     * delivery fee, dan total final dihitung ulang oleh backend.
     */
    fun previewSubtotal(lines: List<PreviewLine>): String? {
        if (lines.isEmpty()) return null
        val total = lines.fold(BigDecimal.ZERO) { acc, line ->
            val price = line.unitPrice?.trim()?.toBigDecimalOrNullSafe() ?: return null
            acc.add(price.multiply(BigDecimal(line.quantity)))
        }
        return total.setScale(2, RoundingMode.HALF_UP).toPlainString()
    }

    fun isValid(raw: String?): Boolean = raw?.trim()?.toBigDecimalOrNullSafe() != null

    private fun String.toBigDecimalOrNullSafe(): BigDecimal? =
        runCatching { BigDecimal(this) }.getOrNull()
}

data class PreviewLine(
    val unitPrice: String?,
    val quantity: Int
)
