package com.kyusui.app.ui.customer.createorder

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.kyusui.app.core.Result
import com.kyusui.app.domain.model.DeliveryLocation
import com.kyusui.app.domain.model.Order
import com.kyusui.app.domain.repository.CustomerRepository
import com.kyusui.app.domain.repository.OrderItemRequest
import com.kyusui.app.ui.customer.toSubmitError
import com.kyusui.app.ui.state.SubmitState
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * State pembuatan order.
 *
 * `submitState` menjadi guard duplicate submit: [submitOrder] langsung kembali
 * bila state sedang `Submitting`, sehingga tap ganda tidak mengirim dua
 * `POST /customer/orders`.
 */
data class CreateOrderUiState(
    val submitState: SubmitState = SubmitState.Idle,
    val createdOrder: Order? = null
) {
    val isSubmitting: Boolean get() = submitState is SubmitState.Submitting
}

@HiltViewModel
class CreateOrderViewModel @Inject constructor(
    private val customerRepository: CustomerRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(CreateOrderUiState())
    val uiState: StateFlow<CreateOrderUiState> = _uiState.asStateFlow()

    private val _navigateToOrderId = MutableStateFlow<String?>(null)
    val navigateToOrderId: StateFlow<String?> = _navigateToOrderId.asStateFlow()

    /**
     * Mengirim order. Hanya `product_id` dan `quantity` yang dikirim; seluruh
     * nominal dihitung ulang backend.
     *
     * @return true bila request benar-benar dikirim. False berarti request
     * ditolak karena guard duplicate submit atau keranjang tidak valid.
     */
    fun submitOrder(cartState: CartUiState): Boolean {
        if (_uiState.value.submitState is SubmitState.Submitting) return false
        if (!cartState.canSubmit) return false

        val location = DeliveryLocationFactory.create(cartState)

        val items = cartState.lines.map { line ->
            OrderItemRequest(productId = line.product.id, quantity = line.quantity)
        }

        if (items.isEmpty() || items.any { it.quantity <= 0 }) return false

        _uiState.update { it.copy(submitState = SubmitState.Submitting) }

        viewModelScope.launch {
            when (
                val result = customerRepository.createOrder(items = items, deliveryLocation = location)
            ) {
                is Result.Success -> {
                    _uiState.update {
                        it.copy(
                            submitState = SubmitState.Success(orderId = result.data.id),
                            createdOrder = result.data
                        )
                    }
                    _navigateToOrderId.value = result.data.id
                }
                is Result.Failure -> _uiState.update {
                    it.copy(submitState = result.toSubmitError())
                }
            }
        }

        return true
    }

    fun clearSubmitState() {
        _uiState.update { it.copy(submitState = SubmitState.Idle) }
    }

    fun consumeNavigation() {
        _navigateToOrderId.value = null
    }
}

/**
 * Pemamn delivery location dari state keranjang.
 *
 * Dipisah agar ViewModel tetap murni dan mudah diuji tanpa Android Context.
 */
internal object DeliveryLocationFactory {
    fun create(state: CartUiState): DeliveryLocation {
        val point = requireNotNull(state.location) { "Lokasi belum tersedia." }
        return DeliveryLocation(
            address = state.address.trim(),
            point = point
        )
    }
}