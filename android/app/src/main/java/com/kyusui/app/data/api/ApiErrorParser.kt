package com.kyusui.app.data.api

import com.kyusui.app.core.ForbiddenException
import com.kyusui.app.core.NetworkException
import com.kyusui.app.core.NotFoundException
import com.kyusui.app.core.ServerException
import com.kyusui.app.core.TimeoutException
import com.kyusui.app.core.UnauthorizedException
import com.kyusui.app.core.UnknownException
import com.kyusui.app.core.ValidationException
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json

@Serializable
data class ApiErrorDto(
    val message: String? = null,
    val errors: Map<String, List<String>> = emptyMap()
)

/**
 * Memetakan HTTP error body sesuai Response Envelope pada API specification section 4.4.
 *
 * Pesan yang ditampilkan ke pengguna selalu Bahasa Indonesia. Pesan per-field dari
 * backend diterjemahkan bila dikenali, dan tetap dipakai apa adanya bila tidak,
 * agar informasi spesifik dari server tidak pernah hilang.
 */
class ApiErrorParser(private val json: Json) {

    fun parse(statusCode: Int, rawBody: String?, httpMessage: String): NetworkException {
        val envelope = runCatching {
            if (rawBody.isNullOrBlank()) null else json.decodeFromString<ApiErrorDto>(rawBody)
        }.getOrNull()

        val fieldErrors = envelope?.errors
            .orEmpty()
            .filterValues { it.isNotEmpty() }
            .mapKeys { (field, _) -> field.substringAfterLast('.') }
            .mapValues { (_, messages) -> messages.map(::toIndonesian) }

        val message = envelope?.message?.takeIf { it.isNotBlank() }

        return when (statusCode) {
            401 -> UnauthorizedException(UNAUTHORIZED_MESSAGE)
            403 -> ForbiddenException(message ?: FORBIDDEN_MESSAGE)
            404 -> NotFoundException(message ?: NOT_FOUND_MESSAGE)
            408, 504 -> TimeoutException(TIMEOUT_MESSAGE)
            in 400..499 -> ValidationException(
                errors = fieldErrors,
                message = VALIDATION_MESSAGE
            )
            in 500..599 -> ServerException(statusCode, message ?: SERVER_MESSAGE)
            else -> UnknownException("HTTP $statusCode: $message")
        }
    }

    private fun toIndonesian(message: String): String {
        return VALIDATION_MESSAGES_INDONESIAN[message.trim().lowercase()] ?: message
    }

    companion object {
        const val UNAUTHORIZED_MESSAGE = "Sesi tidak valid atau telah berakhir. Silakan masuk kembali."
        const val FORBIDDEN_MESSAGE = "Anda tidak memiliki akses ke sumber daya ini."
        const val NOT_FOUND_MESSAGE = "Data yang diminta tidak ditemukan."
        const val TIMEOUT_MESSAGE = "Permintaan terlalu lama. Silakan coba lagi."
        const val VALIDATION_MESSAGE = "Data yang dimasukkan belum benar. Periksa kembali kolom yang ditandai."
        const val SERVER_MESSAGE = "Terjadi kesalahan pada server. Silakan coba lagi."

        private val VALIDATION_MESSAGES_INDONESIAN = mapOf(
            "the name field is required." to "Nama wajib diisi.",
            "the phone field is required." to "Nomor telepon wajib diisi.",
            "the email field is required." to "Email wajib diisi.",
            "the password field is required." to "Kata sandi wajib diisi.",
            "the login field is required." to "Nomor telepon atau email wajib diisi.",
            "the name must be at least 3 characters." to "Nama minimal 3 karakter.",
            "the name may not be greater than 255 characters." to "Nama maksimal 255 karakter.",
            "the email must be a valid email address." to "Format email tidak valid.",
            "the phone number is invalid." to "Format nomor telepon tidak valid.",
            "the phone must be a valid phone number." to "Format nomor telepon tidak valid.",
            "the password must be at least 8 characters." to "Kata sandi minimal 8 karakter.",
            "the password field must be at least 8 characters." to "Kata sandi minimal 8 karakter.",
            "the password confirmation does not match." to "Konfirmasi kata sandi tidak cocok.",
            "the email has already been taken." to "Email sudah terdaftar.",
            "the phone has already been taken." to "Nomor telepon sudah terdaftar.",
            "the phone number has already been taken." to "Nomor telepon sudah terdaftar."
        )
    }
}
