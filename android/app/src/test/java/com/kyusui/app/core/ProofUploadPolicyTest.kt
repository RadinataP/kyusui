package com.kyusui.app.core

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * PAY-002: pre-check bukti pembayaran.
 *
 * Pre-check ini hanya umpan masalah lebih awal. Backend tetap validator final
 * dan satu-satunya pihak yang boleh menetapkan file diterima.
 */
class ProofUploadPolicyTest {

    @Test
    fun `jpg dan png pada ukuran wajar lolos`() {
        assertTrue(
            ProofUploadPolicy.validate(
                mimeType = "image/jpeg",
                sizeBytes = 1_024L,
                fileName = "bukti.jpg"
            ) is ProofUploadPolicy.Result.Valid
        )
        assertTrue(
            ProofUploadPolicy.validate(
                mimeType = "image/png",
                sizeBytes = 2_048L,
                fileName = "bukti.png"
            ) is ProofUploadPolicy.Result.Valid
        )
    }

    @Test
    fun `alias mime dari picker dinormalisasi`() {
        assertTrue(
            ProofUploadPolicy.validate(
                mimeType = "image/jpg",
                sizeBytes = 512L,
                fileName = "bukti.jpg"
            ) is ProofUploadPolicy.Result.Valid
        )
        assertTrue(
            ProofUploadPolicy.validate(
                mimeType = "IMAGE/PNG",
                sizeBytes = 512L,
                fileName = "bukti.png"
            ) is ProofUploadPolicy.Result.Valid
        )
    }

    @Test
    fun `PAY-002 pdf ditolak`() {
        val result = ProofUploadPolicy.validate(
            mimeType = "application/pdf",
            sizeBytes = 4_096L,
            fileName = "bukti.pdf"
        )

        assertEquals(
            ProofUploadPolicy.Reason.UNSUPPORTED_TYPE,
            (result as ProofUploadPolicy.Result.Invalid).reason
        )
    }

    @Test
    fun `PAY-002 format gambar tidak didukung ditolak walau ekstensinya cocok`() {
        // Ekstensi `jpg` tidak boleh dipakai menembus mime `image/webp`,
        // karena backend tetap menolak file seperti ini.
        val result = ProofUploadPolicy.validate(
            mimeType = "image/webp",
            sizeBytes = 4_096L,
            fileName = "bukti.jpg"
        )

        assertEquals(
            ProofUploadPolicy.Reason.UNSUPPORTED_TYPE,
            (result as ProofUploadPolicy.Result.Invalid).reason
        )
    }

    @Test
    fun `mime generik dengan ekstensi yang diizinkan lolos`() {
        assertTrue(
            ProofUploadPolicy.validate(
                mimeType = ProofUploadPolicy.MIME_ANY,
                sizeBytes = 4_096L,
                fileName = "bukti.jpeg"
            ) is ProofUploadPolicy.Result.Valid
        )
    }

    @Test
    fun `PAY-002 mime generik dengan ekstensi tidak dikenal ditolak`() {
        val result = ProofUploadPolicy.validate(
            mimeType = ProofUploadPolicy.MIME_ANY,
            sizeBytes = 4_096L,
            fileName = "bukti.pdf"
        )

        assertEquals(
            ProofUploadPolicy.Reason.UNSUPPORTED_TYPE,
            (result as ProofUploadPolicy.Result.Invalid).reason
        )
    }

    @Test
    fun `mime generik tanpa nama file ditolak karena tidak ada petunjuk format`() {
        val result = ProofUploadPolicy.validate(
            mimeType = ProofUploadPolicy.MIME_ANY,
            sizeBytes = 4_096L,
            fileName = null
        )

        assertEquals(
            ProofUploadPolicy.Reason.UNSUPPORTED_TYPE,
            (result as ProofUploadPolicy.Result.Invalid).reason
        )
    }

    @Test
    fun `mime kosong ditolak`() {
        val result = ProofUploadPolicy.validate(
            mimeType = null,
            sizeBytes = 4_096L,
            fileName = "bukti.jpg"
        )

        assertEquals(
            ProofUploadPolicy.Reason.MISSING_MIME,
            (result as ProofUploadPolicy.Result.Invalid).reason
        )
    }

    @Test
    fun `PAY-002 file kosong ditolak`() {
        listOf(0L, -1L).forEach { size ->
            val result = ProofUploadPolicy.validate(
                mimeType = "image/jpeg",
                sizeBytes = size,
                fileName = "bukti.jpg"
            )

            assertEquals(
                ProofUploadPolicy.Reason.EMPTY_FILE,
                (result as ProofUploadPolicy.Result.Invalid).reason
            )
        }
    }

    @Test
    fun `PAY-002 file melebihi batas pre-check ditolak`() {
        val result = ProofUploadPolicy.validate(
            mimeType = "image/jpeg",
            sizeBytes = ProofUploadPolicy.MAX_SIZE_BYTES + 1L,
            fileName = "bukti.jpg"
        )

        assertEquals(
            ProofUploadPolicy.Reason.TOO_LARGE,
            (result as ProofUploadPolicy.Result.Invalid).reason
        )
    }

    @Test
    fun `file tepat di batas pre-check tetap lolos`() {
        assertTrue(
            ProofUploadPolicy.validate(
                mimeType = "image/jpeg",
                sizeBytes = ProofUploadPolicy.MAX_SIZE_BYTES,
                fileName = "bukti.jpg"
            ) is ProofUploadPolicy.Result.Valid
        )
    }

    @Test
    fun `isAllowed mencerminkan daftar format yang diizinkan`() {
        assertTrue(ProofUploadPolicy.isAllowed("image/jpeg"))
        assertTrue(ProofUploadPolicy.isAllowed("image/png"))
        assertEquals(false, ProofUploadPolicy.isAllowed("application/pdf"))
        assertEquals(false, ProofUploadPolicy.isAllowed(null))
    }
}