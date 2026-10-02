package com.kyusui.app.data.api

import com.kyusui.app.data.api.TolerantIdSerializer
import kotlinx.serialization.Serializable
import okhttp3.MultipartBody
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.Part
import retrofit2.http.PUT
import retrofit2.http.Path
import retrofit2.http.Query

/**
 * Customer API sesuai `06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 9 dan 11.
 *
 * Hanya endpoint yang benar-benar tertulis di specification:
 * - `GET /customer/profile`    (9.1)
 * - `PUT /customer/profile`    (9.2)
 * - `GET /products`            (9.3)
 * - `POST /customer/orders`    (9.4)
 * - `GET /customer/orders`     (9.5)
 * - `GET /customer/orders/{order}` (9.6)
 * - `GET /customer/payment/qris`   (11.1)
 * - `POST /customer/orders/{order}/payment` (11.2)
 * - `GET /customer/orders/{order}/payment`  (11.3)
 * - `GET /customer/payments`    (11.4)
 * - `POST /customer/orders/{order}/payment/proof` (11.5)
 *
 * Tidak ada endpoint `GET /products/{id}` di specification, jadi detail
 * produk diambil dari data yang sudah dimuat pada list, bukan dari request baru.
 */
interface CustomerApi {

    @GET("customer/profile")
    suspend fun profile(): EnvelopeDto<CustomerProfileDto>

    @PUT("customer/profile")
    suspend fun updateProfile(
        @Body request: UpdateCustomerProfileRequestDto
    ): EnvelopeDto<CustomerProfileDto>

    @GET("products")
    suspend fun products(
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = DEFAULT_PER_PAGE
    ): PageDto<CustomerProductDto>

    @POST("customer/orders")
    suspend fun createOrder(
        @Body request: CreateOrderRequestDto
    ): Response<EnvelopeDto<CustomerOrderDto>>

    @GET("customer/orders")
    suspend fun orders(
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = DEFAULT_PER_PAGE
    ): PageDto<CustomerOrderDto>

    @GET("customer/orders/{order}")
    suspend fun order(
        @Path("order") orderId: String
    ): EnvelopeDto<CustomerOrderDto>

    // --- Section 11.1 Customer — Get Active QRIS ---

    /**
     * QRIS aktif milik Berkah Water. Endpoint ini read-only untuk customer dan
     * mengembalikan `404` ketika QRIS belum dikonfigurasi.
     */
    @GET("customer/payment/qris")
    suspend fun activeQris(): EnvelopeDto<ActiveQrisDto>

    // --- Section 11.2 Customer — Create / Select Payment Method ---

    /**
     * Memilih `QRIS` atau `CASH`. Backend yang menentukan `payment_status`;
     * client tidak pernah mengirim status.
     */
    @POST("customer/orders/{order}/payment")
    suspend fun selectPaymentMethod(
        @Path("order") orderId: String,
        @Body request: SelectPaymentMethodRequestDto
    ): Response<EnvelopeDto<PaymentEnvelopeDto>>

    // --- Section 11.3 Customer — Get Payment ---

    @GET("customer/orders/{order}/payment")
    suspend fun payment(
        @Path("order") orderId: String
    ): EnvelopeDto<CustomerPaymentDto>

    // --- Section 11.4 Customer — Payment History ---

    @GET("customer/payments")
    suspend fun payments(
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = DEFAULT_PER_PAGE,
        @Query("payment_method") paymentMethod: String? = null,
        @Query("payment_status") paymentStatus: String? = null
    ): PageDto<CustomerPaymentDto>

    // --- Section 11.5 Customer — Upload QRIS Payment Proof ---

    /**
     * Multipart dengan form field `proof_image`. Response sukses hanya berarti
     * `WAITING_VERIFICATION`; `PAID` tetap milik backend.
     */
    @POST("customer/orders/{order}/payment/proof")
    suspend fun uploadQrIsProof(
        @Path("order") orderId: String,
        @Part proofImage: MultipartBody.Part
    ): Response<EnvelopeDto<PaymentEnvelopeDto>>

    companion object {
        const val DEFAULT_PER_PAGE = 20
    }
}

/**
 * Single resource envelope section 4.4.
 */
@Serializable
data class EnvelopeDto<T>(
    val data: T? = null,
    val message: String? = null
)

/**
 * Collection envelope section 4.4. Paginasi berada di dalam `meta`.
 */
@Serializable
data class PageDto<T>(
    val data: List<T> = emptyList(),
    val meta: PageMetaDto? = null,
    val message: String? = null
) {
    val currentPage: Int get() = meta?.current_page ?: 1

    // `per_page` yang tidak masuk akal dari backend tidak boleh membuat
    // `hasMore` selalu true, karena itu akan memicu permintaan halaman
    // berikutnya tanpa henti.
    val perPage: Int
        get() = meta?.per_page?.takeIf { it > 0 } ?: CustomerApi.DEFAULT_PER_PAGE

    val total: Int get() = meta?.total ?: data.size
    val hasMore: Boolean get() = data.isNotEmpty() && currentPage * perPage < total
}

@Serializable
data class PageMetaDto(
    val current_page: Int = 1,
    val per_page: Int = CustomerApi.DEFAULT_PER_PAGE,
    val total: Int = 0
)

// --- Section 7.2 Customer Profile ---

@Serializable
data class CustomerProfileDto(
    @Serializable(with = TolerantIdSerializer::class) val id: Long,
    @Serializable(with = TolerantIdSerializer::class) val user_id: Long,
    val name: String,
    val phone: String? = null,
    val email: String? = null,
    val default_address: String? = null
)

@Serializable
data class UpdateCustomerProfileRequestDto(
    val name: String? = null,
    val phone: String? = null,
    val email: String? = null,
    val default_address: String? = null
)

// --- Section 7.3 Product Resource ---

@Serializable
data class CustomerProductDto(
    @Serializable(with = TolerantIdSerializer::class) val id: Long,
    val name: String,
    val description: String? = null,
    val price: String,
    val is_available: Boolean = false
)

// --- Section 7.4 Order Resource ---

@Serializable
data class CustomerOrderDto(
    @Serializable(with = TolerantIdSerializer::class) val id: Long,
    val order_number: String? = null,
    @Serializable(with = TolerantIdSerializer::class) val customer_id: Long,
    val items: List<CustomerOrderItemDto> = emptyList(),
    val subtotal_amount: String = "0.00",
    val delivery_fee: String = "0.00",
    val total_amount: String = "0.00",
    val order_status: String? = null,
    val payment: CustomerOrderPaymentDto? = null
)

@Serializable
data class CustomerOrderItemDto(
    @Serializable(with = TolerantIdSerializer::class) val product_id: Long,
    val product_name: String = "",
    val quantity: Int = 0,
    val unit_price: String = "0.00",
    val line_total: String = "0.00"
)

@Serializable
data class CustomerOrderPaymentDto(
    @Serializable(with = TolerantIdSerializer::class) val id: Long,
    val payment_method: String? = null,
    val payment_status: String? = null,
    val amount: String = "0.00"
)

// --- Section 9.4 Create Order ---

/**
 * Android hanya mengirim `product_id` dan `quantity`. Harga, subtotal,
 * delivery fee, dan total dihitung ulang oleh backend.
 */
@Serializable
data class CreateOrderRequestDto(
    val items: List<CreateOrderItemDto>,
    val delivery_location: DeliveryLocationDto
)

@Serializable
data class CreateOrderItemDto(
    @Serializable(with = TolerantIdSerializer::class) val product_id: Long,
    val quantity: Int
)

@Serializable
data class DeliveryLocationDto(
    val latitude: Double,
    val longitude: Double,
    val address: String
)

// --- Section 7.5 Payment Resource / 7.6 Active QRIS ---

/**
 * Payment Resource section 7.5.
 *
 * Field provider sengaja tidak ada karena specification melarangnya:
 * tidak ada `provider_name`, `provider_reference`, `transaction_id`,
 * `payment_url`, atau `webhook_status`.
 */
@Serializable
data class CustomerPaymentDto(
    @Serializable(with = TolerantIdSerializer::class) val id: Long,
    @Serializable(with = TolerantIdSerializer::class) val order_id: Long,
    val payment_method: String? = null,
    val payment_status: String? = null,
    val amount: String = "0.00",
    val proof: PaymentProofDto? = null,
    @Serializable(with = TolerantIdSerializer::class) val verified_by: Long? = null,
    val verified_at: String? = null,
    val created_at: String? = null,
    val updated_at: String? = null
)

/**
 * Keberadaan bukti pembayaran. Backend hanya memberi status keberadaan, bukan
 * URL atau path, karena proof disimpan pada private storage.
 */
@Serializable
data class PaymentProofDto(
    val available: Boolean = false
)

/**
 * Active QRIS Resource section 7.6.
 */
@Serializable
data class ActiveQrisDto(
    val qris_image: String? = null,
    val updated_at: String? = null
)

/**
 * Wrapper yang dipakai endpoint 11.2 dan 11.5, yang keduanya membalut payment
 * di dalam objek `payment`.
 */
@Serializable
data class PaymentEnvelopeDto(
    val payment: CustomerPaymentDto? = null
)

/**
 * Request 11.2. Hanya `payment_method` yang dikirim; `payment_status` tidak
 * pernah dikirim dari client.
 */
@Serializable
data class SelectPaymentMethodRequestDto(
    val payment_method: String
)
