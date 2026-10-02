package com.kyusui.app.ui.customer.products

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
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.kyusui.app.core.Money
import com.kyusui.app.domain.model.Product
import com.kyusui.app.ui.components.EmptyState
import com.kyusui.app.ui.components.PrimaryButton

/**
 * Detail produk.
 *
 * Specification tidak menyediakan `GET /products/{id}`, jadi layar ini
 * menampilkan produk yang sudah dimuat dari `GET /products` melalui argumen
 * navigasi. Harga ditampilkan apa adanya dari backend.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProductDetailScreen(
    uiState: ProductDetailUiState,
    inCartQuantity: Int,
    onBack: () -> Unit,
    onAddToCart: (Product) -> Unit,
    modifier: Modifier = Modifier
) {
    Scaffold(
        modifier = modifier,
        topBar = {
            TopAppBar(
                title = { Text(text = "Detail Produk") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "Kembali"
                        )
                    }
                },
                actions = {
                    if (inCartQuantity > 0) {
                        Text(text = "Di keranjang: $inCartQuantity")
                    }
                }
            )
        }
    ) { innerPadding ->
        when (val state = uiState) {
            is ProductDetailUiState.Missing -> EmptyState(
                modifier = Modifier.padding(innerPadding),
                title = "Produk Tidak Ditemukan",
                message = "Produk ini tidak tersedia. Kembali ke daftar produk.",
                actionText = "Kembali ke Daftar",
                onActionClick = onBack
            )

            is ProductDetailUiState.Ready -> {
                val product = state.product
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(innerPadding)
                        .verticalScroll(rememberScrollState())
                        .padding(24.dp),
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    Text(
                        text = product.name,
                        style = MaterialTheme.typography.headlineSmall
                    )

                    if (!product.isAvailable) {
                        Card(
                            colors = CardDefaults.cardColors(
                                containerColor = MaterialTheme.colorScheme.errorContainer
                            )
                        ) {
                            Text(
                                text = "Produk ini sedang tidak tersedia.",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onErrorContainer,
                                modifier = Modifier.padding(16.dp)
                            )
                        }
                    }

                    product.description?.let { description ->
                        Text(
                            text = description,
                            style = MaterialTheme.typography.bodyLarge,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }

                    Spacer(Modifier.height(8.dp))
                    Text(
                        text = "Harga",
                        style = MaterialTheme.typography.labelLarge,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    Text(
                        text = Money.format(product.price),
                        style = MaterialTheme.typography.headlineMedium,
                        color = MaterialTheme.colorScheme.primary
                    )

                    Spacer(Modifier.height(24.dp))
                    PrimaryButton(
                        text = if (product.isAvailable) "Tambah ke Keranjang" else "Tidak Tersedia",
                        onClick = { onAddToCart(product) },
                        enabled = product.isAvailable
                    )
                }
            }
        }
    }
}