package com.kyusui.app.ui.customer.orders

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.kyusui.app.core.Money
import com.kyusui.app.domain.model.Order
import com.kyusui.app.ui.components.ErrorState
import com.kyusui.app.ui.components.LoadingState
import com.kyusui.app.ui.components.PrimaryButton
import com.kyusui.app.ui.state.LoadState

private const val UNKNOWN_STATUS_LABEL = "Status tidak dikenal"

/**
 * Detail order customer.
 *
 * Seluruh nominal dan status berasal dari backend. Status yang tidak dikenal
 * ditampilkan apa adanya sebagai "Status tidak dikenal" dan tidak ditebak.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OrderDetailScreen(
    uiState: LoadState<Order>,
    onBack: () -> Unit,
    onRetry: () -> Unit,
    onPay: ((orderId: String) -> Unit)? = null,
    modifier: Modifier = Modifier
) {
    Scaffold(
        modifier = modifier,
        topBar = {
            TopAppBar(
                title = { Text(text = "Detail Pesanan") },
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
        when (val state = uiState) {
            is LoadState.Loading -> LoadingState(
                modifier = Modifier.padding(innerPadding),
                message = "Memuat detail pesanan..."
            )

            is LoadState.Error -> ErrorState(
                modifier = Modifier.padding(innerPadding),
                message = state.message,
                isRetryable = state.isRetryable,
                onRetry = onRetry
            )

            is LoadState.Empty -> LoadingState(modifier = Modifier.padding(innerPadding))

            is LoadState.Success -> OrderDetailContent(
                order = state.data,
                onPay = onPay,
                modifier = Modifier.padding(innerPadding)
            )
        }
    }
}

@Composable
private fun OrderDetailContent(
    order: Order,
    onPay: ((orderId: String) -> Unit)?,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(
                text = order.orderNumber.takeIf { it.isNotBlank() } ?: "Pesanan",
                style = MaterialTheme.typography.titleLarge
            )
            Text(
                text = order.orderStatus?.displayName ?: UNKNOWN_STATUS_LABEL,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }

        order.payment?.let { payment ->
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.surfaceVariant
                )
            ) {
                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Text(
                        text = "Pembayaran",
                        style = MaterialTheme.typography.titleSmall
                    )
                    DetailRow(
                        label = "Metode",
                        value = payment.paymentMethod?.displayName ?: UNKNOWN_STATUS_LABEL
                    )
                    DetailRow(
                        label = "Status",
                        value = payment.paymentStatus?.displayName ?: UNKNOWN_STATUS_LABEL
                    )
                    DetailRow(label = "Jumlah", value = Money.format(payment.amount))
                }
            }
        }

        Text(
            text = "Produk Dipesan",
            style = MaterialTheme.typography.titleMedium
        )

        order.items.forEach { item ->
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text(
                        text = "${item.productName} x${item.quantity}",
                        style = MaterialTheme.typography.bodyMedium,
                        modifier = Modifier.weight(1f)
                    )
                    Text(
                        text = Money.format(item.lineTotal),
                        style = MaterialTheme.typography.bodyMedium
                    )
                }
                Text(
                    text = "${Money.format(item.unitPrice)} x ${item.quantity}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }

        HorizontalDivider()

        DetailRow(label = "Subtotal", value = Money.format(order.subtotalAmount))
        DetailRow(label = "Biaya Pengiriman", value = Money.format(order.deliveryFee))

        Spacer(Modifier.height(4.dp))
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = "Total",
                style = MaterialTheme.typography.titleMedium
            )
            Text(
                text = Money.format(order.totalAmount),
                style = MaterialTheme.typography.titleMedium,
                color = MaterialTheme.colorScheme.primary
            )
        }

        // Tombol pembayaran hanya muncul ketika order memang punya payment
        // yang belum lunas, sehingga customer tidak sampai ke layar pembayaran
        // untuk order yang tidak perlu dibayar.
        if (onPay != null && order.requiresPayment) {
            PrimaryButton(
                text = "Bayar Sekarang",
                onClick = { onPay(order.id) },
                modifier = Modifier.fillMaxWidth()
            )
        }

        if (order.requiresPayment) {
            Text(
                text = "Menunggu pembayaran",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth()
            )
        }
    }
}

@Composable
internal fun DetailRow(
    label: String,
    value: String,
    modifier: Modifier = Modifier
) {
    Row(
        modifier = modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium
        )
    }
}