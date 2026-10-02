package com.kyusui.app.data.mapper

import com.kyusui.app.data.api.ActiveQrisDto
import com.kyusui.app.data.api.CustomerOrderDto
import com.kyusui.app.data.api.CustomerOrderItemDto
import com.kyusui.app.data.api.CustomerOrderPaymentDto
import com.kyusui.app.data.api.CustomerPaymentDto
import com.kyusui.app.data.api.CustomerProductDto
import com.kyusui.app.data.api.CustomerProfileDto
import com.kyusui.app.data.api.PageDto
import com.kyusui.app.data.api.PaymentEnvelopeDto
import com.kyusui.app.data.api.PaymentProofDto
import com.kyusui.app.domain.model.ActiveQris
import com.kyusui.app.domain.model.CustomerProfile
import com.kyusui.app.domain.model.Order
import com.kyusui.app.domain.model.OrderItem
import com.kyusui.app.domain.model.OrderPayment
import com.kyusui.app.domain.model.OrderStatus
import com.kyusui.app.domain.model.PaginatedResult
import com.kyusui.app.domain.model.Payment
import com.kyusui.app.domain.model.PaymentMethod
import com.kyusui.app.domain.model.PaymentProof
import com.kyusui.app.domain.model.PaymentStatus
import com.kyusui.app.domain.model.Product

/**
 * Mapping DTO -> domain untuk Customer API.
 *
 * Nominal uang disalin apa adanya dari backend dan tidak pernah dihitung ulang
 * di Android. Status yang tidak dikenal dipetakan ke null supaya UI bisa
 * menampilkan "Status tidak dikenal" daripada mengarang nilai bisnis.
 */

fun CustomerProductDto.toDomain(): Product = Product(
    id = id.toString(),
    name = name,
    description = description?.takeIf { it.isNotBlank() },
    price = price,
    isAvailable = is_available
)

fun CustomerProfileDto.toDomain(): CustomerProfile = CustomerProfile(
    id = id.toString(),
    userId = user_id.toString(),
    name = name,
    phone = phone?.takeIf { it.isNotBlank() },
    email = email?.takeIf { it.isNotBlank() },
    defaultAddress = default_address?.takeIf { it.isNotBlank() }
)

fun CustomerOrderItemDto.toDomain(): OrderItem = OrderItem(
    productId = product_id.toString(),
    productName = product_name,
    quantity = quantity,
    unitPrice = unit_price,
    lineTotal = line_total
)

fun CustomerOrderPaymentDto.toDomain(): OrderPayment = OrderPayment(
    id = id.toString(),
    paymentMethod = PaymentMethod.fromApiValue(payment_method),
    paymentStatus = PaymentStatus.fromApiValue(payment_status),
    amount = amount
)

fun CustomerOrderDto.toDomain(): Order = Order(
    id = id.toString(),
    orderNumber = order_number.orEmpty(),
    customerId = customer_id.toString(),
    items = items.map { it.toDomain() },
    subtotalAmount = subtotal_amount,
    deliveryFee = delivery_fee,
    totalAmount = total_amount,
    orderStatus = OrderStatus.fromApiValue(order_status),
    payment = payment?.toDomain()
)

fun <D, M> PageDto<D>.toDomain(transform: (D) -> M): PaginatedResult<M> = PaginatedResult(
    data = data.map(transform),
    currentPage = currentPage,
    perPage = perPage,
    total = total
)

// --- Payment section 7.5 / 7.6 ---

fun PaymentProofDto.toDomain(): PaymentProof = PaymentProof(available = available)

fun CustomerPaymentDto.toDomain(): Payment = Payment(
    id = id.toString(),
    orderId = order_id.toString(),
    paymentMethod = PaymentMethod.fromApiValue(payment_method),
    // Status yang tidak dikenal dipetakan ke null, bukan ditebak. Android
    // tidak pernah menetapkan PAID secara lokal.
    paymentStatus = PaymentStatus.fromApiValue(payment_status),
    amount = amount,
    proof = proof?.toDomain(),
    verifiedBy = verified_by?.toString(),
    verifiedAt = verified_at?.takeIf { it.isNotBlank() },
    createdAt = created_at,
    updatedAt = updated_at
)

fun ActiveQrisDto.toDomain(): ActiveQris = ActiveQris(
    qrisImage = qris_image?.takeIf { it.isNotBlank() },
    updatedAt = updated_at
)

/**
 * Endpoint 11.2 dan 11.5 membalut payment di dalam objek `payment`.
 */
fun PaymentEnvelopeDto.requirePayment(): Payment =
    payment?.toDomain()
        ?: throw IllegalStateException("Respons backend tidak memuat payment.")