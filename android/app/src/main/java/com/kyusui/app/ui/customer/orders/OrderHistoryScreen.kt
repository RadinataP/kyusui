package com.kyusui.app.ui.customer.orders

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.kyusui.app.core.Money
import com.kyusui.app.domain.model.Order
import com.kyusui.app.ui.components.EmptyState
import com.kyusui.app.ui.components.ErrorState
import com.kyusui.app.ui.components.LoadingState
import com.kyusui.app.ui.state.LoadState

private const val UNKNOWN_STATUS_LABEL = "Status tidak dikenal"
private const val LOAD_MORE_THRESHOLD = 3
private const val APPENDING_KEY = "appending-indicator"

/**
 * Riwayat pesanan customer dari `GET /customer/orders`.
 *
 * Filter status diterapkan pada data yang sudah dimuat karena specification
 * tidak menyediakan parameter filter pada endpoint ini.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OrderHistoryScreen(
    uiState: OrderHistoryUiState,
    onBack: () -> Unit,
    onRetry: () -> Unit,
    onLoadMore: () -> Unit,
    onFilterChange: (OrderStatusFilter) -> Unit,
    onOrderClick: (Order) -> Unit,
    modifier: Modifier = Modifier
) {
    val listState = rememberLazyListState()

    // Infinite scroll mengikuti daftar yang sedang difilter, sehingga customer
    // yang memfilter "Selesai" tetap bisa memuat halaman order lama.
    val shouldLoadMore by remember(uiState.hasMore, uiState.isAppending, uiState.filteredOrders) {
        derivedStateOf {
            if (!uiState.hasMore || uiState.isAppending) return@derivedStateOf false
            val lastVisible = listState.layoutInfo.visibleItemsInfo.lastOrNull()?.index ?: -1
            val total = listState.layoutInfo.totalItemsCount
            total > 0 && lastVisible >= total - LOAD_MORE_THRESHOLD
        }
    }

    LaunchedEffect(shouldLoadMore) {
        if (shouldLoadMore) onLoadMore()
    }

    Scaffold(
        modifier = modifier,
        topBar = {
            TopAppBar(
                title = { Text(text = "Riwayat Pesanan") },
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
        when (val state = uiState.ordersState) {
            is LoadState.Loading -> LoadingState(
                modifier = Modifier.padding(innerPadding),
                message = "Memuat riwayat pesanan..."
            )

            is LoadState.Error -> ErrorState(
                modifier = Modifier.padding(innerPadding),
                message = state.message,
                isRetryable = state.isRetryable,
                onRetry = onRetry
            )

            is LoadState.Empty -> EmptyState(
                modifier = Modifier.padding(innerPadding),
                title = "Belum Ada Pesanan",
                message = uiState.emptyMessage,
                actionText = "Muat Ulang",
                onActionClick = onRetry
            )

            is LoadState.Success -> Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(innerPadding)
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 8.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    OrderStatusFilter.entries.forEach { filter ->
                        FilterChip(
                            selected = uiState.statusFilter == filter,
                            onClick = { onFilterChange(filter) },
                            label = { Text(text = filter.label()) }
                        )
                    }
                }

                if (uiState.filteredOrders.isEmpty()) {
                    EmptyState(
                        title = "Tidak Ada Pesanan",
                        message = uiState.emptyMessage
                    )
                } else {
                    LazyColumn(
                        state = listState,
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        items(uiState.filteredOrders, key = { it.id }) { order ->
                            OrderHistoryCard(
                                order = order,
                                onClick = { onOrderClick(order) }
                            )
                        }

                        // Penanda pemuatan halaman berikutnya memberi umpan balik
                        // bahwa masih ada order lama yang belum dimuat.
                        if (uiState.isAppending) {
                            item(key = APPENDING_KEY) {
                                LoadingState(message = "Memuat pesanan lainnya...")
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun OrderHistoryCard(
    order: Order,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        onClick = onClick,
        modifier = modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = order.orderNumber.takeIf { it.isNotBlank() } ?: "Pesanan",
                    style = MaterialTheme.typography.titleMedium
                )
                Text(
                    text = Money.format(order.totalAmount),
                    style = MaterialTheme.typography.titleSmall,
                    color = MaterialTheme.colorScheme.primary
                )
            }
            Text(
                text = order.orderStatus?.displayName ?: UNKNOWN_STATUS_LABEL,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            Text(
                text = "${order.items.sumOf { it.quantity }} produk",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}