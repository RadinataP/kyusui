package com.kyusui.app.ui.customer.products

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.kyusui.app.core.Result
import com.kyusui.app.domain.model.Product
import com.kyusui.app.domain.repository.CustomerRepository
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
 * State list produk untuk customer.
 *
 * `hasMore` dipakai untuk infinite scroll. Data produk selalu berasal dari
 * `GET /products`; specification tidak menyediakan endpoint detail produk,
 * jadi detail dibaca dari item yang sudah dimuat.
 */
data class ProductListUiState(
    val productsState: LoadState<List<Product>> = LoadState.Loading,
    val page: Int = FIRST_PAGE,
    val hasMore: Boolean = false,
    val isAppending: Boolean = false
) {
    val products: List<Product>
        get() = (productsState as? LoadState.Success)?.data.orEmpty()

    companion object {
        const val FIRST_PAGE = 1
        const val DEFAULT_PER_PAGE = 20
    }
}

@HiltViewModel
class ProductListViewModel @Inject constructor(
    private val customerRepository: CustomerRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(ProductListUiState())
    val uiState: StateFlow<ProductListUiState> = _uiState.asStateFlow()

    init {
        loadProducts()
    }

    fun loadProducts() {
        _uiState.update {
            it.copy(
                productsState = LoadState.Loading,
                page = ProductListUiState.FIRST_PAGE,
                hasMore = false,
                isAppending = false
            )
        }

        viewModelScope.launch {
            val result = customerRepository.getProducts(
                page = ProductListUiState.FIRST_PAGE,
                perPage = ProductListUiState.DEFAULT_PER_PAGE
            )

            _uiState.update { state ->
                state.copy(
                    productsState = result.toPagedListLoadState(EMPTY_PRODUCTS_MESSAGE),
                    hasMore = (result as? Result.Success)?.data?.hasMore ?: false
                )
            }
        }
    }

    /**
     * Memuat halaman berikutnya. Request yang sedang berjalan atau yang sudah
     * mencapai halaman terakhir diabaikan supaya tidak terjadi duplikasi.
     */
    fun loadNextPage() {
        val current = _uiState.value
        if (!current.hasMore || current.isAppending) return
        if (current.productsState !is LoadState.Success) return

        val nextPage = current.page + 1
        _uiState.update { it.copy(isAppending = true) }

        viewModelScope.launch {
            val result = customerRepository.getProducts(
                page = nextPage,
                perPage = ProductListUiState.DEFAULT_PER_PAGE
            )

            _uiState.update { state ->
                when (result) {
                    is Result.Success -> state.copy(
                        productsState = LoadState.Success(
                            mergeDistinct(state.products, result.data.data)
                        ),
                        page = nextPage,
                        hasMore = result.data.hasMore,
                        isAppending = false
                    )
                    // Halaman berikutnya gagal: data yang sudah tampil tetap
                    // dipertahankan, dan tidak ada halaman lanjutan yang ditandai.
                    is Result.Failure -> state.copy(
                        hasMore = false,
                        isAppending = false
                    )
                }
            }
        }
    }

    private fun mergeDistinct(existing: List<Product>, incoming: List<Product>): List<Product> =
        (existing + incoming).distinctBy { it.id }

    companion object {
        const val EMPTY_PRODUCTS_MESSAGE = "Belum ada produk yang tersedia."
    }
}