package com.kyusui.app.domain.model

/**
 * Product Resource sesuai `06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 7.3.
 *
 * `price` adalah decimal string yang dikirim backend dan tidak pernah dihitung
 * ulang di Android. `isAvailable` mengikuti nilai `is_available` dari backend,
 * jadi Android tidak menentukan sendiri apakah produk boleh dibeli.
 */
data class Product(
    val id: String,
    val name: String,
    val description: String? = null,
    val price: String,
    val isAvailable: Boolean
)

/**
 * Order Resource sesuai section 7.4.
 *
 * Seluruh nominal di sini berasal dari backend. Android tidak menghitung
 * subtotal, delivery fee, maupun total final.
 */
data class Order(
    val id: String,
    val orderNumber: String,
    val customerId: String,
    val items: List<OrderItem>,
    val subtotalAmount: String,
    val deliveryFee: String,
    val totalAmount: String,
    val orderStatus: OrderStatus?,
    val payment: OrderPayment? = null
) {
    val requiresPayment: Boolean
        get() = payment?.paymentStatus == PaymentStatus.PENDING ||
            payment?.paymentStatus == null

    val canBePaid: Boolean
        get() = payment?.paymentStatus == PaymentStatus.PENDING
}

data class OrderItem(
    val productId: String,
    val productName: String,
    val quantity: Int,
    val unitPrice: String,
    val lineTotal: String
)

/**
 * Ringkasan payment yang ikut di dalam Order Resource section 7.4.
 */
data class OrderPayment(
    val id: String,
    val paymentMethod: PaymentMethod?,
    val paymentStatus: PaymentStatus?,
    val amount: String
)

/**
 * Order Status canonical section 6.1.
 *
 * `fromApiValue` mengembalikan null untuk nilai yang tidak dikenal supaya UI
 * menampilkan "Status tidak dikenal" dan tidak mengarang status bisnis.
 */
enum class OrderStatus(val apiValue: String, val displayName: String) {
    MENUNGGU_PEMBAYARAN("MENUNGGU_PEMBAYARAN", "Menunggu Pembayaran"),
    MENUNGGU_DIPROSES("MENUNGGU_DIPROSES", "Menunggu Diproses"),
    DIPROSES("DIPROSES", "Diproses"),
    DITUGASKAN("DITUGASKAN", "Ditugaskan"),
    DALAM_PENGANTARAN("DALAM_PENGANTARAN", "Dalam Pengantaran"),
    SELESAI("SELESAI", "Selesai");

    companion object {
        fun fromApiValue(value: String?): OrderStatus? =
            value?.trim()?.let { raw -> entries.firstOrNull { it.apiValue == raw } }
    }
}

enum class PaymentMethod(val apiValue: String, val displayName: String) {
    QRIS("QRIS", "QRIS"),
    CASH("CASH", "Tunai");

    companion object {
        fun fromApiValue(value: String?): PaymentMethod? =
            value?.trim()?.let { raw -> entries.firstOrNull { it.apiValue == raw } }
    }
}

enum class PaymentStatus(val apiValue: String, val displayName: String) {
    PENDING("PENDING", "Belum Dibayar"),
    WAITING_VERIFICATION("WAITING_VERIFICATION", "Menunggu Verifikasi"),
    PAID("PAID", "Sudah Dibayar");

    companion object {
        fun fromApiValue(value: String?): PaymentStatus? =
            value?.trim()?.let { raw -> entries.firstOrNull { it.apiValue == raw } }
    }
}

/**
 * Baris keranjang lokal. Ini state lokal non-authoritative untuk UX saja;
 * `productId` dan `quantity` yang dikirim ke backend, sedangkan price selalu
 * diambil ulang dari data produk backend.
 */
data class CartLine(
    val product: Product,
    val quantity: Int
)

data class PaginatedResult<T>(
    val data: List<T>,
    val currentPage: Int,
    val perPage: Int,
    val total: Int
) {
    val hasMore: Boolean
        get() = data.isNotEmpty() && perPage > 0 && currentPage * perPage < total
}

data class GeoPoint(
    val latitude: Double,
    val longitude: Double
)

data class DeliveryLocation(
    val address: String,
    val point: GeoPoint
)
