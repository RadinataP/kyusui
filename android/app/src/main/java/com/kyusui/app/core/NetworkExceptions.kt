package com.kyusui.app.core

open class NetworkException(message: String, cause: Throwable? = null) : Exception(message, cause)

class NoNetworkException(message: String = "Tidak ada koneksi internet", cause: Throwable? = null) : NetworkException(message, cause)

class TimeoutException(message: String = "Permintaan timeout", cause: Throwable? = null) : NetworkException(message, cause)

class ServerException(
    val statusCode: Int,
    message: String = "Server error",
    cause: Throwable? = null
) : NetworkException(message, cause)

class UnauthorizedException(message: String = "Unauthorized", cause: Throwable? = null) : NetworkException(message, cause)

class ForbiddenException(message: String = "Forbidden", cause: Throwable? = null) : NetworkException(message, cause)

class NotFoundException(message: String = "Not found", cause: Throwable? = null) : NetworkException(message, cause)

class ValidationException(
    val errors: Map<String, List<String>> = emptyMap(),
    message: String = "Validation failed",
    cause: Throwable? = null
) : NetworkException(message, cause)

class ParsingException(message: String = "Gagal memproses respons", cause: Throwable? = null) : NetworkException(message, cause)

class UnknownException(message: String = "Terjadi kesalahan tidak diketahui", cause: Throwable? = null) : NetworkException(message, cause)