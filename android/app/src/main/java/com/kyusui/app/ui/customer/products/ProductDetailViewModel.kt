package com.kyusui.app.ui.customer.products

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import com.kyusui.app.domain.model.Product
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import javax.inject.Inject

/**
 * Detail produk tanpa request jaringan.
 *
 * Specification tidak menyediakan `GET /products/{id}`, jadi produk diteruskan
 * dari `GET /products` melalui argumen navigasi. Kalau argumen tidak tersedia
 * (misalnya proses dibunuh lalu dipulihkan), state-nya `Missing` dan UI
 * meminta customer kembali ke daftar produk. Tidak ada harga yang dihitung
 * ulang di Android.
 */
sealed interface ProductDetailUiState {
    data object Missing : ProductDetailUiState
    data class Ready(val product: Product) : ProductDetailUiState
}

@HiltViewModel
class ProductDetailViewModel @Inject constructor(
    savedStateHandle: SavedStateHandle
) : ViewModel() {

    private val _uiState: MutableStateFlow<ProductDetailUiState> = MutableStateFlow(
        ProductDetailUiState.Missing
    )
    val uiState: StateFlow<ProductDetailUiState> = _uiState.asStateFlow()

    init {
        val id: String? = savedStateHandle[ARG_PRODUCT_ID]
        val name: String? = savedStateHandle[ARG_PRODUCT_NAME]
        val price: String? = savedStateHandle[ARG_PRODUCT_PRICE]
        val description: String? = savedStateHandle[ARG_PRODUCT_DESCRIPTION]
        val isAvailable: Boolean? = savedStateHandle[ARG_PRODUCT_IS_AVAILABLE]

        if (id != null && name != null && price != null && isAvailable != null) {
            _uiState.value = ProductDetailUiState.Ready(
                Product(
                    id = id,
                    name = name,
                    description = description?.takeIf { it.isNotBlank() },
                    price = price,
                    isAvailable = isAvailable
                )
            )
        }
    }

    companion object {
        const val ARG_PRODUCT_ID = "productId"
        const val ARG_PRODUCT_NAME = "productName"
        const val ARG_PRODUCT_PRICE = "productPrice"
        const val ARG_PRODUCT_DESCRIPTION = "productDescription"
        const val ARG_PRODUCT_IS_AVAILABLE = "productIsAvailable"
    }
}