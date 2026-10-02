package com.kyusui.app.ui.customer.createorder

import androidx.lifecycle.ViewModel
import com.kyusui.app.core.PreviewLine
import com.kyusui.app.core.Money
import com.kyusui.app.domain.model.CartLine
import com.kyusui.app.domain.model.DeliveryLocation
import com.kyusui.app.domain.model.GeoPoint
import com.kyusui.app.domain.model.Product
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import javax.inject.Inject

/**
 * Keranjang lokal untuk menyusun order.
 *
 * Keranjang hanya menyimpan `productId` dan `quantity`. Harga yang ditampilkan
 * adalah harga dari `GET /products`, dan subtotal di layar ini hanya estimasi
 * UX: backend menghitung ulang subtotal, delivery fee, dan total final saat
 * `POST /customer/orders` diproses.
 */
data class CartUiState(
    val lines: List<CartLine> = emptyList(),
    val address: String = "",
    val location: GeoPoint? = null,
    val locationStatus: LocationStatus = LocationStatus.Unknown,
    val previewSubtotal: String? = null
) {
    val isEmpty: Boolean get() = lines.isEmpty()

    val totalQuantity: Int get() = lines.sumOf { it.quantity }

    val canSubmit: Boolean
        get() = lines.isNotEmpty() &&
            address.isNotBlank() &&
            location != null &&
            locationStatus == LocationStatus.Ready
}

enum class LocationStatus {
    Unknown,
    Locating,
    Ready,
    PermissionDenied,
    Unavailable
}

@HiltViewModel
class CartViewModel @Inject constructor() : ViewModel() {

    private val _uiState = MutableStateFlow(CartUiState())
    val uiState: StateFlow<CartUiState> = _uiState.asStateFlow()

    fun addProduct(product: Product) {
        if (!product.isAvailable) return
        _uiState.update { state ->
            val existing = state.lines.firstOrNull { it.product.id == product.id }
            val lines = if (existing == null) {
                state.lines + CartLine(product = product, quantity = 1)
            } else {
                state.lines.map { line ->
                    if (line.product.id == product.id) {
                        line.copy(quantity = line.quantity + 1)
                    } else {
                        line
                    }
                }
            }
            state.copy(lines = lines).recalculate()
        }
    }

    fun setQuantity(productId: String, quantity: Int) {
        _uiState.update { state ->
            val lines = when {
                quantity <= 0 -> state.lines.filterNot { it.product.id == productId }
                else -> state.lines.map { line ->
                    if (line.product.id == productId) line.copy(quantity = quantity) else line
                }
            }
            state.copy(lines = lines).recalculate()
        }
    }

    fun removeProduct(productId: String) {
        _uiState.update { state ->
            state.copy(
                lines = state.lines.filterNot { it.product.id == productId }
            ).recalculate()
        }
    }

    fun updateAddress(address: String) {
        _uiState.update { it.copy(address = address) }
    }

    fun updateLocationStatus(status: LocationStatus) {
        _uiState.update { it.copy(locationStatus = status) }
    }

    fun updateLocation(point: GeoPoint) {
        _uiState.update { it.copy(location = point, locationStatus = LocationStatus.Ready) }
    }

    /**
     * Dipanggil setelah order berhasil dibuat supaya keranjang tidak
     * terkirim ulang tidak sengaja.
     */
    fun clear() {
        _uiState.value = CartUiState()
    }

    fun deliveryLocation(): DeliveryLocation? {
        val state = _uiState.value
        val point = state.location ?: return null
        if (state.address.isBlank()) return null
        return DeliveryLocation(address = state.address.trim(), point = point)
    }

    private fun CartUiState.recalculate(): CartUiState = copy(
        previewSubtotal = Money.previewSubtotal(
            lines.map { PreviewLine(unitPrice = it.product.price, quantity = it.quantity) }
        )
    )
}