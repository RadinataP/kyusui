package com.kyusui.app.data.api

import com.kyusui.app.data.mapper.toDomain
import com.kyusui.app.domain.model.OrderStatus
import com.kyusui.app.domain.model.PaginatedResult
import com.kyusui.app.domain.model.PaymentMethod
import com.kyusui.app.domain.model.PaymentStatus
import com.kyusui.app.domain.model.Product
import kotlinx.serialization.json.Json
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Fixture di test ini diambil bentuk literal dari
 * `06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 4.4, 7.2, 7.3, 7.4, 9.4, dan 9.5.
 *
 * Test ini menjaga DTO tetap sinkron dengan specification, khususnya bahwa
 * nominal dan status hanya dibaca dari backend dan tidak pernah dihitung ulang
 * di Android.
 */
class CustomerApiContractTest {

    private val json = Json {
        ignoreUnknownKeys = true
        isLenient = true
        explicitNulls = false
        coerceInputValues = true
    }

    // --- 7.3 Product Resource ---

    @Test
    fun `product resource decodes the canonical fields`() {
        val body = """
            {
              "data": {
                "id": 1,
                "name": "Air Galon",
                "description": "Air galon",
                "price": "8000.00",
                "is_available": true
              },
              "message": "Success."
            }
        """.trimIndent()

        val envelope = json.decodeFromString<EnvelopeDto<CustomerProductDto>>(body)
        val product = requireNotNull(envelope.data).toDomain()

        assertEquals("1", product.id)
        assertEquals("Air Galon", product.name)
        assertEquals("Air galon", product.description)
        assertEquals("8000.00", product.price)
        assertTrue(product.isAvailable)
    }

    @Test
    fun `unavailable product is not made purchasable on the client`() {
        val body = """{"data":{"id":2,"name":"Air Mineral","price":"5000.00","is_available":false}}"""

        val product = requireNotNull(
            json.decodeFromString<EnvelopeDto<CustomerProductDto>>(body).data
        ).toDomain()

        assertFalse(product.isAvailable)
    }

    // --- 7.2 Customer Profile Resource ---

    @Test
    fun `customer profile decodes the flat profile resource`() {
        val body = """
            {
              "data": {
                "id": 10,
                "user_id": 1,
                "name": "Budi",
                "phone": "081234567890",
                "email": "budi@example.com",
                "default_address": "Alamat customer"
              },
              "message": "Success."
            }
        """.trimIndent()

        val profile = requireNotNull(
            json.decodeFromString<EnvelopeDto<CustomerProfileDto>>(body).data
        ).toDomain()

        assertEquals("10", profile.id)
        assertEquals("1", profile.userId)
        assertEquals("Budi", profile.name)
        assertEquals("081234567890", profile.phone)
        assertEquals("Alamat customer", profile.defaultAddress)
    }

    // --- 7.4 Order Resource ---

    @Test
    fun `order resource decodes items totals status and payment`() {
        val body = """
            {
              "data": {
                "id": 1001,
                "order_number": "ORD-20260930-0001",
                "customer_id": 10,
                "items": [
                  {
                    "product_id": 1,
                    "product_name": "Air Galon",
                    "quantity": 2,
                    "unit_price": "8000.00",
                    "line_total": "16000.00"
                  }
                ],
                "subtotal_amount": "16000.00",
                "delivery_fee": "0.00",
                "total_amount": "16000.00",
                "order_status": "MENUNGGU_DIPROSES",
                "payment": {
                  "id": 7001,
                  "payment_method": "CASH",
                  "payment_status": "PENDING",
                  "amount": "16000.00"
                },
                "assignment": null
              },
              "message": "Success."
            }
        """.trimIndent()

        val order = requireNotNull(
            json.decodeFromString<EnvelopeDto<CustomerOrderDto>>(body).data
        ).toDomain()

        assertEquals("1001", order.id)
        assertEquals("ORD-20260930-0001", order.orderNumber)
        assertEquals("10", order.customerId)
        assertEquals(1, order.items.size)

        val item = order.items.first()
        assertEquals("1", item.productId)
        assertEquals("Air Galon", item.productName)
        assertEquals(2, item.quantity)
        assertEquals("8000.00", item.unitPrice)
        assertEquals("16000.00", item.lineTotal)

        assertEquals("16000.00", order.subtotalAmount)
        assertEquals("0.00", order.deliveryFee)
        assertEquals("16000.00", order.totalAmount)
        assertEquals(OrderStatus.MENUNGGU_DIPROSES, order.orderStatus)

        val payment = requireNotNull(order.payment)
        assertEquals("7001", payment.id)
        assertEquals(PaymentMethod.CASH, payment.paymentMethod)
        assertEquals(PaymentStatus.PENDING, payment.paymentStatus)
        assertEquals("16000.00", payment.amount)
    }

    @Test
    fun `server totals are taken verbatim and never recomputed`() {
        val body = """
            {
              "data": {
                "id": 1002,
                "order_number": "ORD-20260930-0002",
                "customer_id": 10,
                "items": [
                  { "product_id": 1, "product_name": "Air Galon", "quantity": 1,
                    "unit_price": "8000.00", "line_total": "8000.00" }
                ],
                "subtotal_amount": "8000.00",
                "delivery_fee": "5000.00",
                "total_amount": "13000.00",
                "order_status": "MENUNGGU_DIPROSES"
              }
            }
        """.trimIndent()

        val order = requireNotNull(
            json.decodeFromString<EnvelopeDto<CustomerOrderDto>>(body).data
        ).toDomain()

        // Nilai tidak boleh diubah walau subtotal + delivery fee tampak jelas.
        assertEquals("8000.00", order.subtotalAmount)
        assertEquals("5000.00", order.deliveryFee)
        assertEquals("13000.00", order.totalAmount)
    }

    @Test
    fun `order without payment decodes with a null payment`() {
        val body = """
            {"data":{"id":1003,"order_number":"ORD-1","customer_id":10,"items":[],
             "subtotal_amount":"0.00","delivery_fee":"0.00","total_amount":"0.00",
             "order_status":"MENUNGGU_PEMBAYARAN","payment":null}}
        """.trimIndent()

        val order = requireNotNull(
            json.decodeFromString<EnvelopeDto<CustomerOrderDto>>(body).data
        ).toDomain()

        assertNull(order.payment)
        assertTrue(order.requiresPayment)
        assertFalse(order.canBePaid)
    }

    @Test
    fun `paid payment no longer requires payment`() {
        val body = """
            {"data":{"id":1004,"order_number":"ORD-2","customer_id":10,"items":[],
             "subtotal_amount":"0.00","delivery_fee":"0.00","total_amount":"0.00",
             "order_status":"SELESAI","payment":{"id":7002,"payment_method":"QRIS",
             "payment_status":"PAID","amount":"0.00"}}}
        """.trimIndent()

        val order = requireNotNull(
            json.decodeFromString<EnvelopeDto<CustomerOrderDto>>(body).data
        ).toDomain()

        assertEquals(PaymentStatus.PAID, order.payment?.paymentStatus)
        assertFalse(order.requiresPayment)
    }

    @Test
    fun `unknown status values are preserved as null instead of a guessed status`() {
        val body = """
            {"data":{"id":1005,"order_number":"ORD-3","customer_id":10,"items":[],
             "subtotal_amount":"0.00","delivery_fee":"0.00","total_amount":"0.00",
             "order_status":"DIPROSES_SPEKIAL"}}
        """.trimIndent()

        val order = requireNotNull(
            json.decodeFromString<EnvelopeDto<CustomerOrderDto>>(body).data
        ).toDomain()

        assertNull(order.orderStatus)
    }

    @Test
    fun `status values retired by the specification are not accepted`() {
        // PROCESSING, CONFIRMED, FAILED, EXPIRED, dan REJECTED bukan status valid.
        listOf("PROCESSING", "CONFIRMED", "FAILED", "EXPIRED", "REJECTED").forEach { value ->
            assertNull("$value harus dianggap tidak dikenal", OrderStatus.fromApiValue(value))
        }
    }

    @Test
    fun `retired payment status values are not accepted`() {
        listOf("PROCESSING", "CONFIRMED", "FAILED", "EXPIRED", "REJECTED").forEach { value ->
            assertNull("$value harus dianggap tidak dikenal", PaymentStatus.fromApiValue(value))
        }
    }

    // --- 9.4 Create Order request shape ---

    @Test
    fun `create order request sends only product id quantity and delivery location`() {
        val encoded = json.encodeToString(
            CreateOrderRequestDto.serializer(),
            CreateOrderRequestDto(
                items = listOf(CreateOrderItemDto(product_id = 1L, quantity = 2)),
                delivery_location = DeliveryLocationDto(
                    latitude = -0.9471,
                    longitude = 100.4172,
                    address = "Alamat pengantaran"
                )
            )
        )

        assertTrue(encoded.contains("\"product_id\":1"))
        assertTrue(encoded.contains("\"quantity\":2"))
        assertTrue(encoded.contains("\"delivery_location\""))
        assertTrue(encoded.contains("\"latitude\":-0.9471"))
        assertTrue(encoded.contains("\"address\":\"Alamat pengantaran\""))

        // Client tidak boleh mengirim nominal apa pun.
        assertFalse(encoded.contains("unit_price"))
        assertFalse(encoded.contains("line_total"))
        assertFalse(encoded.contains("subtotal"))
        assertFalse(encoded.contains("delivery_fee"))
        assertFalse(encoded.contains("total_amount"))
        assertFalse(encoded.contains("order_status"))
        assertFalse(encoded.contains("notes"))
    }

    // --- 4.4 Pagination envelope ---

    @Test
    fun `paginated products decode data and meta`() {
        val body = """
            {
              "data": [
                { "id": 1, "name": "Air Galon", "price": "8000.00", "is_available": true },
                { "id": 2, "name": "Air Mineral", "price": "5000.00", "is_available": true }
              ],
              "meta": { "current_page": 1, "per_page": 20, "total": 42 },
              "message": "Success."
            }
        """.trimIndent()

        val page = json.decodeFromString<PageDto<CustomerProductDto>>(body)

        assertEquals(2, page.data.size)
        assertEquals(1, page.currentPage)
        assertEquals(20, page.perPage)
        assertEquals(42, page.total)
        assertTrue(page.hasMore)

        val domain = page.toDomain { it.toDomain() }
        assertEquals(2, domain.data.size)
        assertEquals(42, domain.total)
        assertTrue(domain.hasMore)
    }

    @Test
    fun `last page reports no more results`() {
        val body = """
            {
              "data": [ { "id": 1, "name": "Air Galon", "price": "8000.00", "is_available": true } ],
              "meta": { "current_page": 3, "per_page": 20, "total": 42 },
              "message": "Success."
            }
        """.trimIndent()

        val page = json.decodeFromString<PageDto<CustomerProductDto>>(body)

        assertEquals(3, page.currentPage)
        assertFalse(page.hasMore)
    }

    @Test
    fun `paginated orders decode the same envelope shape`() {
        val body = """
            {
              "data": [
                { "id": 1001, "order_number": "ORD-1", "customer_id": 10, "items": [],
                  "subtotal_amount": "0.00", "delivery_fee": "0.00", "total_amount": "0.00",
                  "order_status": "SELESAI" }
              ],
              "meta": { "current_page": 1, "per_page": 20, "total": 1 },
              "message": "Success."
            }
        """.trimIndent()

        val domain = json.decodeFromString<PageDto<CustomerOrderDto>>(body)
            .toDomain { it.toDomain() }

        assertEquals(1, domain.data.size)
        assertEquals("ORD-1", domain.data.first().orderNumber)
        assertFalse(domain.hasMore)
    }

    @Test
    fun `missing meta falls back to safe defaults instead of crashing`() {
        val body = """{"data":[{"id":1,"name":"Air Galon","price":"8000.00","is_available":true}]}"""

        val page = json.decodeFromString<PageDto<CustomerProductDto>>(body)

        assertEquals(1, page.currentPage)
        assertEquals(1, page.total)
        assertFalse(page.hasMore)
    }

    @Test
    fun `empty data with meta total zero is still decoded`() {
        val body = """{"data":[],"meta":{"current_page":1,"per_page":20,"total":0},"message":"Success."}"""

        val page = json.decodeFromString<PageDto<CustomerProductDto>>(body)

        assertTrue(page.data.isEmpty())
        assertEquals(0, page.total)
        assertFalse(page.hasMore)
    }

    @Test
    fun `non positive per page from backend does not loop pagination forever`() {
        // Backend yang mengirim per_page=0 membuat currentPage * perPage selalu 0,
        // sehingga hasMore akan true di halaman berapa pun dan UI meminta
        // halaman berikutnya tanpa henti. per_page dikoreksi ke default supaya
        // perhitungannya kembali masuk akal dan loop pasti berhenti.
        val shallowPage = """
            {"data":[{"id":1,"name":"Air Galon","price":"8000.00","is_available":true}],
             "meta":{"current_page":1,"per_page":0,"total":9999}}
        """.trimIndent()
        val deepPage = """
            {"data":[{"id":1,"name":"Air Galon","price":"8000.00","is_available":true}],
             "meta":{"current_page":900,"per_page":0,"total":9999}}
        """.trimIndent()

        val first = json.decodeFromString<PageDto<CustomerProductDto>>(shallowPage)
        val deep = json.decodeFromString<PageDto<CustomerProductDto>>(deepPage)

        assertEquals(CustomerApi.DEFAULT_PER_PAGE, first.perPage)
        // Halaman pertama masih wajar untuk dimuat, tapi halaman dalam
        // harus melaporkan tidak ada lagi halaman, justru loop tak berakhir.
        assertTrue(first.hasMore)
        assertFalse("Halaman dalam tidak boleh melaporkan hasMore selamanya", deep.hasMore)
    }

    @Test
    fun `domain result rejects non positive per page`() {
        val result = PaginatedResult(
            data = listOf(
                Product(
                    id = "1",
                    name = "Air Galon",
                    description = null,
                    price = "8000.00",
                    isAvailable = true
                )
            ),
            currentPage = 900,
            perPage = 0,
            total = 9999
        )

        assertFalse(result.hasMore)
    }

    // --- 9.2 Update profile request ---

    @Test
    fun `update profile request uses the allowed field names`() {
        val encoded = json.encodeToString(
            UpdateCustomerProfileRequestDto.serializer(),
            UpdateCustomerProfileRequestDto(
                name = "Budi Updated",
                phone = "081234567890",
                email = "budi@example.com",
                default_address = "Alamat baru"
            )
        )

        assertTrue(encoded.contains("\"default_address\""))
        assertFalse(encoded.contains("addresses"))
        assertFalse(encoded.contains("\"role\""))
    }

    // --- 11.1 Active QRIS ---

    @Test
    fun `active qris decodes the private image reference`() {
        val body = """
            {
              "data": {
                "qris_image": "/storage/qris/berkah-water.png",
                "updated_at": "2026-01-01T00:00:00Z"
              },
              "message": "Success."
            }
        """.trimIndent()

        val envelope = json.decodeFromString<EnvelopeDto<ActiveQrisDto>>(body)
        val qris = requireNotNull(envelope.data).toDomain()

        assertEquals("/storage/qris/berkah-water.png", qris.qrisImage)
        assertTrue(qris.isDisplayable)
    }

    @Test
    fun `active qris without an image is not displayable`() {
        val body = """{"data": {"qris_image": null, "updated_at": null}}"""

        val qris = requireNotNull(
            json.decodeFromString<EnvelopeDto<ActiveQrisDto>>(body).data
        ).toDomain()

        assertFalse(qris.isDisplayable)
    }

    // --- 11.2 Select payment method request ---

    @Test
    fun `select method request never sends a payment status`() {
        val encoded = json.encodeToString(
            SelectPaymentMethodRequestDto.serializer(),
            SelectPaymentMethodRequestDto(payment_method = "QRIS")
        )

        assertTrue(encoded.contains("\"payment_method\":\"QRIS\""))
        assertFalse(encoded.contains("payment_status"))
        assertFalse(encoded.contains("amount"))
    }

    @Test
    fun `select method request encodes cash`() {
        val encoded = json.encodeToString(
            SelectPaymentMethodRequestDto.serializer(),
            SelectPaymentMethodRequestDto(payment_method = "CASH")
        )

        assertTrue(encoded.contains("\"CASH\""))
    }

    // --- 11.2 / 11.5 Payment envelope ---

    @Test
    fun `payment envelope decodes the payment resource`() {
        val body = """
            {
              "data": {
                "payment": {
                  "id": 9,
                  "order_id": 1001,
                  "payment_method": "QRIS",
                  "payment_status": "WAITING_VERIFICATION",
                  "amount": "21000.00",
                  "proof": { "available": true },
                  "verified_by": null,
                  "verified_at": null,
                  "created_at": "2026-01-01T00:00:00Z",
                  "updated_at": "2026-01-02T00:00:00Z"
                }
              },
              "message": "Success."
            }
        """.trimIndent()

        val envelope = json.decodeFromString<EnvelopeDto<PaymentEnvelopeDto>>(body)
        val payment = requireNotNull(requireNotNull(envelope.data).payment).toDomain()

        assertEquals("9", payment.id)
        assertEquals("1001", payment.orderId)
        assertEquals(PaymentMethod.QRIS, payment.paymentMethod)
        assertEquals(PaymentStatus.WAITING_VERIFICATION, payment.paymentStatus)
        assertEquals("21000.00", payment.amount)
        assertTrue(payment.hasProof)
    }

    @Test
    fun `payment proof resource only exposes availability and never a url`() {
        val body = """
            {
              "data": {
                "id": 9,
                "order_id": 1001,
                "payment_method": "QRIS",
                "payment_status": "WAITING_VERIFICATION",
                "amount": "21000.00",
                "proof": { "available": true }
              }
            }
        """.trimIndent()

        val payment = requireNotNull(
            json.decodeFromString<EnvelopeDto<CustomerPaymentDto>>(body).data
        ).toDomain()

        assertEquals(true, payment.proof?.available)

        // Bukti hanya boleh diekspos sebagai status keberadaan. Tidak boleh ada
        // field yang bisa dipakai menyimpan atau menampilkan bukti ke customer.
        val proofFields = PaymentProofDto::class.java.declaredFields.map { it.name }
        listOf("url", "path", "photo", "image", "file", "public").forEach { leaked ->
            assertFalse(
                "PaymentProofDto tidak boleh punya field $leaked",
                proofFields.any { it.contains(leaked, ignoreCase = true) }
            )
        }
        assertTrue(proofFields.any { it.contains("available", ignoreCase = true) })
    }

    @Test
    fun `payment amount is taken verbatim from the backend`() {
        val body = """
            {
              "data": {
                "id": 9,
                "order_id": 1001,
                "payment_method": "CASH",
                "payment_status": "PENDING",
                "amount": "21500.50"
              }
            }
        """.trimIndent()

        val payment = requireNotNull(
            json.decodeFromString<EnvelopeDto<CustomerPaymentDto>>(body).data
        ).toDomain()

        assertEquals("21500.50", payment.amount)
    }

    // --- Legacy guard ---

    @Test
    fun `payment resource carries no legacy gateway or transaction fields`() {
        val declaredFields = CustomerPaymentDto::class.java.declaredFields.map { it.name }

        // Tidak ada jejak payment gateway, webhook, atau transaksi dinamis.
        listOf("provider", "gateway", "midtrans", "payment_url", "webhook_status")
            .forEach { legacy ->
                assertFalse(
                    "Payment resource tidak boleh punya field $legacy",
                    declaredFields.any { it.contains(legacy, ignoreCase = true) }
                )
            }
    }
}