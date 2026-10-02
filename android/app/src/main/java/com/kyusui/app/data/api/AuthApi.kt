package com.kyusui.app.data.api

import kotlinx.serialization.KSerializer
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.descriptors.PrimitiveKind
import kotlinx.serialization.descriptors.PrimitiveSerialDescriptor
import kotlinx.serialization.descriptors.SerialDescriptor
import kotlinx.serialization.encoding.Decoder
import kotlinx.serialization.encoding.Encoder
import kotlinx.serialization.json.JsonDecoder
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.jsonPrimitive
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST

/**
 * Auth API sesuai `06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 8.
 *
 * Hanya empat endpoint yang dipakai:
 * - `POST /auth/register` (8.1)
 * - `POST /auth/login`    (8.2)
 * - `POST /auth/logout`   (8.3)
 * - `GET  /auth/me`       (8.4)
 *
 * Request dan response mengikuti bentuk yang tertulis di specification.
 * Password dan token tidak pernah masuk ke log maupun source.
 */
interface AuthApi {

    @POST("auth/register")
    suspend fun register(@Body request: AuthRegisterRequestDto): AuthEnvelopeDto<AuthSessionDto>

    @POST("auth/login")
    suspend fun login(@Body request: AuthLoginRequestDto): AuthEnvelopeDto<AuthSessionDto>

    @POST("auth/logout")
    suspend fun logout(): Response<Unit>

    @GET("auth/me")
    suspend fun currentUser(): AuthCurrentUserDto
}

// --- Request (section 8.1 dan 8.2) ---

/**
 * Section 8.1. Register adalah registrasi customer anonim, sehingga `role`
 * tidak pernah dikirim dari Android. Role ditetapkan backend.
 */
@Serializable
data class AuthRegisterRequestDto(
    val name: String,
    val phone: String,
    val email: String? = null,
    val password: String,
    @SerialName("password_confirmation") val passwordConfirmation: String
)

/**
 * Section 8.2. Backend menerima satu field `login`, yang pada specification
 * berisi nomor telepon. Field `email` tidak ada di contract login.
 */
@Serializable
data class AuthLoginRequestDto(
    val login: String,
    val password: String
)

// --- Response envelope (section 4.4) ---

@Serializable
data class AuthEnvelopeDto<T>(
    val data: T? = null,
    val message: String? = null
)

@Serializable
data class AuthSessionDto(
    val user: AuthUserDto,
    val token: String
)

/**
 * Section 7.1 User Resource.
 *
 * Specification tidak menyertakan `created_at` dan `updated_at` pada User
 * Resource, dan `role` berupa object `name` + `display_name`, bukan string.
 */
@Serializable
data class AuthUserDto(
    @Serializable(with = TolerantIdSerializer::class) val id: Long,
    val name: String,
    val email: String? = null,
    val phone: String? = null,
    val role: AuthRoleDto,
    val status: String? = null
)

@Serializable
data class AuthRoleDto(
    val name: String,
    val display_name: String? = null
)

/**
 * `GET /auth/me` mengembalikan User Resource. Section 4.4 membungkus single
 * resource dalam `data`, jadi bentuk yang terutama dibaca adalah envelope. Bentuk
 * polos tanpa envelope juga diterima agar session restoration tidak gagal total
 * hanya karena perbedaan bentuk.
 */
@Serializable
data class AuthCurrentUserDto(
    val data: AuthUserDto? = null,
    val message: String? = null,
    val id: Long? = null,
    val name: String? = null,
    val email: String? = null,
    val phone: String? = null,
    val role: AuthRoleDto? = null,
    val status: String? = null
) {
    fun resolveUser(): AuthUserDto? {
        data?.let { return it }
        val fallbackId = id ?: return null
        val fallbackRole = role ?: return null
        return AuthUserDto(
            id = fallbackId,
            name = name.orEmpty(),
            email = email,
            phone = phone,
            role = fallbackRole,
            status = status
        )
    }
}

/**
 * Specification menulis `id` sebagai angka. Serializer ini menerima angka maupun
 * string agar perubahan tipe di backend tidak membuat session restoration crash.
 */
object TolerantIdSerializer : KSerializer<Long> {

    override val descriptor: SerialDescriptor =
        PrimitiveSerialDescriptor("TolerantId", PrimitiveKind.LONG)

    override fun serialize(encoder: Encoder, value: Long) {
        encoder.encodeLong(value)
    }

    override fun deserialize(decoder: Decoder): Long {
        val jsonDecoder = decoder as? JsonDecoder
            ?: return decoder.decodeLong()
        val primitive: JsonPrimitive = jsonDecoder.decodeJsonElement().jsonPrimitive
        return primitive.content.trim().toLongOrNull()
            ?: throw IllegalArgumentException("User id is not a valid number: ${primitive.content}")
    }
}
