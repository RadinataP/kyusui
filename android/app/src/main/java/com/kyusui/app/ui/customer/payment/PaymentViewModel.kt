package com.kyusui.app.ui.customer.payment

import android.net.Uri
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.kyusui.app.core.ProofUploadPolicy
import com.kyusui.app.core.Result
import com.kyusui.app.data.upload.ProofFileSource
import com.kyusui.app.domain.model.ActiveQris
import com.kyusui.app.domain.model.Payment
import com.kyusui.app.domain.model.PaymentMethod
import com.kyusui.app.domain.model.PaymentStatus
import com.kyusui.app.domain.model.ProofImageSelection
import com.kyusui.app.domain.repository.CustomerRepository
import com.kyusui.app.ui.customer.toLoadError
import com.kyusui.app.ui.customer.toSubmitError
import com.kyusui.app.ui.state.LoadState
import com.kyusui.app.ui.state.SubmitState
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * State pembayaran untuk satu order.
 *
 * [payment] selalu merupakan hasil baca backend. Android tidak pernah menyusun
 * payment dengan `paymentStatus = PAID` secara lokal: `PAID` hanya bisa muncul
 * dari `GET /customer/orders/{order}/payment` setelah Owner memverifikasi QRIS
 * atau Courier mengonfirmasi CASH.
 */
data class PaymentUiState(
    val orderId: String,
    val orderTotal: String? = null,
    val paymentState: LoadState<Payment> = LoadState.Loading,
    val qrisState: LoadState<ActiveQris> = LoadState.Loading,
    val selectState: SubmitState = SubmitState.Idle,
    val uploadState: SubmitState = SubmitState.Idle,
    val selectedProof: ProofImageSelection? = null,
    val proofRejectionMessage: String? = null
) {
    val payment: Payment? get() = (paymentState as? LoadState.Success)?.data

    val activeQris: ActiveQris? get() = (qrisState as? LoadState.Success)?.data

    val isSelecting: Boolean get() = selectState is SubmitState.Submitting

    val isUploading: Boolean get() = uploadState is SubmitState.Submitting

    val method: PaymentMethod? get() = payment?.paymentMethod

    val status: PaymentStatus? get() = payment?.paymentStatus

    /** `true` hanya bila backend melaporkan `PAID`. Tidak pernah diset lokal. */
    val isPaid: Boolean get() = status == PaymentStatus.PAID

    val isAwaitingVerification: Boolean
        get() = status == PaymentStatus.WAITING_VERIFICATION

    val isPending: Boolean get() = status == PaymentStatus.PENDING

    /**
     * QRIS belum dikonfigurasi bila backend menjawab `404` pada
     * `GET /customer/payment/qris`. Ini kondisi konfigurasi bisnis, bukan
     * kegagalan jaringan, jadi pesannya dibuat terpisah dari error umum.
     */
    val isQrisNotConfigured: Boolean
        get() = (qrisState as? LoadState.Error)?.isNotFound == true

    /**
     * Menentukan apakah upload boleh dilakukan.
     *
     * Hanya `QRIS` + `PENDING` yang memenuhi. `PAID` dan
     * `WAITING_VERIFICATION` sama-sama menolak proof baru.
     */
    val canUploadProof: Boolean
        get() = payment?.canUploadProof == true && selectedProof != null

    /**
     * Proof sebelumnya ditolak Owner sehingga payment kembali `PENDING` dengan
     * proof lama masih tercatat. Menampilkan ajakan upload ulang.
     */
    val needsReupload: Boolean get() = payment?.isRejectedProofPendingReupload == true

    val hasProofSelected: Boolean get() = selectedProof != null

    /** QRIS belum boleh dipilih ketika backend belum punya konfigurasi aktif. */
    val isQrisSelectable: Boolean
        get() = qrisState is LoadState.Success && activeQris?.isDisplayable == true

    /**
     * Payment belum pernah dibuat untuk order ini.
     *
     * `GET /customer/orders/{order}/payment` menjawab `404` sebelum customer
     * memilih metode. Itu kondisi normal di layar pemilihan metode, bukan
     * kegagalan, jadi Android tidak menampilkannya sebagai error.
     */
    val hasPayment: Boolean get() = payment != null

    /**
     * Menentukan apakah sebuah method boleh dikirim ke backend.
     *
     * QRIS hanya boleh dipilih bila backend memang punya QRIS aktif yang bisa
     * ditampilkan. Kalau payment sudah tercatat sebagai QRIS, pilihan tetap
     * dibuka supaya customer bisa menyelesaikan pembayaran yang sudah berjalan
     * walaupun konfigurasi berubah setelah payment dibuat.
     */
    fun canSelect(method: PaymentMethod): Boolean = when (method) {
        PaymentMethod.QRIS -> isQrisSelectable || this.method == PaymentMethod.QRIS
        PaymentMethod.CASH -> true
    }
}

/**
 * Semua operasi mengikuti aturan refresh pada payment specification section 24:
 * aksi pengguna -> request -> backend -> state otoritatif -> update Android.
 * Tidak ada jalur yang menetapkan status payment secara optimistis.
 */
@HiltViewModel
class PaymentViewModel @Inject constructor(
    savedStateHandle: SavedStateHandle,
    private val customerRepository: CustomerRepository,
    private val proofFileSource: ProofFileSource
) : ViewModel() {

    private val orderId: String = savedStateHandle.get<String>(ARG_ORDER_ID).orEmpty()

    private val _uiState = MutableStateFlow(
        PaymentUiState(
            orderId = orderId,
            orderTotal = savedStateHandle[ARG_ORDER_TOTAL]
        )
    )
    val uiState: StateFlow<PaymentUiState> = _uiState.asStateFlow()

    init {
        refreshPayment()
        loadActiveQris()
    }

    /**
     * Membaca ulang payment dari backend.
     *
     * Dipanggil setelah upload, pemilihan method, dan saat layar dibuka,
     * karena state lokal tidak boleh menjadi payment source of truth.
     */
    fun refreshPayment() {
        if (orderId.isBlank()) {
            _uiState.update {
                it.copy(paymentState = LoadState.Error(message = MISSING_ORDER_ID_MESSAGE))
            }
            return
        }

        _uiState.update { it.copy(paymentState = LoadState.Loading) }

        viewModelScope.launch {
            when (val result = customerRepository.getPayment(orderId)) {
                is Result.Success -> _uiState.update {
                    it.copy(paymentState = LoadState.Success(result.data))
                }
                is Result.Failure -> {
                    val error = result.toLoadError()
                    _uiState.update {
                        it.copy(
                            paymentState = if (error.isNotFound) {
                                // 404 berarti payment untuk order ini belum
                                // dibuat. Itu kondisi normal sebelum customer
                                // memilih metode, bukan error yang perlu retry.
                                LoadState.Empty()
                            } else {
                                error
                            }
                        )
                    }
                }
            }
        }
    }

    /**
     * Mengambil QRIS aktif dari backend.
     *
     * Android tidak menyimpan QRIS sebagai source of truth dan tidak
     * meng-hardcode QRIS aktif.
     */
    fun loadActiveQris() {
        _uiState.update { it.copy(qrisState = LoadState.Loading) }

        viewModelScope.launch {
            when (val result = customerRepository.getActiveQris()) {
                is Result.Success -> _uiState.update {
                    it.copy(qrisState = LoadState.Success(result.data))
                }
                is Result.Failure -> {
                    val error = result.toLoadError()
                    _uiState.update {
                        it.copy(
                            qrisState = if (error.isNotFound) {
                                // 404 berarti Owner belum mengonfigurasi
                                // QRIS, bukan error jaringan.
                                LoadState.Error(
                                    message = QRIS_NOT_CONFIGURED_MESSAGE,
                                    isRetryable = false,
                                    // Harus tetap ditandai 404 supaya
                                    // `isQrisNotConfigured` mengenali kondisi ini
                                    // dan menahan pemilihan QRIS.
                                    isNotFound = true
                                )
                            } else {
                                error
                            }
                        )
                    }
                }
            }
        }
    }

    /**
     * Memilih `QRIS` atau `CASH` untuk order ini.
     *
     * Backend membuat atau mengambil satu business payment dan menetapkan
     * `PENDING`. Client hanya mengirim method dan tidak pernah mengirim status.
     *
     * @return true bila request benar-benar dikirim.
     */
    fun selectMethod(method: PaymentMethod): Boolean {
        if (_uiState.value.isSelecting || orderId.isBlank()) return false

        // Penjaga sebelum request: jangan kirim QRIS ketika backend tidak punya
        // QRIS aktif. Backend tetap validator final, tetapi menahan di sini
        // mencegah customer menyelesaikan langkah yang pasti ditolak.
        if (!_uiState.value.canSelect(method)) {
            _uiState.update {
                it.copy(
                    selectState = SubmitState.Error(
                        message = when (method) {
                            PaymentMethod.QRIS -> QRIS_NOT_CONFIGURED_MESSAGE
                            PaymentMethod.CASH -> MISSING_ORDER_ID_MESSAGE
                        },
                        isRetryable = method == PaymentMethod.QRIS
                    )
                )
            }
            return false
        }

        _uiState.update { it.copy(selectState = SubmitState.Submitting) }

        viewModelScope.launch {
            when (
                val result = customerRepository.selectPaymentMethod(
                    orderId = orderId,
                    method = method
                )
            ) {
                is Result.Success -> _uiState.update {
                    it.copy(
                        // Status di sini berasal dari backend. Bila backend
                        // mengembalikan PAID, itu keputusan backend.
                        paymentState = LoadState.Success(result.data),
                        selectState = SubmitState.Success(orderId = orderId)
                    )
                }
                is Result.Failure -> _uiState.update {
                    it.copy(selectState = result.toSubmitError())
                }
            }
        }

        return true
    }

    /**
     * Membaca file dari picker lalu menjalankan pre-check sisi klien.
     *
     * Backend tetap validator final; pre-check hanya mencegah request yang
     * pasti ditolak dan tidak pernah mengubah state payment.
     */
    fun onProofPicked(uri: Uri) {
        val selection = proofFileSource.readSelection(uri)

        if (selection == null) {
            _uiState.update {
                it.copy(
                    selectedProof = null,
                    proofRejectionMessage = UNREADABLE_PROOF_MESSAGE
                )
            }
            return
        }

        when (val validation = ProofUploadPolicy.validate(
            mimeType = selection.mimeType,
            sizeBytes = selection.sizeBytes,
            fileName = selection.displayName
        )) {
            is ProofUploadPolicy.Result.Invalid -> _uiState.update {
                it.copy(selectedProof = null, proofRejectionMessage = validation.reason.message)
            }
            ProofUploadPolicy.Result.Valid -> _uiState.update {
                it.copy(selectedProof = selection, proofRejectionMessage = null)
            }
        }
    }

    fun clearProofSelection() {
        _uiState.update { it.copy(selectedProof = null, proofRejectionMessage = null) }
    }

    /**
     * Mengunggah bukti QRIS sebagai multipart.
     *
     * Sukses upload berarti `WAITING_VERIFICATION`, tidak pernah `PAID`.
     * Owner yang memutuskan apakah bukti diterima.
     *
     * @return true bila request benar-benar dikirim.
     */
    fun uploadProof(): Boolean {
        val state = _uiState.value
        val proof = state.selectedProof ?: return false
        if (state.isUploading) return false

        // Penjaga terakhir sebelum request: payment harus masih PENDING.
        // Kalau backend sudah mengubah status (misalnya Owner memverifikasi
        // saat customer masih di layar), request tidak perlu dikirim.
        if (state.payment?.canUploadProof != true) {
            _uiState.update {
                it.copy(
                    uploadState = SubmitState.Error(
                        message = UPLOAD_NOT_ALLOWED_MESSAGE,
                        isRetryable = false
                    )
                )
            }
            return false
        }

        _uiState.update { it.copy(uploadState = SubmitState.Submitting) }

        viewModelScope.launch {
            when (val result = customerRepository.uploadQrIsProof(orderId, proof)) {
                is Result.Success -> _uiState.update {
                    it.copy(
                        // Hasil upload hanya WAITING_VERIFICATION. State di
                        // bawah dibaca apa adanya dari backend; tidak ada
                        // optimistic PAID di jalur mana pun.
                        paymentState = LoadState.Success(result.data),
                        uploadState = SubmitState.Success(orderId = orderId),
                        // Proof sudah ada di backend, jadi pilihan lokal
                        // dibuang agar tidak terkirim ulang tanpa sengaja.
                        selectedProof = null,
                        proofRejectionMessage = null
                    )
                }
                is Result.Failure -> _uiState.update {
                    it.copy(uploadState = result.toSubmitError())
                }
            }
        }

        return true
    }

    fun clearSubmitStates() {
        _uiState.update {
            it.copy(selectState = SubmitState.Idle, uploadState = SubmitState.Idle)
        }
    }

    companion object {
        // Nilainya harus sama dengan nama argumen navigasi `orderId` supaya
        // SavedStateHandle benar-benar menerima nilainya.
        const val ARG_ORDER_ID = "orderId"
        const val ARG_ORDER_TOTAL = "orderTotal"

        const val QRIS_NOT_CONFIGURED_MESSAGE =
            "QRIS belum dikonfigurasi. Silakan hubungi pemilik toko."
        const val UNREADABLE_PROOF_MESSAGE =
            "File tidak dapat dibaca. Pilih file gambar JPG atau PNG."
        const val UPLOAD_NOT_ALLOWED_MESSAGE =
            "Status pembayaran saat ini tidak menerima bukti baru."
        const val MISSING_ORDER_ID_MESSAGE =
            "Order tidak valid. Kembali ke riwayat pesanan."
    }
}