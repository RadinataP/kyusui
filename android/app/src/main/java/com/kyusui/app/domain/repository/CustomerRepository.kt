package com.kyusui.app.domain.repository

import com.kyusui.app.core.Result
import com.kyusui.app.domain.model.ActiveQris
import com.kyusui.app.domain.model.CustomerProfile
import com.kyusui.app.domain.model.DeliveryLocation
import com.kyusui.app.domain.model.Order
import com.kyusui.app.domain.model.PaginatedResult
import com.kyusui.app.domain.model.Payment
import com.kyusui.app.domain.model.PaymentMethod
import com.kyusui.app.domain.model.PaymentStatus
import com.kyusui.app.domain.model.Product
import com.kyusui.app.domain.model.ProofImageSelection

/**
 * Customer data source sesuai `06_KYUSUI_API_SPECIFICATION_REBUILT.md`
 * section 9 dan section 11.
 *
 * Repository ini hanya memakai endpoint yang benar-benar ada di specification.
 * Tidak ada `getProduct(productId)` karena specification tidak menyediakan
 * endpoint detail produk.
 *
 * Tidak ada operasi yang menulis `payment_status` dari client. `PAID` hanya
 * dibaca dari backend.
 */
interface CustomerRepository {

    suspend fun getProfile(): Result<CustomerProfile>

    suspend fun getProducts(page: Int, perPage: Int): Result<PaginatedResult<Product>>

    suspend fun createOrder(
        items: List<OrderItemRequest>,
        deliveryLocation: DeliveryLocation
    ): Result<Order>

    suspend fun getOrders(page: Int, perPage: Int): Result<PaginatedResult<Order>>

    suspend fun getOrder(orderId: String): Result<Order>

    // --- Section 11.1 ---

    suspend fun getActiveQris(): Result<ActiveQris>

    // --- Section 11.2 ---

    suspend fun selectPaymentMethod(
        orderId: String,
        method: PaymentMethod
    ): Result<Payment>

    // --- Section 11.3 ---

    suspend fun getPayment(orderId: String): Result<Payment>

    // --- Section 11.4 ---

    suspend fun getPayments(
        page: Int,
        perPage: Int,
        method: PaymentMethod? = null,
        status: PaymentStatus? = null
    ): Result<PaginatedResult<Payment>>

    // --- Section 11.5 ---

    suspend fun uploadQrIsProof(
        orderId: String,
        proof: ProofImageSelection
    ): Result<Payment>
}

/**
 * Item order yang dikirim ke backend. Backend menghitung ulang `unit_price`,
 * `line_total`, `subtotal_amount`, `delivery_fee`, dan `total_amount`, sehingga
 * client tidak pernah mengirim nominal.
 */
data class OrderItemRequest(
    val productId: String,
    val quantity: Int
)