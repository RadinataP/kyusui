package com.kyusui.app.data.repository

import com.kyusui.app.core.Result
import com.kyusui.app.core.UnauthorizedException
import com.kyusui.app.data.api.CreateOrderItemDto
import com.kyusui.app.data.api.CreateOrderRequestDto
import com.kyusui.app.data.api.CustomerApi
import com.kyusui.app.data.api.CustomerOrderDto
import com.kyusui.app.data.api.CustomerProductDto
import com.kyusui.app.data.api.DeliveryLocationDto
import com.kyusui.app.data.api.EnvelopeDto
import com.kyusui.app.data.api.PageDto
import com.kyusui.app.data.api.PageMetaDto
import com.kyusui.app.data.upload.ProofFileSource
import com.kyusui.app.domain.model.DeliveryLocation
import com.kyusui.app.domain.model.GeoPoint
import com.kyusui.app.domain.repository.OrderItemRequest
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.mockk
import io.mockk.slot
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import retrofit2.Response

class CustomerRepositoryImplTest {

    private lateinit var api: CustomerApi
    private lateinit var proofFileSource: ProofFileSource
    private lateinit var repository: CustomerRepositoryImpl

    @Before
    fun setup() {
        api = mockk(relaxed = true)
        proofFileSource = mockk(relaxed = true)
        repository = CustomerRepositoryImpl(api, proofFileSource)
    }

    @Test
    fun `products are requested with the canonical pagination query`() = runTest {
        coEvery { api.products(any(), any()) } returns page(products = listOf(productDto()))

        val result = repository.getProducts(page = 2, perPage = 10)

        assertTrue(result is Result.Success)
        coVerify(exactly = 1) { api.products(page = 2, perPage = 10) }
    }

    @Test
    fun `product prices come from the backend untouched`() = runTest {
        coEvery { api.products(any(), any()) } returns page(products = listOf(productDto()))

        val products = (repository.getProducts(1, 20) as Result.Success).data.data

        assertEquals("8000.00", products.first().price)
    }

    @Test
    fun `create order sends only ids quantities and delivery location`() = runTest {
        coEvery { api.createOrder(any()) } returns Response.success(
            EnvelopeDto(data = orderDto())
        )

        repository.createOrder(
            items = listOf(OrderItemRequest(productId = "1", quantity = 2)),
            deliveryLocation = deliveryLocation()
        )

        val requestSlot = slot<CreateOrderRequestDto>()
        coVerify(exactly = 1) { api.createOrder(capture(requestSlot)) }

        val request = requestSlot.captured
        assertEquals(listOf(CreateOrderItemDto(product_id = 1L, quantity = 2)), request.items)
        assertEquals(
            DeliveryLocationDto(
                latitude = -0.9471,
                longitude = 100.4172,
                address = "Jl. Merdeka No. 10"
            ),
            request.delivery_location
        )
    }

    @Test
    fun `server totals are returned as authoritative after creating an order`() = runTest {
        coEvery { api.createOrder(any()) } returns Response.success(
            EnvelopeDto(
                data = orderDto(
                    subtotal = "16000.00",
                    deliveryFee = "5000.00",
                    total = "21000.00"
                )
            )
        )

        val order = (
            repository.createOrder(
                items = listOf(OrderItemRequest(productId = "1", quantity = 2)),
                deliveryLocation = deliveryLocation()
            ) as Result.Success
            ).data

        assertEquals("16000.00", order.subtotalAmount)
        assertEquals("5000.00", order.deliveryFee)
        assertEquals("21000.00", order.totalAmount)
    }

    @Test
    fun `invalid product id is reported as a failure instead of sending zero`() = runTest {
        val result = repository.createOrder(
            items = listOf(OrderItemRequest(productId = "not-a-number", quantity = 1)),
            deliveryLocation = deliveryLocation()
        )

        assertTrue(result is Result.Failure)
        coVerify(exactly = 0) { api.createOrder(any()) }
    }

    @Test
    fun `unauthorized response surfaces as a failure`() = runTest {
        coEvery { api.products(any(), any()) } throws UnauthorizedException()

        val result = repository.getProducts(1, 20)

        assertTrue(result is Result.Failure)
        assertTrue((result as Result.Failure).exception is UnauthorizedException)
    }

    @Test
    fun `order detail is requested by order id`() = runTest {
        coEvery { api.order("1001") } returns EnvelopeDto(data = orderDto())

        val result = repository.getOrder("1001")

        assertTrue(result is Result.Success)
        assertEquals("1001", (result as Result.Success).data.id)
        coVerify(exactly = 1) { api.order("1001") }
    }

    @Test
    fun `order history is requested with pagination`() = runTest {
        coEvery { api.orders(any(), any()) } returns orderPage(listOf(orderDto()))

        val result = repository.getOrders(page = 1, perPage = 50)

        assertTrue(result is Result.Success)
        coVerify(exactly = 1) { api.orders(page = 1, perPage = 50) }
    }

    @Test
    fun `empty envelope payload is reported as a failure`() = runTest {
        coEvery { api.order("1001") } returns EnvelopeDto(data = null)

        val result = repository.getOrder("1001")

        assertTrue(result is Result.Failure)
    }

    private fun deliveryLocation() = DeliveryLocation(
        address = "Jl. Merdeka No. 10",
        point = GeoPoint(latitude = -0.9471, longitude = 100.4172)
    )

    private fun productDto() = CustomerProductDto(
        id = 1L,
        name = "Air Galon",
        description = "Air galon",
        price = "8000.00",
        is_available = true
    )

    private fun orderDto(
        subtotal: String = "16000.00",
        deliveryFee: String = "0.00",
        total: String = "16000.00"
    ) = CustomerOrderDto(
        id = 1001L,
        order_number = "ORD-20260930-0001",
        customer_id = 10L,
        items = emptyList(),
        subtotal_amount = subtotal,
        delivery_fee = deliveryFee,
        total_amount = total,
        order_status = "MENUNGGU_DIPROSES",
        payment = null
    )

    private fun <T : Any> pageOf(items: List<T>): PageDto<T> = PageDto(
        data = items,
        meta = PageMetaDto(current_page = 1, per_page = 20, total = items.size)
    )

    private fun page(products: List<CustomerProductDto> = emptyList()) = pageOf(products)

    private fun orderPage(orders: List<CustomerOrderDto> = emptyList()) = pageOf(orders)
}