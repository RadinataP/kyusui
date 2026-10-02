package com.kyusui.app.core

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

/**
 * Nominal uang selalu datang dari backend sebagai decimal string. Test ini
 * menjaga format tampilan saja, bukan perhitungan authoritative.
 */
class MoneyTest {

    @Test
    fun `decimal string is formatted as rupiah without decimals`() {
        assertEquals("Rp 16.000", Money.format("16000.00"))
        assertEquals("Rp 8.000", Money.format("8000.00"))
        assertEquals("Rp 1.500.000", Money.format("1500000.00"))
        assertEquals("Rp 21.000", Money.format("21000"))
    }

    @Test
    fun `rounding uses half up so display never drops a real amount`() {
        assertEquals("Rp 8.001", Money.format("8000.50"))
        assertEquals("Rp 8.001", Money.format("8000.51"))
    }

    @Test
    fun `zero is shown as rupiah zero`() {
        assertEquals("Rp 0", Money.format("0.00"))
    }

    @Test
    fun `missing or malformed values never crash the screen`() {
        assertEquals("-", Money.format(null))
        assertEquals("-", Money.format(""))
        assertEquals("-", Money.format("bukan-angka"))
    }

    @Test
    fun `preview subtotal multiplies backend prices by quantity`() {
        val subtotal = Money.previewSubtotal(
            listOf(
                PreviewLine(unitPrice = "8000.00", quantity = 2),
                PreviewLine(unitPrice = "5000.00", quantity = 1)
            )
        )

        assertEquals("21000.00", subtotal)
    }

    @Test
    fun `preview subtotal returns null when any price is unusable`() {
        val subtotal = Money.previewSubtotal(
            listOf(
                PreviewLine(unitPrice = "8000.00", quantity = 2),
                PreviewLine(unitPrice = "tidak-valid", quantity = 1)
            )
        )

        assertNull(subtotal)
    }

    @Test
    fun `empty cart has no preview subtotal`() {
        assertNull(Money.previewSubtotal(emptyList()))
    }
}