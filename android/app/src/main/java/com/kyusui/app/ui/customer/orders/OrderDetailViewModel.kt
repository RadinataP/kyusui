package com.kyusui.app.ui.customer.orders

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.kyusui.app.core.Result
import com.kyusui.app.domain.model.Order
import com.kyusui.app.domain.repository.CustomerRepository
import com.kyusui.app.ui.customer.toLoadError
import com.kyusui.app.ui.state.LoadState
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * Detail order customer dari `GET /customer/orders/{order}`.
 *
 * Semua nominal dan status order berasal dari backend.
 */
@HiltViewModel
class OrderDetailViewModel @Inject constructor(
    private val customerRepository: CustomerRepository,
    savedStateHandle: SavedStateHandle
) : ViewModel() {

    private val orderId: String = savedStateHandle.get<String>(ARG_ORDER_ID).orEmpty()

    private val _uiState = MutableStateFlow<LoadState<Order>>(LoadState.Loading)
    val uiState: StateFlow<LoadState<Order>> = _uiState.asStateFlow()

    init {
        loadOrder()
    }

    fun loadOrder() {
        if (orderId.isBlank()) {
            _uiState.value = LoadState.Error(
                message = "Order tidak ditemukan.",
                isRetryable = false
            )
            return
        }

        _uiState.value = LoadState.Loading

        viewModelScope.launch {
            when (val result = customerRepository.getOrder(orderId)) {
                is Result.Success -> _uiState.value = LoadState.Success(result.data)
                is Result.Failure -> _uiState.value = result.toLoadError()
            }
        }
    }

    companion object {
        const val ARG_ORDER_ID = "orderId"
    }
}