package com.kyusui.app.ui.customer.createorder

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
import com.kyusui.app.ui.components.EmptyState
import com.kyusui.app.ui.components.InputField
import com.kyusui.app.ui.components.InlineError
import com.kyusui.app.ui.components.PrimaryButton
import com.kyusui.app.ui.state.SubmitState

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CreateOrderScreen(
    cartState: CartUiState,
    submitState: SubmitState,
    onBack: () -> Unit,
    onAddressChange: (String) -> Unit,
    onRequestLocation: () -> Unit,
    onContinueToSummary: () -> Unit,
    onSubmit: () -> Unit,
    modifier: Modifier = Modifier
) {
    Scaffold(
        modifier = modifier,
        topBar = {
            TopAppBar(
                title = { Text(text = "Buat Pesanan") },
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
        if (cartState.isEmpty) {
            EmptyState(
                modifier = Modifier.padding(innerPadding),
                title = "Keranjang Kosong",
                message = "Pilih produk terlebih dahulu sebelum membuat pesanan.",
                actionText = "Kembali ke Produk",
                onActionClick = onBack
            )
            return@Scaffold
        }

        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Text(
                text = "Rincian Pesanan",
                style = MaterialTheme.typography.titleMedium
            )

            cartState.lines.forEach { line ->
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.surfaceVariant
                    )
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(12.dp),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = "${line.product.name} x${line.quantity}",
                            style = MaterialTheme.typography.bodyMedium,
                            modifier = Modifier.weight(1f)
                        )
                        Text(
                            text = Money.format(line.product.price),
                            style = MaterialTheme.typography.bodyMedium
                        )
                    }
                }
            }

            // Estimasi lokal. Backend menghitung ulang subtotal, delivery fee,
            // dan total final saat order dibuat.
            cartState.previewSubtotal?.let { subtotal ->
                Text(
                    text = "Estimasi subtotal: ${Money.format(subtotal)}",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Text(
                    text = "Total akhir dihitung oleh server.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }

            Spacer(Modifier.height(8.dp))
            Text(
                text = "Lokasi Pengantaran",
                style = MaterialTheme.typography.titleMedium
            )

            InputField(
                label = "Alamat lengkap",
                value = cartState.address,
                onValueChange = onAddressChange,
                placeholder = "Contoh: Jl. Merdeka No. 10",
                maxLines = 3,
                singleLine = false
            )

            PrimaryButton(
                text = when (cartState.locationStatus) {
                    LocationStatus.Ready -> "Lokasi Diperbarui"
                    LocationStatus.Locating -> "Mendeteksi Lokasi..."
                    else -> "Gunakan Lokasi Saat Ini"
                },
                onClick = onRequestLocation,
                isLoading = cartState.locationStatus == LocationStatus.Locating,
                fillWidth = false
            )

            when (cartState.locationStatus) {
                LocationStatus.PermissionDenied -> Text(
                    text = "Izin lokasi tidak diberikan. Aktifkan izin lokasi untuk melanjutkan.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.error
                )
                LocationStatus.Unavailable -> Text(
                    text = "Lokasi tidak dapat dideteksi. Coba lagi di area terbuka.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.error
                )
                LocationStatus.Ready -> cartState.location?.let { point ->
                    Text(
                        text = "Koordinat: ${point.latitude}, ${point.longitude}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
                else -> Unit
            }

            if (submitState is SubmitState.Error) {
                InlineError(message = submitState.message)
            }

            // Ringkasan adalah satu-satunya tempat konfirmasi, jadi spec flow
            // "isi data pengiriman -> konfirmasi -> bayar" tidak bisa dilewati.
            // Tombol tetap mati sampai alamat dan lokasi valid supaya order
            // tidak pernah terkirim dengan data pengantaran yang kosong.
            PrimaryButton(
                text = "Lanjut ke Ringkasan",
                onClick = onContinueToSummary,
                enabled = cartState.canSubmit
            )

            Text(
                text = "Dengan membuat pesanan, Anda menyetujui pesanan diproses oleh KYUSUI.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth()
            )
        }
    }
}