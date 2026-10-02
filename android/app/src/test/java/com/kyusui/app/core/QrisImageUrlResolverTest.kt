package com.kyusui.app.core

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Keamanan QRIS image.
 *
 * `qris_image` berasal dari backend dan tidak boleh dipakai untuk mengarahkan
 * perangkat memuat gambar dari host arbitrer. Resolver harus menolak nilai yang
 * menunjuk ke luar origin API.
 */
class QrisImageUrlResolverTest {

    private val baseUrl = "http://10.0.2.2:8000/api/v1/"

    @Test
    fun `path relatif dikirim ke origin api`() {
        assertEquals(
            "http://10.0.2.2:8000/storage/qris/berkah-water.png",
            QrisImageUrlResolver.resolve("/storage/qris/berkah-water.png", baseUrl)
        )
    }

    @Test
    fun `path relatif tanpa slash depan tetap dikirim ke origin api`() {
        assertEquals(
            "http://10.0.2.2:8000/storage/qris/berkah-water.png",
            QrisImageUrlResolver.resolve("storage/qris/berkah-water.png", baseUrl)
        )
    }

    @Test
    fun `golongan path tetap berada di host api`() {
        val resolved = QrisImageUrlResolver.resolve("/../../etc/passwd", baseUrl)

        // Host tidak boleh berubah walau path mencoba keluar dari segmen api.
        assertTrue(resolved?.startsWith("http://10.0.2.2:8000/") == true)
    }

    @Test
    fun `url absolut pada origin yang sama diterima`() {
        assertEquals(
            "http://10.0.2.2:8000/storage/qris/temporary.png?token=abc",
            QrisImageUrlResolver.resolve(
                "http://10.0.2.2:8000/storage/qris/temporary.png?token=abc",
                baseUrl
            )
        )
    }

    @Test
    fun `PAY-004 url absolut ke host lain ditolak`() {
        assertNull(
            QrisImageUrlResolver.resolve("https://evil.example.com/qris.png", baseUrl)
        )
    }

    @Test
    fun `PAY-004 url ke subdomain berbeda ditolak`() {
        assertNull(
            QrisImageUrlResolver.resolve("http://10.0.2.2.evil.example.com/qris.png", baseUrl)
        )
    }

    @Test
    fun `PAY-004 protocol relative url ditolak`() {
        assertNull(QrisImageUrlResolver.resolve("//evil.example.com/qris.png", baseUrl))
    }

    @Test
    fun `nilai kosong ditolak`() {
        assertNull(QrisImageUrlResolver.resolve(null, baseUrl))
        assertNull(QrisImageUrlResolver.resolve("", baseUrl))
        assertNull(QrisImageUrlResolver.resolve("   ", baseUrl))
    }

    @Test
    fun `base url tanpa segmen path api tetap menghasilkan origin`() {
        assertEquals(
            "https://api.kyusui.example.com/storage/qris/a.png",
            QrisImageUrlResolver.resolve(
                "/storage/qris/a.png",
                "https://api.kyusui.example.com"
            )
        )
    }

    @Test
    fun `PAY-004 scheme huruf besar tidak melewati allowlist host`() {
        // Scheme URL tidak case-sensitive, jadi huruf besar harus tetap
        // diperlakukan sebagai URL absolute dan divalidasi terhadap host.
        assertNull(
            QrisImageUrlResolver.resolve("HTTP://evil.example.com/qris.png", baseUrl)
        )
        assertNull(
            QrisImageUrlResolver.resolve("HTTPS://evil.example.com/qris.png", baseUrl)
        )
        assertEquals(
            "http://10.0.2.2:8000/storage/qris/a.png",
            QrisImageUrlResolver.resolve(
                "HTTP://10.0.2.2:8000/storage/qris/a.png",
                baseUrl
            )
        )
    }
}