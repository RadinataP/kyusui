package com.kyusui.app.ui.customer.createorder

import com.kyusui.app.core.ForbiddenException
import com.kyusui.app.core.NoNetworkException
import com.kyusui.app.core.Result
import com.kyusui.app.core.UnauthorizedException
import com.kyusui.app.domain.model.CartLine
import com.kyusui.app.domain.model.DeliveryLocation
import com.kyusui.app.domain.model.GeoPoint
import com.kyusui.app.domain.model.Order
import com.kyusui.app.domain.model.OrderItem
import com.kyusui.app.domain.model.OrderPayment
import com.kyusui.app.domain.model.OrderStatus
import com.kyusui.app.domain.model.PaymentMethod
import com.kyusui.app.domain.model.PaymentStatus
import com.kyusui.app.domain.model.Product
import com.kyusui.app.domain.repository.CustomerRepository
import com.kyusui.app.domain.repository.OrderItemRequest
import com.kyusui.app.ui.state.SubmitState
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.mockk
import io.mockk.slot
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
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class CreateOrderViewModelTest {

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

    // --- Happy path ---

    @Test
    fun `successful submit exposes the created order id`() = runTest(dispatcher) {
        coEvery { repository.createOrder(any(), any()) } returns Result.success(order())

        val viewModel = CreateOrderViewModel(repository)
        val started = viewModel.submitOrder(validCart())

        assertTrue(started)
        assertTrue(viewModel.uiState.value.submitState is SubmitState.Submitting)
        advanceUntilIdle()

        val submitState = viewModel.uiState.value.submitState
        assertEquals(SubmitState.Success(orderId = "1001"), submitState)
        assertEquals("1001", viewModel.navigateToOrderId.value)
    }

    @Test
    fun `only product id and quantity are sent to the backend`() = runTest(dispatcher) {
        coEvery { repository.createOrder(any(), any()) } returns Result.success(order())

        val viewModel = CreateOrderViewModel(repository)
        viewModel.submitOrder(validCart())
        advanceUntilIdle()

        val itemsSlot = slot<List<OrderItemRequest>>()
        val locationSlot = slot<DeliveryLocation>()
        coVerify(exactly = 1) {
            repository.createOrder(capture(itemsSlot), capture(locationSlot))
        }

        assertEquals(listOf(OrderItemRequest(productId = "1", quantity = 2)), itemsSlot.captured)
        assertEquals("Jl. Merdeka No. 10", locationSlot.captured.address)
        assertEquals(GeoPoint(-0.9471, 100.4172), locationSlot.captured.point)
    }

    // --- Duplicate submission guard ---

    @Test
    fun `submitting twice only sends one request`() = runTest(dispatcher) {
        coEvery { repository.createOrder(any(), any()) } returns Result.success(order())

        val viewModel = CreateOrderViewModel(repository)

        val first = viewModel.submitOrder(validCart())
        val second = viewModel.submitOrder(validCart())

        assertTrue("First submit must be accepted", first)
        assertFalse("Second submit must be rejected while in flight", second)

        advanceUntilIdle()
        coVerify(exactly = 1) { repository.createOrder(any(), any()) }
    }

    @Test
    fun `submit is rejected while a previous submit is still submitting`() = runTest(dispatcher) {
        coEvery { repository.createOrder(any(), any()) } returns Result.success(order())

        val viewModel = CreateOrderViewModel(repository)
        viewModel.submitOrder(validCart())

        assertTrue(viewModel.uiState.value.isSubmitting)
        assertFalse(viewModel.submitOrder(validCart()))
        assertFalse(viewModel.submitOrder(validCart()))

        advanceUntilIdle()
        coVerify(exactly = 1) { repository.createOrder(any(), any()) }
    }

    @Test
    fun `submit is allowed again after a failure`() = runTest(dispatcher) {
        coEvery { repository.createOrder(any(), any()) } returns
            Result.failure(NoNetworkException())

        val viewModel = CreateOrderViewModel(repository)
        viewModel.submitOrder(validCart())
        advanceUntilIdle()

        assertTrue(viewModel.uiState.value.submitState is SubmitState.Error)

        coEvery { repository.createOrder(any(), any()) } returns Result.success(order())
        val retried = viewModel.submitOrder(validCart())
        advanceUntilIdle()

        assertTrue(retried)
        assertEquals(SubmitState.Success(orderId = "1001"), viewModel.uiState.value.submitState)
    }

    // --- Guard validitas lokal ---

    @Test
    fun `empty cart never reaches the backend`() = runTest(dispatcher) {
        val viewModel = CreateOrderViewModel(repository)

        assertFalse(viewModel.submitOrder(CartUiState()))
        advanceUntilIdle()

        coVerify(exactly = 0) { repository.createOrder(any(), any()) }
    }

    @Test
    fun `cart without address never reaches the backend`() = runTest(dispatcher) {
        val viewModel = CreateOrderViewModel(repository)

        val cart = validCart().copy(address = "   ")
        assertFalse(viewModel.submitOrder(cart))
        advanceUntilIdle()

        coVerify(exactly = 0) { repository.createOrder(any(), any()) }
    }

    @Test
    fun `cart without location never reaches the backend`() = runTest(dispatcher) {
        val viewModel = CreateOrderViewModel(repository)

        val cart = validCart().copy(location = null, locationStatus = LocationStatus.Unknown)
        assertFalse(viewModel.submitOrder(cart))
        advanceUntilIdle()

        coVerify(exactly = 0) { repository.createOrder(any(), any()) }
    }

    // --- Error mapping ---

    @Test
    fun `network failure produces a retryable submit error`() = runTest(dispatcher) {
        coEvery { repository.createOrder(any(), any()) } returns
            Result.failure(NoNetworkException())

        val viewModel = CreateOrderViewModel(repository)
        viewModel.submitOrder(validCart())
        advanceUntilIdle()

        val error = viewModel.uiState.value.submitState as SubmitState.Error
        assertTrue(error.isRetryable)
        assertFalse(error.isUnauthorized)
    }

    @Test
    fun `unauthorized submit error is flagged as unauthorized`() = runTest(dispatcher) {
        coEvery { repository.createOrder(any(), any()) } returns
            Result.failure(UnauthorizedException())

        val viewModel = CreateOrderViewModel(repository)
        viewModel.submitOrder(validCart())
        advanceUntilIdle()

        val error = viewModel.uiState.value.submitState as SubmitState.Error
        assertTrue(error.isUnauthorized)
        assertFalse(error.isRetryable)
    }

    @Test
    fun `forbidden submit error is not retryable`() = runTest(dispatcher) {
        coEvery { repository.createOrder(any(), any()) } returns
            Result.failure(ForbiddenException())

        val viewModel = CreateOrderViewModel(repository)
        viewModel.submitOrder(validCart())
        advanceUntilIdle()

        val error = viewModel.uiState.value.submitState as SubmitState.Error
        assertFalse(error.isRetryable)
        assertFalse(error.isUnauthorized)
    }

    // --- Navigation side effect ---

    @Test
    fun `navigation is consumed once so the screen is not revisited`() = runTest(dispatcher) {
        coEvery { repository.createOrder(any(), any()) } returns Result.success(order())

        val viewModel = CreateOrderViewModel(repository)
        viewModel.submitOrder(validCart())
        advanceUntilIdle()

        assertEquals("1001", viewModel.navigateToOrderId.value)
        viewModel.consumeNavigation()
        assertNull(viewModel.navigateToOrderId.value)
    }

    @Test
    fun `clearSubmitState returns the submit state to idle`() = runTest(dispatcher) {
        coEvery { repository.createOrder(any(), any()) } returns
            Result.failure(NoNetworkException())

        val viewModel = CreateOrderViewModel(repository)
        viewModel.submitOrder(validCart())
        advanceUntilIdle()
        viewModel.clearSubmitState()

        assertEquals(SubmitState.Idle, viewModel.uiState.value.submitState)
    }

    // --- Cart ---

    @Test
    fun `adding the same product twice increases the quantity`() {
        val cartViewModel = CartViewModel()
        val product = product()

        cartViewModel.addProduct(product)
        cartViewModel.addProduct(product)

        assertEquals(1, cartViewModel.uiState.value.lines.size)
        assertEquals(2, cartViewModel.uiState.value.lines.first().quantity)
        assertEquals(2, cartViewModel.uiState.value.totalQuantity)
    }

    @Test
    fun `unavailable product is never added to the cart`() {
        val cartViewModel = CartViewModel()

        cartViewModel.addProduct(product(isAvailable = false))

        assertTrue(cartViewModel.uiState.value.isEmpty)
    }

    @Test
    fun `setting quantity to zero removes the line`() {
        val cartViewModel = CartViewModel()
        cartViewModel.addProduct(product())

        cartViewModel.setQuantity("1", 0)

        assertTrue(cartViewModel.uiState.value.isEmpty)
    }

    @Test
    fun `preview subtotal is computed from backend prices`() {
        val cartViewModel = CartViewModel()
        cartViewModel.addProduct(product(id = "1", price = "8000.00"))
        cartViewModel.setQuantity("1", 2)

        assertEquals("16000.00", cartViewModel.uiState.value.previewSubtotal)
    }

    @Test
    fun `cart is not submittable until address and location are set`() {
        val cartViewModel = CartViewModel()
        cartViewModel.addProduct(product())

        assertFalse(cartViewModel.uiState.value.canSubmit)

        cartViewModel.updateAddress("Jl. Merdeka No. 10")
        assertFalse(cartViewModel.uiState.value.canSubmit)

        cartViewModel.updateLocation(GeoPoint(-0.9471, 100.4172))
        assertTrue(cartViewModel.uiState.value.canSubmit)
    }

    @Test
    fun `clearing the cart resets preview and submit readiness`() {
        val cartViewModel = CartViewModel()
        cartViewModel.addProduct(product())
        cartViewModel.updateAddress("Jl. Merdeka No. 10")
        cartViewModel.updateLocation(GeoPoint(-0.9471, 100.4172))

        cartViewModel.clear()

        val state = cartViewModel.uiState.value
        assertTrue(state.isEmpty)
        assertNull(state.previewSubtotal)
        assertFalse(state.canSubmit)
        assertNull(cartViewModel.deliveryLocation())
    }

    private fun validCart() = CartUiState(
        lines = listOf(CartLine(product = product(), quantity = 2)),
        address = "Jl. Merdeka No. 10",
        location = GeoPoint(-0.9471, 100.4172),
        locationStatus = LocationStatus.Ready,
        previewSubtotal = "16000.00"
    )

    private fun product(
        id: String = "1",
        name: String = "Air Galon",
        price: String = "8000.00",
        isAvailable: Boolean = true
    ) = Product(
        id = id,
        name = name,
        description = "Air galon",
        price = price,
        isAvailable = isAvailable
    )

    private fun order() = Order(
        id = "1001",
        orderNumber = "ORD-20260930-0001",
        customerId = "10",
        items = listOf(
            OrderItem(
                productId = "1",
                productName = "Air Galon",
                quantity = 2,
                unitPrice = "8000.00",
                lineTotal = "16000.00"
            )
        ),
        subtotalAmount = "16000.00",
        deliveryFee = "0.00",
        totalAmount = "16000.00",
        orderStatus = OrderStatus.MENUNGGU_PEMBAYARAN,
        payment = OrderPayment(
            id = "7001",
            paymentMethod = PaymentMethod.CASH,
            paymentStatus = PaymentStatus.PENDING,
            amount = "16000.00"
        )
    )
}