package com.kyusui.app.domain.model

/**
 * Payment Resource section 7.5 dan 11.3.
 *
 * Android adalah pure reader untuk `paymentStatus`. Tidak ada operasi client
 * yang menulis `PAID`: upload proof hanya menghasilkan `WAITING_VERIFICATION`,
 * dan `PAID` hanya pernah dibaca dari backend (Owner verification untuk QRIS,
 * konfirmasi Courier untuk CASH).
 */
data class Payment(
    val id: String,
    val orderId: String,
    val paymentMethod: PaymentMethod?,
    val paymentStatus: PaymentStatus?,
    val amount: String,
    val proof: PaymentProof?,
    val verifiedBy: String?,
    val verifiedAt: String?,
    val createdAt: String?,
    val updatedAt: String?
) {
    /**
     * Bukti pembayaran masih bisa diunggah hanya pada `PENDING`.
     *
     * Section 11.4 payment specification: upload diperbolehkan pada `PENDING`
     * dan dilarang pada `PAID`. `WAITING_VERIFICATION` juga menolak upload
     * karena backend menganggap proof sedang diverifikasi Owner.
     */
    val canUploadProof: Boolean
        get() = paymentMethod == PaymentMethod.QRIS &&
            paymentStatus == PaymentStatus.PENDING

    /**
     * Menandai bahwa sebuah proof pernah diunggah lalu ditolak Owner.
     *
     * Rejection pada specification tidak menciptakan status `REJECTED`:
     * `WAITING_VERIFICATION` kembali menjadi `PENDING` dan hanya
     * `verified_by` / `verified_at` yang di-null-kan. Karena itu status `PENDING`
     * dengan proof yang masih tersedia berarti customer perlu mengunggah ulang,
     * sedangkan `PENDING` tanpa proof berarti belum pernah mengunggah.
     */
    val isRejectedProofPendingReupload: Boolean
        get() = paymentMethod == PaymentMethod.QRIS &&
            paymentStatus == PaymentStatus.PENDING &&
            (proof?.available == true)

    val hasProof: Boolean get() = proof?.available == true
}

/**
 * Ringkasan keberadaan bukti pembayaran.
 *
 * Response sengaja hanya memberi `available`. Backend memakai private storage
 * dan tidak mengizinkan file proof menjadi public URL, jadi Android tidak
 * pernah menerima path, URL, atau nama file untuk diunduh sendiri.
 */
data class PaymentProof(
    val available: Boolean
)

/**
 * Active QRIS Resource section 7.6 dan endpoint 11.1.
 *
 * `qrisImage` adalah konfigurasi bisnis milik Berkah Water, bukan data
 * customer dan bukan QRIS dinamis per transaksi. Android tidak menyimpan
 * QRIS sebagai source of truth dan tidak meng-hardcode QRIS aktif.
 */
data class ActiveQris(
    val qrisImage: String?,
    val updatedAt: String?
) {
    /** QRIS hanya bisa ditampilkan bila backend benar-benar memberikan gambar. */
    val isDisplayable: Boolean get() = !qrisImage.isNullOrBlank()
}

/**
 * File bukti pembayaran yang dipilih customer sebelum diunggah.
 *
 * `localUri` hanya dipakai untuk preview di perangkat dan sebagai sumber
 * multipart. Uri ini tidak pernah dikirim sebagai path server dan tidak
 * pernah dicatat ke log.
 */
data class ProofImageSelection(
    val localUri: String,
    val mimeType: String,
    val sizeBytes: Long,
    val displayName: String
)