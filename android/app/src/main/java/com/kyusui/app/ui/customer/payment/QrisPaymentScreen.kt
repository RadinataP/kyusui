package com.kyusui.app.ui.customer.payment

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.CloudOff
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import coil.ImageLoader
import coil.compose.AsyncImage
import coil.request.ImageRequest
import com.kyusui.app.core.ProofUploadPolicy
import com.kyusui.app.core.QrisImageUrlResolver
import com.kyusui.app.domain.model.ActiveQris
import com.kyusui.app.domain.model.PaymentStatus
import com.kyusui.app.ui.components.ErrorState
import com.kyusui.app.ui.components.InlineError
import com.kyusui.app.ui.components.LoadingState
import com.kyusui.app.ui.components.PrimaryButton
import com.kyusui.app.ui.components.SecondaryButton
import com.kyusui.app.ui.state.LoadState
import com.kyusui.app.ui.state.SubmitState

/**
 * Layar pembayaran QRIS.
 *
 * Alur yang ditampilkan mengikuti state machine pada specification:
 * `PENDING` (scan dan unggah bukti) -> `WAITING_VERIFICATION` (menunggu
 * pemeriksaan Owner) -> `PAID` (hanya dari backend). Upload proof yang sukses
 * hanya menghasilkan `WAITING_VERIFICATION`.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun QrisPaymentScreen(
    uiState: PaymentUiState,
    onBack: () -> Unit,
    onRefresh: () -> Unit,
    onRetryQris: () -> Unit,
    onProofPicked: (Uri) -> Unit,
    onClearProof: () -> Unit,
    onUploadProof: () -> Unit,
    imageLoader: ImageLoader,
    modifier: Modifier = Modifier
) {
    // Android Photo Picker dipakai agar customer tidak perlu memberi izin
    // penyimpanan. Selected=false membatasi ke gambar yang sudah ada di perangkat
    // tanpa perlu izin baca media.
    val picker = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.PickVisualMedia(),
        onResult = { uri -> uri?.let(onProofPicked) }
    )

    Scaffold(
        modifier = modifier,
        topBar = {
            TopAppBar(
                title = { Text(text = "Pembayaran QRIS") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "Kembali"
                        )
                    }
                }
            )
        }
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .verticalScroll(rememberScrollState())
                .padding(24.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            when (val state = uiState.paymentState) {
                is LoadState.Loading -> LoadingState(message = "Memuat pembayaran...")
                is LoadState.Error -> ErrorState(
                    message = state.message,
                    isRetryable = state.isRetryable,
                    onRetry = onRefresh
                )
                else -> Unit
            }

            uiState.payment?.let { payment ->
                PaymentAmountRow(
                    label = "Total pembayaran",
                    amount = payment.amount,
                    isEmphasis = true
                )

                PaymentStatusCard(
                    status = payment.paymentStatus,
                    method = payment.paymentMethod
                )

                PaymentStatusExplanation(
                    status = payment.paymentStatus,
                    method = payment.paymentMethod,
                    isRejectedProof = uiState.needsReupload
                )
            }

            when (val status = uiState.status) {
                PaymentStatus.PAID -> PaidQrIsSection()

                PaymentStatus.WAITING_VERIFICATION -> WaitingVerificationSection()

                PaymentStatus.PENDING -> {
                    QrisImageSection(
                        qrisState = uiState.qrisState,
                        imageLoader = imageLoader,
                        onRetry = onRetryQris
                    )

                    when {
                        uiState.needsReupload -> RejectedProofSection()

                        uiState.isQrisNotConfigured -> NotConfiguredSection()

                        else -> Unit
                    }

                    ProofUploadSection(
                        selectedProofUri = uiState.selectedProof?.localUri,
                        rejectionMessage = uiState.proofRejectionMessage,
                        uploadError = (uiState.uploadState as? SubmitState.Error)?.message,
                        isUploading = uiState.isUploading,
                        canUpload = uiState.canUploadProof,
                        onPickFile = {
                            picker.launch(
                                PickVisualMediaRequest(
                                    ActivityResultContracts.PickVisualMedia.ImageOnly
                                )
                            )
                        },
                        onClearFile = onClearProof,
                        onUpload = onUploadProof
                    )
                }

                else -> Unit
            }

            if (uiState.status == null && uiState.payment != null) {
                // Payment dengan status tidak dikenal tidak boleh menampilkan
                // aksi pembayaran apa pun, karena aksi bisa merusak state backend.
                Text(
                    text = "Status pembayaran tidak dikenali. " +
                        "Muat ulang untuk mengambil status terbaru.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.error
                )
                SecondaryButton(
                    text = "Muat Ulang",
                    onClick = onRefresh,
                    modifier = Modifier.fillMaxWidth()
                )
            }
        }
    }
}

@Composable
private fun QrisImageSection(
    qrisState: LoadState<ActiveQris>,
    imageLoader: ImageLoader,
    onRetry: () -> Unit
) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(text = "QRIS Berkah Water", style = MaterialTheme.typography.titleMedium)

        when (qrisState) {
            is LoadState.Loading -> LoadingState(message = "Memuat QRIS...")

            is LoadState.Error -> ErrorState(
                message = qrisState.message,
                isRetryable = qrisState.isRetryable,
                onRetry = onRetry
            )

            is LoadState.Empty -> Text(
                text = "QRIS belum tersedia.",
                style = MaterialTheme.typography.bodyMedium
            )

is LoadState.Success -> {
                val qris = qrisState.data
                val qrisImageUrl = remember(qris.qrisImage) {
                    QrisImageUrlResolver.resolve(qris.qrisImage)
                }

                if (qrisImageUrl == null) {
                    // Konfigurasi ada tapi gambarnya tidak bisa dimuat dengan
                    // aman. Android tidak pernah mengarang QRIS atau memuat dari
                    // host di luar origin API.
                    QrisUnavailableNotice()
                } else {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(
                            containerColor = MaterialTheme.colorScheme.surface
                        )
                    ) {
                        AsyncImage(
                            model = ImageRequest.Builder(LocalContext.current)
                                // URL yang sudah dibatasi ke origin API. Nilai
                                // mentah dari backend tidak pernah dipakai
                                // langsung sebagai model.
                                .data(qrisImageUrl)
                                .crossfade(true)
                                .build(),
                            imageLoader = imageLoader,
                            contentDescription = "QRIS pembayaran Berkah Water",
                            contentScale = ContentScale.Fit,
                            modifier = Modifier
                                .fillMaxWidth()
                                .heightIn(max = 320.dp)
                                .padding(16.dp)
                        )
                    }

                    qris.updatedAt?.let {
                        Text(
                            text = "Terakhir diperbarui: $it",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun QrisUnavailableNotice() {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.errorContainer
        )
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = Icons.Filled.CloudOff,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onErrorContainer
            )
            Spacer(Modifier.width(12.dp))
            Text(
                text = "Gambar QRIS tidak dapat dimuat. Periksa koneksi Anda.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onErrorContainer
            )
        }
    }
}

@Composable
private fun NotConfiguredSection() {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.errorContainer
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Text(
                text = "QRIS belum dikonfigurasi",
                style = MaterialTheme.typography.titleSmall,
                color = MaterialTheme.colorScheme.onErrorContainer
            )
            Text(
                text = "Pemilik toko belum memasang QRIS aktif, jadi pembayaran " +
                    "QRIS belum tersedia. Pilih pembayaran tunai atau hubungi " +
                    "pemilik toko.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onErrorContainer
            )
        }
    }
}

/**
 * Bagian upload bukti.
 *
 * Pratinjau memakai URI lokal yang dipilih customer. Backend hanya menerima
 * `available` untuk bukti, sehingga Android tidak pernah mengunduh atau menyimpan
 * salinan proof milik backend.
 */
@Composable
private fun ProofUploadSection(
    selectedProofUri: String?,
    rejectionMessage: String?,
    uploadError: String?,
    isUploading: Boolean,
    canUpload: Boolean,
    onPickFile: () -> Unit,
    onClearFile: () -> Unit,
    onUpload: () -> Unit
) {
    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(
            text = "Bukti pembayaran",
            style = MaterialTheme.typography.titleMedium
        )

        ProofPrivacyNotice()

        if (selectedProofUri == null) {
            PrimaryButton(
                text = "Pilih Foto Bukti",
                onClick = onPickFile,
                modifier = Modifier.fillMaxWidth()
            )
        } else {
            ProofPreview(
                uri = selectedProofUri,
                onClear = onClearFile
            )
        }

        rejectionMessage?.let {
            InlineError(message = it)
        }

        uploadError?.let {
            InlineError(message = it)
        }

        if (selectedProofUri != null) {
            PrimaryButton(
                text = "Kirim Bukti Pembayaran",
                onClick = onUpload,
                modifier = Modifier.fillMaxWidth(),
                enabled = canUpload && !isUploading,
                isLoading = isUploading
            )

            Text(
                text = "Format JPG atau PNG, maksimal ${ProofUploadPolicy.MAX_SIZE_LABEL}.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth()
            )
        }
    }
}

@Composable
private fun ProofPreview(uri: String, onClear: () -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        AsyncImage(
            model = Uri.parse(uri),
            contentDescription = "Pratinjau bukti pembayaran yang dipilih",
            contentScale = ContentScale.Crop,
            modifier = Modifier
                .fillMaxWidth()
                .aspectRatio(4f / 3f)
                .background(
                    color = MaterialTheme.colorScheme.surfaceVariant,
                    shape = RoundedCornerShape(12.dp)
                )
        )
        SecondaryButton(
            text = "Ganti Foto",
            onClick = onClear,
            modifier = Modifier.fillMaxWidth()
        )
    }
}

@Composable
private fun WaitingVerificationSection() {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.secondaryContainer
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Text(
                text = "Bukti sedang diverifikasi",
                style = MaterialTheme.typography.titleSmall,
                color = MaterialTheme.colorScheme.onSecondaryContainer
            )
            Text(
                text = "Bukti Anda sudah dikirim. Pemilik toko akan memeriksa " +
                    "dan memberi tahu hasilnya. Anda dapat menutup halaman ini " +
                    "saja; statusnya akan tetap benar saat dibuka kembali.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSecondaryContainer
            )
        }
    }
}

@Composable
private fun PaidQrIsSection() {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.primaryContainer
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Text(
                text = "Pembayaran QRIS selesai",
                style = MaterialTheme.typography.titleSmall,
                color = MaterialTheme.colorScheme.onPrimaryContainer
            )
            Text(
                text = "Bukti pembayaran telah disetujui pemilik toko. " +
                    "Pesanan Anda akan diproses.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onPrimaryContainer
            )
        }
    }
}

/**
 * Bagian rejection.
 *
 * Rejection bukan status payment. Backend mengembalikan payment ke `PENDING`
 * sehingga customer dapat mengunggah bukti baru pada payment record yang sama.
 */
@Composable
private fun RejectedProofSection() {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.errorContainer
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Text(
                text = "Bukti sebelumnya ditolak",
                style = MaterialTheme.typography.titleSmall,
                color = MaterialTheme.colorScheme.onErrorContainer
            )
            Text(
                text = "Unggah ulang bukti pembayaran yang lebih jelas. Bukti baru " +
                    "menggantikan bukti lama pada pesanan yang sama.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onErrorContainer
            )
        }
    }
}