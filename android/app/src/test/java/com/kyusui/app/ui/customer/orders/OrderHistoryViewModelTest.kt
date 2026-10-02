package com.kyusui.app.ui.customer.orders

import com.kyusui.app.core.NoNetworkException
import com.kyusui.app.core.Result
import com.kyusui.app.core.UnauthorizedException
import com.kyusui.app.domain.model.Order
import com.kyusui.app.domain.model.OrderItem
import com.kyusui.app.domain.model.OrderStatus
import com.kyusui.app.domain.model.PaginatedResult
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
class OrderHistoryViewModelTest {

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

    @Test
    fun `history is loading before the response arrives`() = runTest(dispatcher) {
        coEvery { repository.getOrders(any(), any()) } returns Result.success(page(listOf(order(id = "1001"))))

        val viewModel = OrderHistoryViewModel(repository)

        assertTrue(viewModel.uiState.value.ordersState is LoadState.Loading)
        advanceUntilIdle()
    }

    @Test
    fun `orders are exposed in success state`() = runTest(dispatcher) {
        coEvery { repository.getOrders(any(), any()) } returns
            Result.success(page(listOf(order(id = "1001"), order(id = "1002"))))

        val viewModel = OrderHistoryViewModel(repository)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertEquals(2, state.allOrders.size)
        assertEquals(2, state.filteredOrders.size)
    }

    @Test
    fun `empty history becomes empty state`() = runTest(dispatcher) {
        coEvery { repository.getOrders(any(), any()) } returns Result.success(page(emptyList()))

        val viewModel = OrderHistoryViewModel(repository)
        advanceUntilIdle()

        val ordersState = viewModel.uiState.value.ordersState
        assertTrue(ordersState is LoadState.Empty)
        assertEquals(
            OrderHistoryViewModel.EMPTY_ORDERS_MESSAGE,
            viewModel.uiState.value.emptyMessage
        )
    }

    @Test
    fun `network failure becomes a retryable error`() = runTest(dispatcher) {
        coEvery { repository.getOrders(any(), any()) } returns
            Result.failure(NoNetworkException())

        val viewModel = OrderHistoryViewModel(repository)
        advanceUntilIdle()

        val error = viewModel.uiState.value.ordersState as LoadState.Error
        assertTrue(error.isRetryable)
        assertFalse(viewModel.uiState.value.isUnauthorized)
    }

    @Test
    fun `unauthorized history is flagged as unauthorized`() = runTest(dispatcher) {
        coEvery { repository.getOrders(any(), any()) } returns
            Result.failure(UnauthorizedException())

        val viewModel = OrderHistoryViewModel(repository)
        advanceUntilIdle()

        val error = viewModel.uiState.value.ordersState as LoadState.Error
        assertTrue(error.isUnauthorized)
        assertTrue(viewModel.uiState.value.isUnauthorized)
        assertFalse(error.isRetryable)
    }

    @Test
    fun `retry requests history again`() = runTest(dispatcher) {
        coEvery { repository.getOrders(any(), any()) } returns
            Result.failure(NoNetworkException())

        val viewModel = OrderHistoryViewModel(repository)
        advanceUntilIdle()

        coEvery { repository.getOrders(any(), any()) } returns Result.success(page(listOf(order(id = "1001"))))
        viewModel.loadOrders()
        advanceUntilIdle()

        assertEquals(1, viewModel.uiState.value.allOrders.size)
        coVerify(exactly = 2) { repository.getOrders(any(), any()) }
    }

    // --- Filtering ---

    @Test
    fun `active filter keeps unfinished orders`() = runTest(dispatcher) {
        coEvery { repository.getOrders(any(), any()) } returns Result.success(
            page(
                listOf(
                    order(id = "1001", OrderStatus.MENUNGGU_DIPROSES),
                    order(id = "1002", OrderStatus.SELESAI)
                )
            )
        )

        val viewModel = OrderHistoryViewModel(repository)
        advanceUntilIdle()
        viewModel.setStatusFilter(OrderStatusFilter.ACTIVE)

        val state = viewModel.uiState.value
        assertEquals(1, state.filteredOrders.size)
        assertEquals("1001", state.filteredOrders.first().id)
        assertFalse(state.hasNoMatchForFilter)
    }

    @Test
    fun `completed filter keeps finished orders`() = runTest(dispatcher) {
        coEvery { repository.getOrders(any(), any()) } returns Result.success(
            page(
                listOf(
                    order(id = "1001", OrderStatus.MENUNGGU_DIPROSES),
                    order(id = "1002", OrderStatus.SELESAI)
                )
            )
        )

        val viewModel = OrderHistoryViewModel(repository)
        advanceUntilIdle()
        viewModel.setStatusFilter(OrderStatusFilter.COMPLETED)

        val state = viewModel.uiState.value
        assertEquals(1, state.filteredOrders.size)
        assertEquals("1002", state.filteredOrders.first().id)
    }

    @Test
    fun `a filter with no match reports a distinct empty message`() = runTest(dispatcher) {
        coEvery { repository.getOrders(any(), any()) } returns
            Result.success(page(listOf(order(id = "1001", OrderStatus.SELESAI))))

        val viewModel = OrderHistoryViewModel(repository)
        advanceUntilIdle()
        viewModel.setStatusFilter(OrderStatusFilter.ACTIVE)

        val state = viewModel.uiState.value
        assertTrue(state.hasNoMatchForFilter)
        assertEquals(
            "Tidak ada order yang sedang aktif.",
            state.emptyMessage
        )
    }

    @Test
    fun `order with unknown status stays in the active list`() = runTest(dispatcher) {
        coEvery { repository.getOrders(any(), any()) } returns Result.success(
            page(listOf(order(id = "1001", null)))
        )

        val viewModel = OrderHistoryViewModel(repository)
        advanceUntilIdle()
        viewModel.setStatusFilter(OrderStatusFilter.ACTIVE)

        assertEquals(1, viewModel.uiState.value.filteredOrders.size)
    }

    @Test
    fun `first page reports more pages when total exceeds per page`() = runTest(dispatcher) {
        coEvery { repository.getOrders(1, any()) } returns Result.success(
            PaginatedResult(
                data = listOf(order(id = "1001")),
                currentPage = 1,
                perPage = OrderHistoryViewModel.PER_PAGE,
                total = OrderHistoryViewModel.PER_PAGE + 1
            )
        )

        val viewModel = OrderHistoryViewModel(repository)
        advanceUntilIdle()

        assertTrue(viewModel.uiState.value.hasMore)
        assertFalse(viewModel.uiState.value.isAppending)
    }

    @Test
    fun `append merges the next page and does not duplicate existing orders`() =
        runTest(dispatcher) {
            // total = per_page + 1: halaman 1 masih punya halaman lanjutan,
            // dan halaman 2 adalah halaman terakhir.
            coEvery { repository.getOrders(1, any()) } returns Result.success(
                PaginatedResult(
                    data = listOf(order(id = "1001")),
                    currentPage = 1,
                    perPage = OrderHistoryViewModel.PER_PAGE,
                    total = OrderHistoryViewModel.PER_PAGE + 1
                )
            )
            coEvery { repository.getOrders(2, any()) } returns Result.success(
                PaginatedResult(
                    // "1001" sudah tampil di halaman 1, jadi tidak boleh terappend lagi.
                    data = listOf(order(id = "1001"), order(id = "1002")),
                    currentPage = 2,
                    perPage = OrderHistoryViewModel.PER_PAGE,
                    total = OrderHistoryViewModel.PER_PAGE + 1
                )
            )

            val viewModel = OrderHistoryViewModel(repository)
            advanceUntilIdle()
            viewModel.loadNextPage()
            advanceUntilIdle()

            val state = viewModel.uiState.value
            assertEquals(2, state.page)
            assertEquals(listOf("1001", "1002"), state.allOrders.map { it.id })
            assertFalse(state.isAppending)
            assertFalse(state.hasMore)
        }

    @Test
    fun `failed append keeps loaded orders and stops further requests`() = runTest(dispatcher) {
        coEvery { repository.getOrders(1, any()) } returns Result.success(
            PaginatedResult(
                data = listOf(order(id = "1001")),
                currentPage = 1,
                perPage = OrderHistoryViewModel.PER_PAGE,
                total = OrderHistoryViewModel.PER_PAGE + 1
            )
        )
        coEvery { repository.getOrders(2, any()) } returns
            Result.failure(NoNetworkException())

        val viewModel = OrderHistoryViewModel(repository)
        advanceUntilIdle()
        viewModel.loadNextPage()
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertEquals(1, state.allOrders.size)
        assertTrue(state.ordersState is LoadState.Success)
        assertFalse(state.hasMore)
        assertFalse(state.isAppending)
    }

    @Test
    fun `load next page is ignored when no further page exists`() = runTest(dispatcher) {
        coEvery { repository.getOrders(any(), any()) } returns
            Result.success(page(listOf(order(id = "1001"))))

        val viewModel = OrderHistoryViewModel(repository)
        advanceUntilIdle()
        viewModel.loadNextPage()
        advanceUntilIdle()

        coVerify(exactly = 1) {
            repository.getOrders(1, OrderHistoryViewModel.PER_PAGE)
        }
    }

    @Test
    fun `status filter survives appending another page`() = runTest(dispatcher) {
        coEvery { repository.getOrders(1, any()) } returns Result.success(
            PaginatedResult(
                data = listOf(order(id = "1001", status = OrderStatus.SELESAI)),
                currentPage = 1,
                perPage = OrderHistoryViewModel.PER_PAGE,
                total = OrderHistoryViewModel.PER_PAGE + 1
            )
        )
        coEvery { repository.getOrders(2, any()) } returns Result.success(
            PaginatedResult(
                data = listOf(
                    order(id = "1002", status = OrderStatus.DIPROSES)
                ),
                currentPage = 2,
                perPage = OrderHistoryViewModel.PER_PAGE,
                total = OrderHistoryViewModel.PER_PAGE + 1
            )
        )

        val viewModel = OrderHistoryViewModel(repository)
        advanceUntilIdle()
        viewModel.setStatusFilter(OrderStatusFilter.COMPLETED)
        viewModel.loadNextPage()
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertEquals(OrderStatusFilter.COMPLETED, state.statusFilter)
        assertEquals(listOf("1001"), state.filteredOrders.map { it.id })
    }

    private fun page(orders: List<Order>) = PaginatedResult(
        data = orders,
        currentPage = 1,
        perPage = OrderHistoryViewModel.PER_PAGE,
        total = orders.size
    )

    private fun order(id: String, status: OrderStatus? = OrderStatus.SELESAI) = Order(
        id = id,
        orderNumber = "ORD-20260930-$id",
        customerId = "10",
        items = listOf(
            OrderItem(
                productId = "1",
                productName = "Air Galon",
                quantity = 1,
                unitPrice = "8000.00",
                lineTotal = "8000.00"
            )
        ),
        subtotalAmount = "8000.00",
        deliveryFee = "0.00",
        totalAmount = "8000.00",
        orderStatus = status,
        payment = null
    )
}