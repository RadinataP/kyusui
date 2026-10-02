package com.kyusui.app.core

import kotlinx.coroutines.CancellationException
import retrofit2.HttpException
import java.io.IOException
import java.net.SocketTimeoutException
import java.net.UnknownHostException

suspend fun <T> safeApiCall(block: suspend () -> T): Result<T> = try {
    Result.success(block())
} catch (e: CancellationException) {
    throw e
} catch (e: HttpException) {
    Result.failure(e.toNetworkException())
} catch (e: UnknownHostException) {
    Result.failure(NoNetworkException(cause = e))
} catch (e: SocketTimeoutException) {
    Result.failure(TimeoutException(cause = e))
} catch (e: IOException) {
    Result.failure(NoNetworkException(cause = e))
    } catch (e: NetworkException) {
        Result.failure(e)
    } catch (e: Exception) {
        Result.failure(UnknownException(e.message ?: "Terjadi kesalahan tidak diketahui", e))
    }


fun HttpException.toNetworkException(): NetworkException = when (code()) {
    401 -> UnauthorizedException(message ?: "Unauthorized", this)
    403 -> ForbiddenException(message ?: "Forbidden", this)
    404 -> NotFoundException(message ?: "Not found", this)
    408 -> TimeoutException(message ?: "Permintaan timeout", this)
    in 500..599 -> ServerException(code(), message ?: "Server error", this)
    in 400..499 -> ValidationException(message = "Validation failed: ${message()}", cause = this)
    else -> UnknownException("HTTP ${code()}: ${message()}", this)
}

fun Throwable.userMessage(): String = when (this) {
    is NoNetworkException -> message ?: "Tidak ada koneksi internet"
    is TimeoutException -> message ?: "Permintaan timeout"
    is ServerException -> "Server sedang bermasalah (${statusCode}), coba lagi nanti"
    is UnauthorizedException -> "Sesi berakhir, silakan login kembali"
    is ForbiddenException -> "Akses ditolak"
    is NotFoundException -> "Data tidak ditemukan"
    is ValidationException -> message ?: "Data yang dikirim tidak valid"
    is ParsingException -> message ?: "Gagal memproses respons"
    is UnknownException -> message ?: "Terjadi kesalahan tidak diketahui"
    else -> message ?: "Terjadi kesalahan tidak diketahui"
}
