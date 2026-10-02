package com.kyusui.app.ui.customer.payment

import android.net.Uri
import androidx.lifecycle.SavedStateHandle
import com.kyusui.app.core.NoNetworkException
import com.kyusui.app.core.NotFoundException
import com.kyusui.app.core.Result
import com.kyusui.app.core.ServerException
import com.kyusui.app.core.UnauthorizedException
import com.kyusui.app.data.upload.ProofFileSource
import com.kyusui.app.domain.model.ActiveQris
import com.kyusui.app.domain.model.Payment
import com.kyusui.app.domain.model.PaymentMethod
import com.kyusui.app.domain.model.PaymentProof
import com.kyusui.app.domain.model.PaymentStatus
import com.kyusui.app.domain.model.ProofImageSelection
import com.kyusui.app.domain.repository.CustomerRepository
import com.kyusui.app.ui.state.LoadState
import com.kyusui.app.ui.state.SubmitState
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.mockk
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * PAY-001 s.d. PAY-006 dan CASH-001 s.d. CASH-003.
 *
 * Aturan utama yang diuji di sini: status payment selalu berasal dari backend.
 * Tidak ada jalur di ViewModel yang menulis `PAID` secara lokal.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class PaymentViewModelTest {

    private val dispatcher = StandardTestDispatcher()
    private lateinit var repository: CustomerRepository
    private lateinit var proofFileSource: ProofFileSource

    @Before
    fun setup() {
        Dispatchers.setMain(dispatcher)
        repository = mockk(relaxed = true)
        proofFileSource = mockk(relaxed = true)
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    // --- Helper -------------------------------------------------------------

    private fun viewModel(orderId: String = ORDER_ID) = PaymentViewModel(
        savedStateHandle = SavedStateHandle(mapOf(PaymentViewModel.ARG_ORDER_ID to orderId)),
        customerRepository = repository,
        proofFileSource = proofFileSource
    )

    private fun payment(
        method: PaymentMethod? = PaymentMethod.QRIS,
        status: PaymentStatus? = PaymentStatus.PENDING,
        proof: PaymentProof? = null
    ) = Payment(
        id = "9",
        orderId = ORDER_ID,
        paymentMethod = method,
        paymentStatus = status,
        amount = "21000.00",
        proof = proof,
        verifiedBy = null,
        verifiedAt = null,
        createdAt = "2026-01-01T00:00:00Z",
        updatedAt = null
    )

    private fun givenPayment(value: Payment?) {
        coEvery { repository.getPayment(ORDER_ID) } returns
            if (value == null) {
                Result.failure(NotFoundException())
            } else {
                Result.success(value)
            }
    }

    private fun givenActiveQris(image: String? = "/storage/qris/berkah-water.png") {
        coEvery { repository.getActiveQris() } returns
            Result.success(ActiveQris(qrisImage = image, updatedAt = null))
    }

    private fun givenNoActiveQris() {
        coEvery { repository.getActiveQris() } returns Result.failure(NotFoundException())
    }

    private fun selection(
        mimeType: String = "image/jpeg",
        sizeBytes: Long = 2_048L
    ) = ProofImageSelection(
        localUri = "content://media/pictures/1",
        mimeType = mimeType,
        sizeBytes = sizeBytes,
        displayName = "bukti.jpg"
    )

    private fun givenPickedProof(
        mimeType: String = "image/jpeg",
        sizeBytes: Long = 2_048L
    ): Uri {
        coEvery { proofFileSource.readSelection(any()) } returns selection(mimeType, sizeBytes)
        return mockk<Uri>()
    }

    // --- PAY-001: hanya QRIS dan CASH ----------------------------------------

    @Test
    fun `PAY-001 hanya ada dua metode pembayaran canonical`() {
        assertEquals(
            setOf(PaymentMethod.QRIS, PaymentMethod.CASH),
            PaymentMethod.entries.toSet()
        )
    }

    @Test
    fun `PAY-001 hanya ada tiga status pembayaran canonical`() {
        assertEquals(
            setOf(
                PaymentStatus.PENDING,
                PaymentStatus.WAITING_VERIFICATION,
                PaymentStatus.PAID
            ),
            PaymentStatus.entries.toSet()
        )
    }

    // --- CASH-001: CASH tidak punya bukti ------------------------------------

    @Test
    fun `CASH-001 cash tidak pernah bisa mengunggah bukti`() {
        assertFalse(payment(method = PaymentMethod.CASH).canUploadProof)
        assertFalse(
            payment(
                method = PaymentMethod.CASH,
                status = PaymentStatus.PENDING,
                proof = PaymentProof(available = true)
            ).canUploadProof
        )
    }

    @Test
    fun `PAY-002 qris pending boleh mengunggah bukti`() {
        assertTrue(payment(method = PaymentMethod.QRIS).canUploadProof)
    }

    @Test
    fun `PAY-006 bukti baru ditolak saat menunggu verifikasi`() {
        assertFalse(payment(status = PaymentStatus.WAITING_VERIFICATION).canUploadProof)
    }

    @Test
    fun `PAY-006 bukti baru ditolak setelah lunas`() {
        assertFalse(payment(status = PaymentStatus.PAID).canUploadProof)
    }

    // --- Rejection: WAITING -> PENDING ---------------------------------------

    @Test
    fun `PAY-005 bukti yang ditolak menunggu upload ulang`() {
        assertTrue(
            payment(
                status = PaymentStatus.PENDING,
                proof = PaymentProof(available = true)
            ).isRejectedProofPendingReupload
        )
    }

    @Test
    fun `bukti yang masih diverifikasi bukan penolakan`() {
        assertFalse(
            payment(
                status = PaymentStatus.WAITING_VERIFICATION,
                proof = PaymentProof(available = true)
            ).isRejectedProofPendingReupload
        )
    }

    // --- QRIS belum dikonfigurasi -------------------------------------------

    @Test
    fun `qris 404 terbaca sebagai belum dikonfigurasi dan tidak retryable`() =
        runTest(dispatcher) {
            givenNoActiveQris()
            givenPayment(payment())

            val vm = viewModel()
            advanceUntilIdle()

            assertTrue(vm.uiState.value.isQrisNotConfigured)
            val qrisState = vm.uiState.value.qrisState as LoadState.Error
            assertFalse(qrisState.isRetryable)
            assertEquals(PaymentViewModel.QRIS_NOT_CONFIGURED_MESSAGE, qrisState.message)
        }

    @Test
    fun `qris tanpa gambar tidak bisa dipilih`() = runTest(dispatcher) {
        givenActiveQris(image = null)
        givenPayment(payment())

        val vm = viewModel()
        advanceUntilIdle()

        assertFalse(vm.uiState.value.isQrisSelectable)
    }

    @Test
    fun `PAY-001 request qris baru ditahan saat backend tidak punya QRIS aktif`() =
        runTest(dispatcher) {
            givenNoActiveQris()
            // Payment belum pernah dibuat, jadi memilih QRIS berarti memulai
            // pembayaran baru yang pasti ditolak backend.
            givenPayment(null)

            val vm = viewModel()
            advanceUntilIdle()

            val sent = vm.selectMethod(PaymentMethod.QRIS)
            advanceUntilIdle()

            assertFalse(sent)
            coVerify(exactly = 0) { repository.selectPaymentMethod(any(), any()) }
            val selectState = vm.uiState.value.selectState as SubmitState.Error
            assertEquals(PaymentViewModel.QRIS_NOT_CONFIGURED_MESSAGE, selectState.message)
        }

    @Test
    fun `payment qris yang sudah berjalan tetap boleh dibuka meski QRIS dicabut`() =
        runTest(dispatcher) {
            givenNoActiveQris()
            // Customer mungkin sudah mengunggah bukti dan hanya menunggu
            // verifikasi. Menahan akses akan membuat pembayaran ini buntu.
            givenPayment(
                payment(
                    method = PaymentMethod.QRIS,
                    status = PaymentStatus.WAITING_VERIFICATION,
                    proof = PaymentProof(available = true)
                )
            )
            coEvery {
                repository.selectPaymentMethod(ORDER_ID, PaymentMethod.QRIS)
            } returns Result.success(
                payment(
                    method = PaymentMethod.QRIS,
                    status = PaymentStatus.WAITING_VERIFICATION,
                    proof = PaymentProof(available = true)
                )
            )

            val vm = viewModel()
            advanceUntilIdle()

            assertTrue(vm.selectMethod(PaymentMethod.QRIS))
            advanceUntilIdle()

            coVerify(exactly = 1) { repository.selectPaymentMethod(ORDER_ID, PaymentMethod.QRIS) }
        }

    @Test
    fun `cash tetap boleh dipilih saat qris belum dikonfigurasi`() = runTest(dispatcher) {
        givenNoActiveQris()
        givenPayment(null)
        coEvery {
            repository.selectPaymentMethod(ORDER_ID, PaymentMethod.CASH)
        } returns Result.success(payment(method = PaymentMethod.CASH))

        val vm = viewModel()
        advanceUntilIdle()

        assertTrue(vm.selectMethod(PaymentMethod.CASH))
        advanceUntilIdle()

        coVerify(exactly = 1) { repository.selectPaymentMethod(ORDER_ID, PaymentMethod.CASH) }
    }

    // --- Memilih metode ------------------------------------------------------

    @Test
    fun `memilih cash hanya mengirim method dan status datang dari backend`() =
        runTest(dispatcher) {
            givenActiveQris()
            givenPayment(null)
            coEvery {
                repository.selectPaymentMethod(ORDER_ID, PaymentMethod.CASH)
            } returns Result.success(payment(method = PaymentMethod.CASH))

            val vm = viewModel()
            advanceUntilIdle()

            assertTrue(vm.selectMethod(PaymentMethod.CASH))
            advanceUntilIdle()

            coVerify(exactly = 1) { repository.selectPaymentMethod(ORDER_ID, PaymentMethod.CASH) }
            assertEquals(PaymentStatus.PENDING, vm.uiState.value.status)
            assertEquals(SubmitState.Success(ORDER_ID), vm.uiState.value.selectState)
        }

    @Test
    fun `kegagalan memilih metode tidak mengubah state payment`() = runTest(dispatcher) {
        givenActiveQris()
        givenPayment(payment())
        coEvery {
            repository.selectPaymentMethod(ORDER_ID, PaymentMethod.QRIS)
        } returns Result.failure(ServerException(statusCode = 409))

        val vm = viewModel()
        advanceUntilIdle()

        vm.selectMethod(PaymentMethod.QRIS)
        advanceUntilIdle()

        assertEquals(PaymentStatus.PENDING, vm.uiState.value.status)
        assertTrue(vm.uiState.value.selectState is SubmitState.Error)
    }

    // --- Payment belum dibuat ----------------------------------------------

    @Test
    fun `payment yang belum dibuat bukan error fatal di layar pemilihan`() =
        runTest(dispatcher) {
            givenActiveQris()
            givenPayment(null)

            val vm = viewModel()
            advanceUntilIdle()

            assertTrue(vm.uiState.value.paymentState is LoadState.Empty)
            assertFalse(vm.uiState.value.hasPayment)
        }

    // --- Upload bukti --------------------------------------------------------

    @Test
    fun `PAY-003 upload hanya tersedia setelah proof lolos pre-check`() =
        runTest(dispatcher) {
            givenActiveQris()
            givenPayment(payment())
            val uri = givenPickedProof()

            val vm = viewModel()
            advanceUntilIdle()

            assertFalse(vm.uiState.value.canUploadProof)

            vm.onProofPicked(uri)

            assertTrue(vm.uiState.value.canUploadProof)
        }

    @Test
    fun `PAY-002 proof yang gagal pre-check tidak pernah dikirim`() = runTest(dispatcher) {
        givenActiveQris()
        givenPayment(payment())
        val uri = givenPickedProof(mimeType = "application/pdf")

        val vm = viewModel()
        advanceUntilIdle()

        vm.onProofPicked(uri)

        assertFalse(vm.uiState.value.canUploadProof)
        assertNotNull(vm.uiState.value.proofRejectionMessage)
        assertFalse(vm.uploadProof())
        coVerify(exactly = 0) { repository.uploadQrIsProof(any(), any()) }
    }

    @Test
    fun `PAY-002 file kosong tidak pernah dikirim`() = runTest(dispatcher) {
        givenActiveQris()
        givenPayment(payment())
        val uri = givenPickedProof(sizeBytes = 0L)

        val vm = viewModel()
        advanceUntilIdle()

        vm.onProofPicked(uri)

        assertFalse(vm.uploadProof())
        coVerify(exactly = 0) { repository.uploadQrIsProof(any(), any()) }
    }

    @Test
    fun `PAY-006 upload sukses hanya menghasilkan menunggu verifikasi`() =
        runTest(dispatcher) {
            givenActiveQris()
            givenPayment(payment())
            val uri = givenPickedProof()
            coEvery { repository.uploadQrIsProof(ORDER_ID, any()) } returns Result.success(
                payment(
                    status = PaymentStatus.WAITING_VERIFICATION,
                    proof = PaymentProof(available = true)
                )
            )

            val vm = viewModel()
            advanceUntilIdle()
            vm.onProofPicked(uri)
            assertTrue(vm.uploadProof())
            advanceUntilIdle()

            assertEquals(PaymentStatus.WAITING_VERIFICATION, vm.uiState.value.status)
            assertTrue(vm.uiState.value.isAwaitingVerification)
            // Pilihan lokal dibuang supaya bukti tidak terkirim dua kali.
            assertNull(vm.uiState.value.selectedProof)
            coVerify(exactly = 1) { repository.uploadQrIsProof(ORDER_ID, any()) }
        }

    @Test
    fun `upload kedua saat request berjalan ditolak sebagai duplicate submit`() =
        runTest(dispatcher) {
            givenActiveQris()
            givenPayment(payment())
            val uri = givenPickedProof()
            coEvery { repository.uploadQrIsProof(ORDER_ID, any()) } returns Result.success(
                payment(status = PaymentStatus.WAITING_VERIFICATION)
            )

            val vm = viewModel()
            advanceUntilIdle()
            vm.onProofPicked(uri)

            assertTrue(vm.uploadProof())
            // Tanpa advanceUntilIdle: request masih berjalan.
            assertFalse(vm.uploadProof())
            advanceUntilIdle()

            coVerify(exactly = 1) { repository.uploadQrIsProof(ORDER_ID, any()) }
        }

    @Test
    fun `upload ditolak ketika status sudah berubah di backend`() = runTest(dispatcher) {
        givenActiveQris()
        givenPayment(payment(status = PaymentStatus.PAID))
        val uri = givenPickedProof()

        val vm = viewModel()
        advanceUntilIdle()
        vm.onProofPicked(uri)

        assertFalse(vm.uploadProof())
        coVerify(exactly = 0) { repository.uploadQrIsProof(any(), any()) }
    }

    @Test
    fun `kegagalan upload dari backend ditampilkan apa adanya`() = runTest(dispatcher) {
        givenActiveQris()
        givenPayment(payment())
        val uri = givenPickedProof()
        coEvery { repository.uploadQrIsProof(ORDER_ID, any()) } returns
            Result.failure(ServerException(statusCode = 422, message = "Ukuran file melebihi batas."))

        val vm = viewModel()
        advanceUntilIdle()
        vm.onProofPicked(uri)
        vm.uploadProof()
        advanceUntilIdle()

        assertEquals(PaymentStatus.PENDING, vm.uiState.value.status)
        assertTrue(vm.uiState.value.uploadState is SubmitState.Error)
        // Bukti lokal tetap disimpan supaya customer tidak perlu memilih ulang.
        assertNotNull(vm.uiState.value.selectedProof)
    }

    @Test
    fun `PAY-004 bukti hanya disimpan sebagai status keberadaan`() = runTest(dispatcher) {
        givenActiveQris()
        givenPayment(payment())
        val uri = givenPickedProof()
        coEvery { repository.uploadQrIsProof(ORDER_ID, any()) } returns Result.success(
            payment(
                status = PaymentStatus.WAITING_VERIFICATION,
                proof = PaymentProof(available = true)
            )
        )

        val vm = viewModel()
        advanceUntilIdle()
        vm.onProofPicked(uri)
        vm.uploadProof()
        advanceUntilIdle()

        // Domain menyimpan `available`, bukan URL atau path bukti.
        val state = vm.uiState.value
        assertNull(state.selectedProof)
        assertEquals(PaymentProof(available = true), state.payment?.proof)
    }

    // --- CASH-002 / CASH-003 -------------------------------------------------

    @Test
    fun `CASH-002 cash menunggu konfirmasi kurir sebelum dianggap lunas`() =
        runTest(dispatcher) {
            givenActiveQris()
            givenPayment(payment(method = PaymentMethod.CASH, status = PaymentStatus.PENDING))

            val vm = viewModel()
            advanceUntilIdle()

            assertEquals(PaymentStatus.PENDING, vm.uiState.value.status)
            assertFalse(vm.uiState.value.isPaid)
        }

    @Test
    fun `CASH-003 cash hanya lunas setelah backend mengonfirmasi`() = runTest(dispatcher) {
        givenActiveQris()
        givenPayment(payment(method = PaymentMethod.CASH, status = PaymentStatus.PAID))

        val vm = viewModel()
        advanceUntilIdle()

        assertEquals(PaymentStatus.PAID, vm.uiState.value.status)
        assertTrue(vm.uiState.value.isPaid)
    }

    @Test
    fun `cash tidak pernah bisa mengunggah bukti dari layar pembayaran`() =
        runTest(dispatcher) {
            givenActiveQris()
            givenPayment(payment(method = PaymentMethod.CASH, status = PaymentStatus.PENDING))
            val uri = givenPickedProof()

            val vm = viewModel()
            advanceUntilIdle()
            vm.onProofPicked(uri)

            assertFalse(vm.uiState.value.canUploadProof)
            assertFalse(vm.uploadProof())
            coVerify(exactly = 0) { repository.uploadQrIsProof(any(), any()) }
        }

    // --- Guard umum ---------------------------------------------------------

    @Test
    fun `order tanpa id tidak memanggil backend`() = runTest(dispatcher) {
        val vm = viewModel(orderId = "")
        advanceUntilIdle()

        coVerify(exactly = 0) { repository.getPayment(any()) }
        assertTrue(vm.uiState.value.paymentState is LoadState.Error)
    }

    @Test
    fun `unauthorized saat memuat payment ditandai untuk arahkan ke login`() =
        runTest(dispatcher) {
            givenActiveQris()
            coEvery { repository.getPayment(ORDER_ID) } returns Result.failure(UnauthorizedException())

            val vm = viewModel()
            advanceUntilIdle()

            val state = vm.uiState.value.paymentState as LoadState.Error
            assertTrue(state.isUnauthorized)
        }

    @Test
    fun `jaringan mati saat memuat payment dapat diulang`() = runTest(dispatcher) {
        givenActiveQris()
        coEvery { repository.getPayment(ORDER_ID) } returns Result.failure(NoNetworkException())

        val vm = viewModel()
        advanceUntilIdle()

        val state = vm.uiState.value.paymentState as LoadState.Error
        assertTrue(state.isRetryable)
        assertFalse(state.isNotFound)
    }

    @Test
    fun `refresh membaca ulang payment dari backend dan mengganti status lokal`() =
        runTest(dispatcher) {
            givenActiveQris()
            givenPayment(payment(status = PaymentStatus.PENDING))

            val vm = viewModel()
            advanceUntilIdle()
            assertEquals(PaymentStatus.PENDING, vm.uiState.value.status)

            givenPayment(payment(status = PaymentStatus.PAID))
            vm.refreshPayment()
            advanceUntilIdle()

            assertEquals(PaymentStatus.PAID, vm.uiState.value.status)
        }

    @Test
    fun `clearSubmitStates mengembalikan state aksi ke idle`() = runTest(dispatcher) {
        givenActiveQris()
        givenPayment(null)
        coEvery {
            repository.selectPaymentMethod(ORDER_ID, PaymentMethod.CASH)
        } returns Result.success(payment(method = PaymentMethod.CASH))

        val vm = viewModel()
        advanceUntilIdle()
        vm.selectMethod(PaymentMethod.CASH)
        advanceUntilIdle()

        vm.clearSubmitStates()

        assertEquals(SubmitState.Idle, vm.uiState.value.selectState)
        assertEquals(SubmitState.Idle, vm.uiState.value.uploadState)
    }

    companion object {
        private const val ORDER_ID = "1001"
    }
}