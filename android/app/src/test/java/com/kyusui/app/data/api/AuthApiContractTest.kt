package com.kyusui.app.data.api

import com.kyusui.app.core.ForbiddenException
import com.kyusui.app.core.NotFoundException
import com.kyusui.app.core.ServerException
import com.kyusui.app.core.TimeoutException
import com.kyusui.app.core.UnauthorizedException
import com.kyusui.app.core.UnknownException
import com.kyusui.app.core.ValidationException
import com.kyusui.app.domain.model.UserRole
import io.mockk.every
import io.mockk.mockk
import kotlinx.serialization.json.Json
import okhttp3.Interceptor
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.Protocol
import okhttp3.Request
import okhttp3.Response
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.assertEquals
import org.junit.Assert.assertSame
import org.junit.Assert.assertTrue
import org.junit.Assert.fail
import org.junit.Test

/**
 * Fixture di test ini diambil bentuk literal dari
 * `06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 4.4, 7.1, dan 8.
 */
class AuthApiContractTest {

    private val json = Json {
        ignoreUnknownKeys = true
        isLenient = true
        explicitNulls = false
        coerceInputValues = true
    }

    // --- 8.1 Register response ---

    @Test
    fun `register response envelope decodes user and token`() {
        val body = """
            {
              "data": {
                "user": {
                  "id": 1,
                  "name": "Budi",
                  "email": "budi@example.com",
                  "phone": "081234567890",
                  "role": { "name": "CUSTOMER", "display_name": "Pelanggan" },
                  "status": "ACTIVE"
                },
                "token": "sanctum-token"
              },
              "message": "Registration successful."
            }
        """.trimIndent()

        val envelope = json.decodeFromString<AuthEnvelopeDto<AuthSessionDto>>(body)

        assertEquals("Registration successful.", envelope.message)
        val session = requireNotNull(envelope.data)
        assertEquals("sanctum-token", session.token)
        assertEquals(1L, session.user.id)
        assertEquals("Budi", session.user.name)
        assertEquals("081234567890", session.user.phone)
        assertEquals("CUSTOMER", session.user.role.name)
        assertEquals("Pelanggan", session.user.role.display_name)
        assertEquals("ACTIVE", session.user.status)
    }

    // --- 8.2 Login request shape ---

    @Test
    fun `login request serializes the login field not email`() {
        val encoded = json.encodeToString(
            AuthLoginRequestDto.serializer(),
            AuthLoginRequestDto(login = "081234567890", password = "secret-password")
        )

        assertTrue(encoded.contains("\"login\""))
        assertTrue(!encoded.contains("email"))
        assertTrue(!encoded.contains("device_token"))
    }

    // --- 8.1 Register request shape ---

    @Test
    fun `register request serializes password confirmation and no role`() {
        val encoded = json.encodeToString(
            AuthRegisterRequestDto.serializer(),
            AuthRegisterRequestDto(
                name = "Budi",
                phone = "081234567890",
                email = "budi@example.com",
                password = "secret-password",
                passwordConfirmation = "secret-password"
            )
        )

        assertTrue(encoded.contains("\"password_confirmation\""))
        assertTrue(!encoded.contains("\"role\""))
        assertTrue(!encoded.contains("\"address\""))
    }

    // --- 8.4 Current user ---

    @Test
    fun `current user decodes the enveloped user resource`() {
        val body = """
            {
              "data": {
                "id": 1,
                "name": "Budi",
                "email": "budi@example.com",
                "phone": "081234567890",
                "role": { "name": "OWNER", "display_name": "Pemilik Toko" },
                "status": "ACTIVE"
              },
              "message": "Success."
            }
        """.trimIndent()

        val user = json.decodeFromString<AuthCurrentUserDto>(body).resolveUser()

        assertEquals(1L, requireNotNull(user).id)
        assertEquals("Pemilik Toko", user.role.display_name)
    }

    @Test
    fun `user resource with a string id is still accepted`() {
        val body = """
            { "data": { "id": "42", "name": "Budi",
              "role": { "name": "COURIER" } }, "message": "Success." }
        """.trimIndent()

        val user = json.decodeFromString<AuthCurrentUserDto>(body).resolveUser()

        assertEquals(42L, requireNotNull(user).id)
        assertEquals(UserRole.COURIER, UserRole.fromApiValue(requireNotNull(user).role.name))
    }

    @Test
    fun `current user without a resolvable payload returns null`() {
        val user = json.decodeFromString<AuthCurrentUserDto>("""{"message":"Success."}""").resolveUser()

        assertEquals(null, user)
    }

    // --- 4.4 Error envelope ---

    private val parser = ApiErrorParser(json)

    @Test
    fun `validation error envelope maps field messages`() {
        val body = """
            {
              "message": "Validation failed.",
              "errors": {
                "email": ["The email has already been taken."],
                "password": ["The password field must be at least 8 characters."]
              }
            }
        """.trimIndent()

        val exception = parser.parse(422, body, "Unprocessable Content")

        assertTrue(exception is ValidationException)
        val errors = (exception as ValidationException).errors
        assertEquals("Email sudah terdaftar.", errors["email"]?.first())
        assertEquals("Kata sandi minimal 8 karakter.", errors["password"]?.first())
    }

    @Test
    fun `duplicate identity conflict is reported as a field error`() {
        val body = """
            { "message": "Conflict.", "errors": { "phone": ["The phone has already been taken."] } }
        """.trimIndent()

        val exception = parser.parse(409, body, "Conflict")

        assertTrue(exception is ValidationException)
        assertEquals(
            "Nomor telepon sudah terdaftar.",
            (exception as ValidationException).errors["phone"]?.first()
        )
    }

    @Test
    fun `unknown field message is preserved so server detail is not lost`() {
        val body = """
            { "message": "Validation failed.", "errors": { "phone": ["Format 0812 tidak dikenali."] } }
        """.trimIndent()

        val errors = (parser.parse(422, body, "Unprocessable Content") as ValidationException).errors

        assertEquals("Format 0812 tidak dikenali.", errors["phone"]?.first())
    }

    @Test
    fun `nested error field names are reduced to the leaf name`() {
        val body = """
            { "message": "Validation failed.", "errors": { "data.user.phone": ["The phone has already been taken."] } }
        """.trimIndent()

        val errors = (parser.parse(422, body, "Unprocessable Content") as ValidationException).errors

        assertEquals(
            "Nomor telepon sudah terdaftar.",
            errors["phone"]?.first()
        )
    }

    // --- Status mapping ---

    @Test
    fun `401 maps to unauthorized with an Indonesian message`() {
        val exception = parser.parse(401, """{"message":"Unauthenticated."}""", "Unauthorized")

        assertTrue(exception is UnauthorizedException)
        assertEquals(ApiErrorParser.UNAUTHORIZED_MESSAGE, exception.message)
    }

    @Test
    fun `403 maps to forbidden`() {
        assertTrue(parser.parse(403, null, "Forbidden") is ForbiddenException)
    }

    @Test
    fun `404 maps to not found`() {
        assertTrue(parser.parse(404, null, "Not Found") is NotFoundException)
    }

    @Test
    fun `408 maps to timeout`() {
        assertTrue(parser.parse(408, null, "Request Timeout") is TimeoutException)
    }

    @Test
    fun `500 maps to server exception keeping the status code`() {
        val exception = parser.parse(500, null, "Server Error")

        assertTrue(exception is ServerException)
        assertEquals(500, (exception as ServerException).statusCode)
    }

    @Test
    fun `unexpected status maps to unknown exception`() {
        assertTrue(parser.parse(302, null, "Found") is UnknownException)
    }

    @Test
    fun `non json error body does not crash the parser`() {
        val exception = parser.parse(500, "<html>Bad Gateway</html>", "Bad Gateway")

        assertTrue(exception is ServerException)
    }

    // --- Interceptor integration ---

    @Test
    fun `2xx response is returned untouched`() {
        val response = createResponse(200)

        val result = ResponseValidationInterceptor(parser).intercept(chainReturning(response))

        assertSame(response, result)
    }

    @Test
    fun `401 response throws unauthorized`() {
        assertThrows<UnauthorizedException>(401)
    }

    @Test
    fun `422 response throws validation exception with field errors`() {
        val body = """{"message":"Validation failed.","errors":{"email":["The email has already been taken."]}}"""

        val exception = assertThrows<ValidationException>(422, body)

        assertEquals(
            "Email sudah terdaftar.",
            (exception as ValidationException).errors["email"]?.first()
        )
    }

    private inline fun <reified T : Throwable> assertThrows(code: Int, body: String? = null): T {
        try {
            ResponseValidationInterceptor(parser).intercept(createChain(code, body))
        } catch (throwable: Throwable) {
            if (throwable is T) return throwable
            fail("Expected ${T::class.simpleName} but got ${throwable::class.simpleName}")
        }
        fail("Expected ${T::class.simpleName} but no exception was thrown")
        error("unreachable")
    }

    private fun createResponse(code: Int, body: String = "{}"): Response = Response.Builder()
        .request(Request.Builder().url("https://api.test.com").build())
        .protocol(Protocol.HTTP_1_1)
        .code(code)
        .message("HTTP $code")
        .body(body.toResponseBody("application/json".toMediaType()))
        .build()

    private fun chainReturning(response: Response): Interceptor.Chain {
        val chain = mockk<Interceptor.Chain>(relaxed = true)
        every { chain.proceed(any()) } returns response
        return chain
    }

    private fun createChain(code: Int, body: String? = null): Interceptor.Chain =
        chainReturning(createResponse(code, body ?: "{}"))
}
