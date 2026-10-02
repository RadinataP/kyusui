package com.kyusui.app.core

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Test

class NetworkExceptionsTest {

    @Test
    fun `no network exception has default message`() {
        val exception = NoNetworkException()
        assertEquals("Tidak ada koneksi internet", exception.message)
    }

    @Test
    fun `timeout exception has default message`() {
        val exception = TimeoutException()
        assertEquals("Permintaan timeout", exception.message)
    }

    @Test
    fun `server exception has status code`() {
        val exception = ServerException(500, "Internal Server Error")
        assertEquals(500, exception.statusCode)
        assertEquals("Internal Server Error", exception.message)
    }

    @Test
    fun `unauthorized exception has default message`() {
        val exception = UnauthorizedException()
        assertEquals("Unauthorized", exception.message)
    }

    @Test
    fun `forbidden exception has default message`() {
        val exception = ForbiddenException()
        assertEquals("Forbidden", exception.message)
    }

    @Test
    fun `not found exception has default message`() {
        val exception = NotFoundException()
        assertEquals("Not found", exception.message)
    }

    @Test
    fun `validation exception has errors map`() {
        val errors = mapOf("email" to listOf("Email is required"))
        val exception = ValidationException(errors = errors)
        assertEquals(errors, exception.errors)
        assertEquals("Validation failed", exception.message)
    }

    @Test
    fun `parsing exception has default message`() {
        val exception = ParsingException()
        assertEquals("Gagal memproses respons", exception.message)
    }

    @Test
    fun `unknown exception has default message`() {
        val exception = UnknownException()
        assertEquals("Terjadi kesalahan tidak diketahui", exception.message)
    }

    @Test
    fun `all exceptions inherit from NetworkException`() {
        val exceptions = listOf(
            NoNetworkException(),
            TimeoutException(),
            ServerException(500),
            UnauthorizedException(),
            ForbiddenException(),
            NotFoundException(),
            ValidationException(),
            ParsingException(),
            UnknownException()
        )
        exceptions.forEach {
            assertNotNull(it as NetworkException)
        }
    }
}