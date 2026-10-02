package com.kyusui.app.ui.state

/**
 * State untuk layar yang memuat data collection dari backend.
 *
 * `Empty` dipisahkan dari `Success` dengan data kosong supaya UI bisa
 * menampilkan pesan "belum ada data" yang tepat, bukan blank state.
 */
sealed interface LoadState<out T> {
    data object Loading : LoadState<Nothing>

    data class Success<out T>(val data: T) : LoadState<T>

    data class Empty(val message: String = "Belum ada data") : LoadState<Nothing>

    data class Error(
        val message: String,
        val isUnauthorized: Boolean = false,
        val isRetryable: Boolean = true,
        /**
         * True bila backend menjawab 404. Dipisahkan dari [isRetryable] karena
         * 404 pada `GET /customer/payment/qris` berarti Owner belum
         * mengonfigurasi QRIS, yaitu kondisi bisnis yang tidak akan berubah
         * dengan tombol coba lagi.
         */
        val isNotFound: Boolean = false
    ) : LoadState<Nothing>
}

/**
 * State untuk aksi submit (create order).
 *
 * `Submitting` adalah guard duplicate submit: aksi hanya boleh berjalan saat
 * state bukan `Submitting`, sehingga double tap tidak mengirim dua request.
 */
sealed interface SubmitState {
    data object Idle : SubmitState

    data object Submitting : SubmitState

    data class Success(val orderId: String) : SubmitState

    data class Error(
        val message: String,
        val isUnauthorized: Boolean = false,
        val isRetryable: Boolean = true
    ) : SubmitState
}