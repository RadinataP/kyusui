package com.kyusui.app.core

/**
 * Validasi sisi klien untuk file bukti pembayaran QRIS.
 *
 * Pre-check ini murni untuk umpan masalah lebih awal sebelum jaringan dipakai.
 * Backend tetap validator final: `06_KYUSUI_API_SPECIFICATION_REBUILT.md`
 * section 11.5 menyatakan ukuran maksimum dan daftar format harus berada di
 * konfigurasi backend, bukan konstanta Android.
 *
 * Karena itu Android tidak menetapkan aturan bisnis sendiri di sini. Nilai pada
 * [MAX_SIZE_BYTES] hanya pencerminan aturan yang sudah didokumentasikan pada
 * `08_KYUSUI_PAYMENT_SPECIFICATION_REBUILT.md` section 11.1, dipakai sebagai
 * pre-check UX agar customer mendapat umpan masalah sebelum jaringan dipakai.
 * Ia bukan sumber kebenaran: kalau konfigurasi backend lebih longgar, Android
 * tidak boleh mengubah hasil akhir, dan backend tetap menampilkan penolakan
 * menurut aturannya sendiri.
 *
 * Kegagalan validasi backend tetap ditampilkan apa adanya, dan pre-check ini
 * tidak pernah menandai payment sebagai berhasil.
 */
object ProofUploadPolicy {

    /** Format yang diizinkan oleh specification section 11.1. */
    val ALLOWED_MIME_TYPES: Set<String> = setOf("image/jpeg", "image/png")

    /** MIME yang setara dari picker perangkat, dinormalisasi sebelum dibandingkan. */
    private val ALIASES: Map<String, String> = mapOf(
        "image/jpg" to "image/jpeg",
        "image/pjpeg" to "image/jpeg"
    )

    /**
     * Pre-check ukuran sisi klien, bukan limit backend.
     *
     * Backend menetapkan limit sendiri lewat konfigurasi. Nilai ini hanya
     * mencegah upload yang jelas tidak berguna dan memberi umpan masalah lebih
     * awal kepada customer.
     */
    const val MAX_SIZE_BYTES: Long = 5L * 1024 * 1024

    const val MAX_SIZE_LABEL: String = "5 MB"

    /** Ekstensi yang diterima picker saat pengguna memilih dari penyimpanan. */
    val ALLOWED_EXTENSIONS: Set<String> = setOf("jpg", "jpeg", "png")

    /**
     * Hasil pre-check.
     *
     * Kegagalan di sini tidak pernah mengubah state payment. Customer tetap
     * dapat mencoba upload ulang dengan file yang berbeda.
     */
    sealed interface Result {
        data object Valid : Result

        data class Invalid(val reason: Reason) : Result
    }

    enum class Reason(val message: String) {
        MISSING_MIME(
            "Jenis file tidak dikenali. Pilih file gambar JPG atau PNG."
        ),
        UNSUPPORTED_TYPE(
            "Format tidak didukung. Gunakan JPG atau PNG."
        ),
        EMPTY_FILE(
            "File kosong atau rusak. Pilih file gambar lain."
        ),
        TOO_LARGE(
            "Ukuran file melebihi $MAX_SIZE_LABEL. Kompres atau pilih gambar lain."
        )
    }

    /**
     * Memeriksa candidate sebelum diunggah.
     *
     * Pemeriksaan ini bukan pengganti backend: hasil [Result.Valid] hanya
     * berarti file layak dicoba, bukan berarti upload akan diterima.
     */
    fun validate(
        mimeType: String?,
        sizeBytes: Long,
        fileName: String? = null
    ): Result {
        val normalizedMime = normalizeMimeType(mimeType)
        if (normalizedMime.isNullOrBlank()) return Result.Invalid(Reason.MISSING_MIME)
        if (sizeBytes <= 0L) return Result.Invalid(Reason.EMPTY_FILE)

        // Hanya ada dua sumber kebenaran yang boleh dipakai klien: MIME yang
        // dibaca dari picker, atau ekstensinya kalau picker mengembalikan MIME
        // generik. MIME lain yang bukan `image/*` langsung ditolak karena tidak
        // mungkin menjadi bukti QRIS.
        when {
            normalizedMime in ALLOWED_MIME_TYPES -> Unit
            isImageMime(normalizedMime) -> return Result.Invalid(Reason.UNSUPPORTED_TYPE)
            normalizedMime == MIME_ANY ->
                if (!hasAllowedExtension(fileName)) return Result.Invalid(Reason.UNSUPPORTED_TYPE)
            else -> return Result.Invalid(Reason.UNSUPPORTED_TYPE)
        }

        if (sizeBytes > MAX_SIZE_BYTES) return Result.Invalid(Reason.TOO_LARGE)

        return Result.Valid
    }

    fun isAllowed(mimeType: String?): Boolean =
        normalizeMimeType(mimeType) in ALLOWED_MIME_TYPES

    private fun normalizeMimeType(mimeType: String?): String? {
        val raw = mimeType?.trim()?.lowercase()?.takeIf { it.isNotEmpty() } ?: return null
        return ALIASES[raw] ?: raw
    }

    private fun hasAllowedExtension(fileName: String?): Boolean {
        val extension = fileName?.substringAfterLast('.', "")?.lowercase() ?: return false
        return extension in ALLOWED_EXTENSIONS
    }

    private fun isImageMime(normalizedMime: String): Boolean =
        normalizedMime.startsWith("image/")

    /** MIME generik yang kadang dikembalikan content resolver. */
    const val MIME_ANY: String = "application/octet-stream"
}