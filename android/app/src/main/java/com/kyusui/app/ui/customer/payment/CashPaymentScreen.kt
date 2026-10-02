package com.kyusui.app.ui.customer.payment

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.kyusui.app.domain.model.PaymentStatus
import com.kyusui.app.ui.components.ErrorState
import com.kyusui.app.ui.components.LoadingState
import com.kyusui.app.ui.components.SecondaryButton
import com.kyusui.app.ui.state.LoadState

/**
 * Layar pembayaran tunai.
 *
 * Android sengaja tidak punya aksi pembayaran apa pun untuk CASH. Cash on
 * Delivery diselesaikan langsung dengan kurir, dan konfirmasi "uang diterima"
 * dilakukan Courier di perangkatnya. Karena itu `PAID` di layar ini hanya dapat
 * muncul setelah backend menerima konfirmasi tersebut; tidak ada tombol locally
 * confirm.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CashPaymentScreen(
    uiState: PaymentUiState,
    onBack: () -> Unit,
    onRefresh: () -> Unit,
    modifier: Modifier = Modifier
) {
    Scaffold(
        modifier = modifier,
        topBar = {
            TopAppBar(
                title = { Text(text = "Pembayaran Tunai") },
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
                    label = "Total yang harus dibayar",
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
                    isRejectedProof = false
                )
            }

            CashPaymentInfoCard()

            when (uiState.status) {
                PaymentStatus.PENDING -> {
                    CashPendingNotice()
                    // Muat ulang tersedia karena status berubah setelah Courier
                    // mengonfirmasi pembayaran.
                    SecondaryButton(
                        text = "Muat Ulang Status",
                        onClick = onRefresh,
                        modifier = Modifier.fillMaxWidth()
                    )
                }

                PaymentStatus.PAID -> CashPaidNotice()

                else -> Spacer(Modifier.height(0.dp))
            }

            // QRIS tidak pernah masuk WAITING_VERIFICATION, dan CASH tidak
            // memiliki proof. Status yang tidak dikenal ditampilkan tanpa
            // aksi pembayaran apa pun.
            if (uiState.status == null && uiState.payment != null) {
                Text(
                    text = "Status pembayaran tidak dikenali. Muat ulang untuk " +
                        "mengambil status terbaru.",
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
private fun CashPendingNotice() {
    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Text(
            text = "Pesanan Anda sedang diproses",
            style = MaterialTheme.typography.titleSmall
        )
        Text(
            text = "Pesanan dengan pembayaran tunai tetap diproses dan dikirim " +
                "sebelum uang diterima. Status \"sudah dibayar\" akan muncul " +
                "setelah kurir mengonfirmasi penerimaan uang.",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}

@Composable
private fun CashPaidNotice() {
    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Text(
            text = "Uang sudah diterima",
            style = MaterialTheme.typography.titleSmall
        )
        Text(
            text = "Kurir telah mengonfirmasi pembayaran tunai untuk pesanan ini.",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}