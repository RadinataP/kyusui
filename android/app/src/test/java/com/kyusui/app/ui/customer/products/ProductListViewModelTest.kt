package com.kyusui.app.ui.customer.products

import com.kyusui.app.core.NoNetworkException
import com.kyusui.app.core.Result
import com.kyusui.app.core.UnauthorizedException
import com.kyusui.app.domain.model.PaginatedResult
import com.kyusui.app.domain.model.Product
import com.kyusui.app.domain.repository.CustomerRepository
import com.kyusui.app.ui.state.LoadState
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.mockk
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class ProductListViewModelTest {

    private val dispatcher = StandardTestDispatcher()
    private lateinit var repository: CustomerRepository

    @Before
    fun setup() {
        Dispatchers.setMain(dispatcher)
        repository = mockk(relaxed = true)
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    // --- Loading, Success, Empty ---

    @Test
    fun `initial state is loading before the first response arrives`() = runTest(dispatcher) {
        coEvery { repository.getProducts(any(), any()) } returns Result.success(paged(products()))

        val viewModel = ProductListViewModel(repository)

        assertTrue(viewModel.uiState.value.productsState is LoadState.Loading)
        advanceUntilIdle()
    }

    @Test
    fun `products are exposed as success state`() = runTest(dispatcher) {
        coEvery { repository.getProducts(any(), any()) } returns
            Result.success(paged(products()))

        val viewModel = ProductListViewModel(repository)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertEquals(2, state.products.size)
        assertEquals("Air Galon", state.products.first().name)
    }

    @Test
    fun `empty product list becomes empty state, not success`() = runTest(dispatcher) {
        coEvery { repository.getProducts(any(), any()) } returns Result.success(paged(emptyList()))

        val viewModel = ProductListViewModel(repository)
        advanceUntilIdle()

        val productsState = viewModel.uiState.value.productsState
        assertTrue(productsState is LoadState.Empty)
        assertEquals(
            ProductListViewModel.EMPTY_PRODUCTS_MESSAGE,
            (productsState as LoadState.Empty).message
        )
    }

    // --- Error ---

    @Test
    fun `network failure becomes retryable error state`() = runTest(dispatcher) {
        coEvery { repository.getProducts(any(), any()) } returns
            Result.failure(NoNetworkException())

        val viewModel = ProductListViewModel(repository)
        advanceUntilIdle()

        val productsState = viewModel.uiState.value.productsState
        assertTrue(productsState is LoadState.Error)
        val error = productsState as LoadState.Error
        assertTrue(error.isRetryable)
        assertFalse(error.isUnauthorized)
    }

    @Test
    fun `unauthorized response is flagged as unauthorized and not retryable`() = runTest(dispatcher) {
        coEvery { repository.getProducts(any(), any()) } returns
            Result.failure(UnauthorizedException())

        val viewModel = ProductListViewModel(repository)
        advanceUntilIdle()

        val error = viewModel.uiState.value.productsState as LoadState.Error
        assertTrue(error.isUnauthorized)
        assertFalse(error.isRetryable)
    }

    // --- Retry ---

    @Test
    fun `retry requests the first page again`() = runTest(dispatcher) {
        coEvery { repository.getProducts(any(), any()) } returns
            Result.failure(NoNetworkException())

        val viewModel = ProductListViewModel(repository)
        advanceUntilIdle()

        coEvery { repository.getProducts(any(), any()) } returns Result.success(paged(products()))
        viewModel.loadProducts()
        advanceUntilIdle()

        assertEquals(2, viewModel.uiState.value.products.size)
        coVerify(exactly = 2) { repository.getProducts(1, ProductListUiState.DEFAULT_PER_PAGE) }
    }

    // --- Pagination ---

    @Test
    fun `first page without more results disables load more`() = runTest(dispatcher) {
        coEvery { repository.getProducts(any(), any()) } returns
            Result.success(PaginatedResult(products(), currentPage = 1, perPage = 20, total = 2))

        val viewModel = ProductListViewModel(repository)
        advanceUntilIdle()

        assertFalse(viewModel.uiState.value.hasMore)
    }

@Test
    fun `append merges the next page and does not duplicate existing products`() = runTest(dispatcher) {
        // total=25 dengan per_page=20 berarti halaman 1 masih punya halaman
        // lanjutan, dan halaman 2 adalah halaman terakhir.
        coEvery { repository.getProducts(1, any()) } returns
            Result.success(PaginatedResult(products(), currentPage = 1, perPage = 20, total = 25))
        coEvery { repository.getProducts(2, any()) } returns
            Result.success(
                PaginatedResult(
                    data = listOf(
                        // Produk yang sama sudah ada di halaman 1.
                        product(id = "2", name = "Air Mineral"),
                        product(id = "3", name = "Air Mineral 600ml")
                    ),
                    currentPage = 2,
                    perPage = 20,
                    total = 25
                )
            )

        val viewModel = ProductListViewModel(repository)
        advanceUntilIdle()
        assertTrue("Halaman 1 harus punya halaman lanjutan", viewModel.uiState.value.hasMore)

        viewModel.loadNextPage()
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertEquals(2, state.page)
        assertEquals("Produk duplikat tidak boleh ikut terappend", 3, state.products.size)
        assertEquals(listOf("1", "2", "3"), state.products.map { it.id })
        assertFalse(state.isAppending)
        assertFalse(state.hasMore)
    }

    @Test
    fun `concurrent load more requests only trigger a single page request`() = runTest(dispatcher) {
        coEvery { repository.getProducts(1, any()) } returns
            Result.success(PaginatedResult(products(), currentPage = 1, perPage = 20, total = 100))
        coEvery { repository.getProducts(2, any()) } returns
            Result.success(
                PaginatedResult(
                    data = listOf(product(id = "2", name = "Air Mineral")),
                    currentPage = 2,
                    perPage = 20,
                    total = 100
                )
            )

        val viewModel = ProductListViewModel(repository)
        advanceUntilIdle()

        // Dua panggilan berurutan tanpa menunggu harus tetap satu request.
        viewModel.loadNextPage()
        viewModel.loadNextPage()
        advanceUntilIdle()

        coVerify(exactly = 1) { repository.getProducts(2, ProductListUiState.DEFAULT_PER_PAGE) }
    }

    private fun paged(products: List<Product>) = PaginatedResult(
        data = products,
        currentPage = 1,
        perPage = ProductListUiState.DEFAULT_PER_PAGE,
        total = products.size
    )

    private fun products() = listOf(
        product(id = "1", name = "Air Galon"),
        product(id = "2", name = "Air Mineral")
    )

    private fun product(
        id: String,
        name: String,
        price: String = "8000.00",
        isAvailable: Boolean = true
    ) = Product(
        id = id,
        name = name,
        description = "Deskripsi $name",
        price = price,
        isAvailable = isAvailable
    )
}