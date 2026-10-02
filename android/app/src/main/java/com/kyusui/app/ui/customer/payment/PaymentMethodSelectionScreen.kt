package com.kyusui.app.ui.customer.payment

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.AccountBalanceWallet
import androidx.compose.material.icons.filled.QrCode2
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.unit.dp
import com.kyusui.app.domain.model.PaymentMethod
import com.kyusui.app.domain.model.PaymentStatus
import com.kyusui.app.ui.components.ErrorState
import com.kyusui.app.ui.components.InlineError
import com.kyusui.app.ui.components.LoadingState
import com.kyusui.app.ui.components.PrimaryButton
import com.kyusui.app.ui.state.LoadState
import com.kyusui.app.ui.state.SubmitState

/**
 * Pemilihan metode pembayaran.
 *
 * Memilih metode hanya membuat payment `PENDING`. Layar ini tidak pernah
 * menampilkan payment sebagai lunas hanya karena customer memilih metode.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PaymentMethodSelectionScreen(
    uiState: PaymentUiState,
    onBack: () -> Unit,
    onSelect: (PaymentMethod) -> Unit,
    onRetryPayment: () -> Unit,
    modifier: Modifier = Modifier
) {
    // QRIS hanya dapat dipilih bila backend punya QRIS aktif, kecuali payment
    // sudah tercatat sebagai QRIS sehingga pembayaran yang berjalan tetap bisa
    // diselesaikan.
    val isQrisAvailable = uiState.isQrisSelectable || uiState.method == PaymentMethod.QRIS
    val isQrisLoading = !uiState.isQrisSelectable &&
        !uiState.isQrisNotConfigured &&
        uiState.qrisState is LoadState.Loading

    var selected by remember(uiState.method, isQrisAvailable) {
        val existingMethod = uiState.method
        mutableStateOf(
            when {
                existingMethod != null -> existingMethod
                isQrisAvailable -> PaymentMethod.QRIS
                // Tanpa QRIS aktif, jangan preselect opsi yang tidak bisa dipakai.
                else -> PaymentMethod.CASH
            }
        )
    }

    Scaffold(
        modifier = modifier,
        topBar = {
            TopAppBar(
                title = { Text(text = "Metode Pembayaran") },
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
                is LoadState.Loading -> LoadingState(message = "Memuat pesanan...")

                is LoadState.Error -> ErrorState(
                    message = state.message,
                    isRetryable = state.isRetryable,
                    onRetry = onRetryPayment
                )

                else -> Unit
            }

            val payment = uiState.payment

            PaymentAmountRow(
                label = "Total yang harus dibayar",
                amount = payment?.amount ?: uiState.orderTotal.orEmpty(),
                isEmphasis = true
            )

            if (payment != null && payment.paymentStatus != null) {
                PaymentStatusCard(
                    status = payment.paymentStatus,
                    method = payment.paymentMethod
                )
            }

            PaymentMethodExplanation(
                method = selected,
                isQrisAvailable = isQrisAvailable,
                isQrisLoading = isQrisLoading
            )

            PaymentMethodOption(
                method = PaymentMethod.QRIS,
                title = "QRIS",
                description = "Scan QRIS toko, lalu unggah bukti pembayaran.",
                icon = { Icon(Icons.Filled.QrCode2, contentDescription = null) },
                selected = selected == PaymentMethod.QRIS,
                enabled = isQrisAvailable,
                onSelect = { selected = PaymentMethod.QRIS }
            )

            PaymentMethodOption(
                method = PaymentMethod.CASH,
                title = "Tunai (Cash on Delivery)",
                description = "Bayar langsung kepada kurir saat barang diterima.",
                icon = { Icon(Icons.Filled.AccountBalanceWallet, contentDescription = null) },
                selected = selected == PaymentMethod.CASH,
                enabled = true,
                onSelect = { selected = PaymentMethod.CASH }
            )

            if (uiState.isQrisNotConfigured) {
                InlineError(message = PaymentViewModel.QRIS_NOT_CONFIGURED_MESSAGE)
            }

            (uiState.selectState as? SubmitState.Error)?.let { error ->
                InlineError(message = error.message)
            }

            // Memilih metode tetap enabled saat payment sudah PENDING supaya
            // customer bisa berpindah dari QRIS ke tunai sebelum membayar.
            // PAID mengunci pilihan karena payment sudah final.
            val isAlreadyPaid = uiState.status == PaymentStatus.PAID

            PrimaryButton(
                text = if (selected == PaymentMethod.QRIS) {
                    "Lanjut ke Pembayaran QRIS"
                } else {
                    "Lanjut ke Pembayaran Tunai"
                },
                onClick = { onSelect(selected) },
                enabled = !isAlreadyPaid && !uiState.isSelecting,
                isLoading = uiState.isSelecting
            )

            if (isAlreadyPaid) {
                Text(
                    text = "Pembayaran untuk pesanan ini sudah selesai.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }

            Text(
                text = "Memilih metode pembayaran belum berarti pembayaran " +
                    "terkonfirmasi. Pembayaran hanya dianggap selesai setelah " +
                    "diverifikasi oleh pemilik toko atau kurir.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

@Composable
private fun PaymentMethodOption(
    method: PaymentMethod,
    title: String,
    description: String,
    icon: @Composable () -> Unit,
    selected: Boolean,
    enabled: Boolean,
    onSelect: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .selectable(
                selected = selected,
                enabled = enabled,
                role = Role.RadioButton,
                onClick = onSelect
            ),
        colors = CardDefaults.cardColors(
            containerColor = if (selected) {
                MaterialTheme.colorScheme.secondaryContainer
            } else {
                MaterialTheme.colorScheme.surface
            }
        )
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            RadioButton(selected = selected, onClick = onSelect, enabled = enabled)
            Spacer(Modifier.size(12.dp))
            icon()
            Spacer(Modifier.size(12.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(text = title, style = MaterialTheme.typography.titleSmall)
                Text(
                    text = description,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
    }
}

@Composable
private fun PaymentMethodExplanation(
    method: PaymentMethod,
    isQrisAvailable: Boolean,
    isQrisLoading: Boolean
) {
    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
        when (method) {
            PaymentMethod.QRIS -> {
                Text(
                    text = "Cara pembayaran QRIS",
                    style = MaterialTheme.typography.titleSmall
                )
                Text(
                    text = "1. Scan atau tampilkan QRIS toko.\n" +
                        "2. Bayar melalui aplikasi pembayaran Anda.\n" +
                        "3. Unggah foto bukti pembayaran.\n" +
                        "4. Tunggu pemilik toko memverifikasi bukti Anda.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            PaymentMethod.CASH -> {
                Text(
                    text = "Cara pembayaran tunai",
                    style = MaterialTheme.typography.titleSmall
                )
                Text(
                    text = "1. Pesanan diproses tanpa perlu bukti.\n" +
                        "2. Bayar uang tunai kepada kurir saat barang diterima.\n" +
                        "3. Kurir mengonfirmasi penerimaan pembayaran.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
        if (method == PaymentMethod.QRIS && !isQrisAvailable) {
            Spacer(Modifier.height(4.dp))
            Text(
                text = if (isQrisLoading) {
                    "QRIS sedang dimuat dari server."
                } else {
                    PaymentViewModel.QRIS_NOT_CONFIGURED_MESSAGE
                },
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}