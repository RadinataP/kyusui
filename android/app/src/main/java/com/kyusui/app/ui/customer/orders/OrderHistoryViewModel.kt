package com.kyusui.app.ui.customer.orders

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.kyusui.app.core.Result
import com.kyusui.app.domain.model.Order
import com.kyusui.app.domain.model.OrderStatus
import com.kyusui.app.domain.repository.CustomerRepository
import com.kyusui.app.ui.customer.toLoadError
import com.kyusui.app.ui.customer.toPagedListLoadState
import com.kyusui.app.ui.state.LoadState
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * Riwayat order customer dari `GET /customer/orders`.
 *
 * Specification tidak menyediakan filter status pada endpoint ini, jadi
 * filtering dilakukan pada data yang sudah dimuat, bukan sebagai query.
 */
data class OrderHistoryUiState(
    val ordersState: LoadState<List<Order>> = LoadState.Loading,
    val statusFilter: OrderStatusFilter = OrderStatusFilter.ALL,
    val allOrders: List<Order> = emptyList(),
    val page: Int = FIRST_PAGE,
    val hasMore: Boolean = false,
    val isAppending: Boolean = false
) {
    val isUnauthorized: Boolean
        get() = (ordersState as? LoadState.Error)?.isUnauthorized == true

    val filteredOrders: List<Order>
        get() = when (statusFilter) {
            OrderStatusFilter.ALL -> allOrders
            OrderStatusFilter.ACTIVE -> allOrders.filter { it.isActive() }
            OrderStatusFilter.COMPLETED -> allOrders.filterNot { it.isActive() }
        }

    /**
     * Filter yang tidak menghasilkan hasil harus tetap bisa dibedakan dari
     * "belum ada order sama sekali", supaya pesan yang ditampilkan jujur.
     */
    val hasNoMatchForFilter: Boolean
        get() = allOrders.isNotEmpty() && filteredOrders.isEmpty()

    val emptyMessage: String
        get() = when {
            allOrders.isEmpty() -> OrderHistoryViewModel.EMPTY_ORDERS_MESSAGE
            statusFilter == OrderStatusFilter.ACTIVE -> EMPTY_ACTIVE_ORDERS_MESSAGE
            statusFilter == OrderStatusFilter.COMPLETED -> EMPTY_COMPLETED_ORDERS_MESSAGE
            else -> OrderHistoryViewModel.EMPTY_ORDERS_MESSAGE
        }

    companion object {
        const val FIRST_PAGE = 1
    }
}

private const val EMPTY_ACTIVE_ORDERS_MESSAGE = "Tidak ada order yang sedang aktif."
private const val EMPTY_COMPLETED_ORDERS_MESSAGE = "Belum ada order yang selesai."

enum class OrderStatusFilter {
    ALL,
    ACTIVE,
    COMPLETED;

    fun label(): String = when (this) {
        ALL -> "Semua"
        ACTIVE -> "Aktif"
        COMPLETED -> "Selesai"
    }
}

/**
 * Status yang masih berjalan. `SELESAI` adalah status terminal satu-satunya,
 * jadi status yang tidak dikenal dianggap aktif supaya customer tidak
 * kehilangan order yang belum selesai.
 */
private fun Order.isActive(): Boolean = orderStatus != OrderStatus.SELESAI

@HiltViewModel
class OrderHistoryViewModel @Inject constructor(
    private val customerRepository: CustomerRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(OrderHistoryUiState())
    val uiState: StateFlow<OrderHistoryUiState> = _uiState.asStateFlow()

    init {
        loadOrders()
    }

    fun loadOrders() {
        _uiState.update {
            it.copy(
                ordersState = LoadState.Loading,
                allOrders = emptyList(),
                page = OrderHistoryUiState.FIRST_PAGE,
                hasMore = false,
                isAppending = false
            )
        }

        viewModelScope.launch {
            val result = customerRepository.getOrders(
                page = OrderHistoryUiState.FIRST_PAGE,
                perPage = PER_PAGE
            )

            _uiState.update { state ->
                when (result) {
                    is Result.Success -> state.copy(
                        ordersState = result.toPagedListLoadState(EMPTY_ORDERS_MESSAGE),
                        allOrders = result.data.data,
                        hasMore = result.data.hasMore
                    )
                    is Result.Failure -> state.copy(ordersState = result.toLoadError())
                }
            }
        }
    }

    /**
     * Memuat halaman order berikutnya.
     *
     * Halaman yang gagal tidak menghapus order yang sudah tampil; `hasMore`
     * dimatikan supaya tidak mengulang request yang sama saat customer
     * menggulir ulang.
     */
    fun loadNextPage() {
        val current = _uiState.value
        if (!current.hasMore || current.isAppending) return
        if (current.ordersState !is LoadState.Success) return

        val nextPage = current.page + 1
        _uiState.update { it.copy(isAppending = true) }

        viewModelScope.launch {
            val result = customerRepository.getOrders(page = nextPage, perPage = PER_PAGE)

            _uiState.update { state ->
                when (result) {
                    is Result.Success -> {
                        val merged = (state.allOrders + result.data.data).distinctBy { it.id }
                        state.copy(
                            allOrders = merged,
                            ordersState = LoadState.Success(merged),
                            page = nextPage,
                            hasMore = result.data.hasMore,
                            isAppending = false
                        )
                    }
                    is Result.Failure -> state.copy(
                        hasMore = false,
                        isAppending = false
                    )
                }
            }
        }
    }

    fun setStatusFilter(filter: OrderStatusFilter) {
        // Filter sengaja dipertahankan saat halaman berikutnya dimuat supaya
        // pilihan customer tidak ter-reset karena infinite scroll.
        _uiState.update { it.copy(statusFilter = filter) }
    }

    companion object {
        const val PER_PAGE = 50
        const val EMPTY_ORDERS_MESSAGE = "Belum ada riwayat order."
    }
}