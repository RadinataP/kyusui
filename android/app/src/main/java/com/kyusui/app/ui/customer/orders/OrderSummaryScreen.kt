package com.kyusui.app.ui.customer.orders

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
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
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.kyusui.app.core.Money
import com.kyusui.app.ui.components.PrimaryButton
import com.kyusui.app.ui.customer.createorder.CartUiState

/**
 * Ringkasan order sebelum dikirim.
 *
 * Nilai yang ditampilkan adalah estimasi dari harga produk yang sudah dimuat.
 * Backend menghitung ulang seluruh nominal saat `POST /customer/orders`, jadi
 * layar ini sengaja tidak menampilkan total final sebagai angkapasti.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OrderSummaryScreen(
    cartState: CartUiState,
    onBack: () -> Unit,
    onSubmit: () -> Unit,
    isSubmitting: Boolean,
    modifier: Modifier = Modifier
) {
    Scaffold(
        modifier = modifier,
        topBar = {
            TopAppBar(
                title = { Text(text = "Ringkasan Pesanan") },
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
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Text(
                text = "Produk Dipesan",
                style = MaterialTheme.typography.titleMedium
            )

            cartState.lines.forEach { line ->
                SummaryRow(
                    label = "${line.product.name} x${line.quantity}",
                    value = Money.format(line.product.price)
                )
            }

            HorizontalDivider()

            cartState.previewSubtotal?.let { subtotal ->
                SummaryRow(
                    label = "Estimasi subtotal",
                    value = Money.format(subtotal),
                    emphasize = true
                )
            }

            cartState.address.takeIf { it.isNotBlank() }?.let { address ->
                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(
                        text = "Alamat Pengantaran",
                        style = MaterialTheme.typography.titleSmall
                    )
                    Text(
                        text = address,
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    cartState.location?.let { point ->
                        Text(
                            text = "Koordinat: ${point.latitude}, ${point.longitude}",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }

            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.surfaceVariant
                )
            ) {
                Text(
                    text = "Delivery fee dan total akhir dihitung oleh server saat pesanan diproses.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(12.dp)
                )
            }

            PrimaryButton(
                text = "Kirim Pesanan",
                onClick = onSubmit,
                enabled = cartState.canSubmit,
                isLoading = isSubmitting
            )
        }
    }
}

@Composable
private fun SummaryRow(
    label: String,
    value: String,
    emphasize: Boolean = false,
    modifier: Modifier = Modifier
) {
    Row(
        modifier = modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Text(
            text = label,
            style = if (emphasize) {
                MaterialTheme.typography.titleSmall
            } else {
                MaterialTheme.typography.bodyMedium
            },
            modifier = Modifier.weight(1f)
        )
        Text(
            text = value,
            style = if (emphasize) {
                MaterialTheme.typography.titleSmall
            } else {
                MaterialTheme.typography.bodyMedium
            },
            color = if (emphasize) {
                MaterialTheme.colorScheme.primary
            } else {
                MaterialTheme.colorScheme.onSurface
            }
        )
    }
}