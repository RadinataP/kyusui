package com.kyusui.app.data.repository

import com.kyusui.app.core.NoNetworkException
import com.kyusui.app.core.Result
import com.kyusui.app.core.UnauthorizedException
import com.kyusui.app.core.ValidationException
import com.kyusui.app.data.api.AuthApi
import com.kyusui.app.data.api.AuthCurrentUserDto
import com.kyusui.app.data.api.AuthEnvelopeDto
import com.kyusui.app.data.api.AuthLoginRequestDto
import com.kyusui.app.data.api.AuthRegisterRequestDto
import com.kyusui.app.data.api.AuthRoleDto
import com.kyusui.app.data.api.AuthSessionDto
import com.kyusui.app.data.api.AuthUserDto
import com.kyusui.app.data.datastore.PreferencesDataSource
import com.kyusui.app.data.datastore.StoredSession
import com.kyusui.app.domain.model.UserRole
import com.kyusui.app.domain.repository.LoginRequest
import com.kyusui.app.domain.repository.RegisterRequest
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.mockk
import io.mockk.slot
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.runBlocking
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import retrofit2.Response

/**
 * Kontrak diuji mengikuti `06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 8.
 */
class AuthRepositoryImplTest {

    private lateinit var authApi: AuthApi
    private lateinit var preferencesRepository: PreferencesDataSource
    private lateinit var repository: AuthRepositoryImpl

    @Before
    fun setup() {
        authApi = mockk()
        preferencesRepository = mockk(relaxed = true)
        coEvery { preferencesRepository.saveAuthData(any(), any(), any(), any(), any(), any()) } returns
            Result.success(Unit)
        coEvery { preferencesRepository.clearAuthData() } returns Result.success(Unit)
        coEvery { preferencesRepository.storedSession } returns flowOf(null)
        repository = AuthRepositoryImpl(authApi, preferencesRepository)
    }

    // --- 1. Successful login ---

    @Test
    fun `successful login returns token and persists session`() = runBlocking {
        coEvery { authApi.login(any()) } returns envelope(
            token = "sanctum-token",
            roleName = "CUSTOMER"
        )

        val result = repository.login(LoginRequest(login = "081234567890", password = "secret"))

        assertTrue(result.isSuccess)
        val authResult = result.getOrThrow()
        assertEquals("sanctum-token", authResult.token)
        assertEquals("1", authResult.user.id)
        assertEquals("Budi", authResult.user.name)
        assertEquals(UserRole.CUSTOMER, authResult.user.role)
        assertEquals("Pelanggan", authResult.user.roleDisplayName)
        assertTrue(authResult.user.isActive())

        coVerify(exactly = 1) {
            preferencesRepository.saveAuthData(
                accessToken = "sanctum-token",
                userId = "1",
                role = "CUSTOMER",
                email = "budi@example.com",
                name = "Budi",
                status = "ACTIVE"
            )
        }
    }

    @Test
    fun `login sends the login field as required by the specification`() = runBlocking {
        val captured = slot<AuthLoginRequestDto>()
        coEvery { authApi.login(capture(captured)) } returns envelope()

        repository.login(LoginRequest(login = "  081234567890  ", password = "secret"))

        assertEquals("081234567890", captured.captured.login)
        assertEquals("secret", captured.captured.password)
    }

    // --- 2. Invalid credential ---

    @Test
    fun `invalid credential returns unauthorized failure`() = runBlocking {
        coEvery { authApi.login(any()) } throws UnauthorizedException(
            "Sesi tidak valid atau telah berakhir. Silakan masuk kembali."
        )

        val result = repository.login(LoginRequest(login = "081234567890", password = "salah"))

        assertTrue(result.isFailure)
        assertTrue(result.exceptionOrNull is UnauthorizedException)
        coVerify(exactly = 0) {
            preferencesRepository.saveAuthData(any(), any(), any(), any(), any(), any())
        }
    }

    // --- 3. Validation error ---

    @Test
    fun `validation error keeps per field messages`() = runBlocking {
        coEvery { authApi.register(any()) } throws ValidationException(
            errors = mapOf(
                "email" to listOf("The email has already been taken."),
                "phone" to listOf("The phone has already been taken.")
            ),
            message = "Validation failed."
        )

        val result = repository.register(
            RegisterRequest(
                name = "Budi",
                phone = "081234567890",
                password = "secret-password",
                passwordConfirmation = "secret-password",
                email = "budi@example.com"
            )
        )

        assertTrue(result.isFailure)
        val validation = result.exceptionOrNull as ValidationException
        assertEquals("The email has already been taken.", validation.errors["email"]?.first())
        assertEquals("The phone has already been taken.", validation.errors["phone"]?.first())
    }

    @Test
    fun `register does not send role because role comes from backend`() = runBlocking {
        val captured = slot<AuthRegisterRequestDto>()
        coEvery { authApi.register(capture(captured)) } returns envelope()

        repository.register(
            RegisterRequest(
                name = " Budi ",
                phone = " 081234567890 ",
                password = "secret-password",
                passwordConfirmation = "secret-password",
                email = " budi@example.com "
            )
        )

        assertEquals("Budi", captured.captured.name)
        assertEquals("081234567890", captured.captured.phone)
        assertEquals("budi@example.com", captured.captured.email)
        assertEquals("secret-password", captured.captured.passwordConfirmation)
    }

    // --- 4. Session restoration ---

    @Test
    fun `session restoration returns session built from stored token and current user`() = runBlocking {
        coEvery { preferencesRepository.storedSession } returns flowOf(storedSession())
        coEvery { authApi.currentUser() } returns currentUser(roleName = "OWNER")

        val result = repository.restoreSession()

        assertTrue(result.isSuccess)
        val session = requireNotNull(result.getOrThrow())
        assertEquals("stored-token", session.token)
        assertEquals(UserRole.OWNER, session.user.role)
        coVerify(exactly = 1) { authApi.currentUser() }
    }

    @Test
    fun `session restoration returns null when nothing is stored`() = runBlocking {
        coEvery { preferencesRepository.storedSession } returns flowOf(null)

        val result = repository.restoreSession()

        assertTrue(result.isSuccess)
        assertNull(result.getOrThrow())
        coVerify(exactly = 0) { authApi.currentUser() }
    }

    @Test
    fun `session restoration propagates network failure so user can retry`() = runBlocking {
        coEvery { preferencesRepository.storedSession } returns flowOf(storedSession())
        coEvery { authApi.currentUser() } throws NoNetworkException()

        val result = repository.restoreSession()

        assertTrue(result.isFailure)
        assertTrue(result.exceptionOrNull is NoNetworkException)
        coVerify(exactly = 0) { preferencesRepository.clearAuthData() }
    }

    // --- 5. Unauthorized response ---

    @Test
    fun `unauthorized response during restoration clears the local session`() = runBlocking {
        coEvery { preferencesRepository.storedSession } returns flowOf(storedSession())
        coEvery { authApi.currentUser() } throws UnauthorizedException()

        val result = repository.restoreSession()

        assertTrue(result.isSuccess)
        assertNull(result.getOrThrow())
        coVerify(exactly = 1) { preferencesRepository.clearAuthData() }
    }

    // --- 6. Logout ---

    @Test
    fun `logout clears the local session`() = runBlocking {
        coEvery { authApi.logout() } returns Response.success(Unit)

        val result = repository.logout()

        assertTrue(result.isSuccess)
        coVerify(exactly = 1) { authApi.logout() }
        coVerify(exactly = 1) { preferencesRepository.clearAuthData() }
    }

    @Test
    fun `logout clears local session even when the server rejects the request`() = runBlocking {
        coEvery { authApi.logout() } returns Response.error(401, okhttp3.ResponseBody.create(null, ""))

        repository.logout()

        coVerify(exactly = 1) { preferencesRepository.clearAuthData() }
    }

    @Test
    fun `logout clears local session even when the server is unreachable`() = runBlocking {
        coEvery { authApi.logout() } throws NoNetworkException()

        repository.logout()

        coVerify(exactly = 1) { preferencesRepository.clearAuthData() }
    }

    // --- 7. Role routing ---

    @Test
    fun `role from backend is preserved for each canonical role`() = runBlocking {
        val expected = mapOf(
            "CUSTOMER" to UserRole.CUSTOMER,
            "OWNER" to UserRole.OWNER,
            "COURIER" to UserRole.COURIER
        )

        expected.forEach { (roleName, expectedRole) ->
            coEvery { authApi.login(any()) } returns envelope(roleName = roleName)

            val result = repository.login(LoginRequest(login = "0812", password = "secret"))

            assertTrue(result.isSuccess)
            assertEquals(expectedRole, result.getOrThrow().user.role)
        }
    }

    @Test
    fun `unknown role is rejected instead of silently treated as customer`() = runBlocking {
        coEvery { authApi.login(any()) } returns envelope(roleName = "ADMIN")

        val result = repository.login(LoginRequest(login = "0812", password = "secret"))

        assertTrue(result.isFailure)
        coVerify(exactly = 0) {
            preferencesRepository.saveAuthData(any(), any(), any(), any(), any(), any())
        }
    }

    @Test
    fun `current user endpoint is mapped to domain user`() = runBlocking {
        coEvery { authApi.currentUser() } returns currentUser(roleName = "COURIER")

        val result = repository.currentUser()

        assertTrue(result.isSuccess)
        val user = result.getOrThrow()
        assertEquals("Kurir", user.roleLabel())
        assertEquals(UserRole.COURIER, user.role)
    }

    @Test
    fun `current user also accepts an unwrapped user resource`() = runBlocking {
        coEvery { authApi.currentUser() } returns AuthCurrentUserDto(
            data = null,
            message = null,
            id = 7L,
            name = "Andi",
            email = "andi@example.com",
            phone = "081200000000",
            role = AuthRoleDto(name = "COURIER", display_name = "Kurir"),
            status = "ACTIVE"
        )

        val result = repository.currentUser()

        assertTrue(result.isSuccess)
        val session = result.getOrThrow()
        assertNotNull(session)
        assertEquals("7", requireNotNull(session).id)
        assertEquals(UserRole.COURIER, requireNotNull(session).role)
    }

    // --- Helpers ---

    private fun envelope(
        token: String = "sanctum-token",
        roleName: String = "CUSTOMER"
    ) = AuthEnvelopeDto(
        data = AuthSessionDto(
            user = authUser(roleName = roleName),
            token = token
        ),
        message = "Login successful."
    )

    private fun currentUser(roleName: String) = AuthCurrentUserDto(
        data = authUser(roleName = roleName),
        message = "Success."
    )

    private fun authUser(roleName: String) = AuthUserDto(
        id = 1L,
        name = "Budi",
        email = "budi@example.com",
        phone = "081234567890",
        role = AuthRoleDto(name = roleName, display_name = displayNameOf(roleName)),
        status = "ACTIVE"
    )

    private fun displayNameOf(roleName: String) = when (roleName) {
        "OWNER" -> "Pemilik Toko"
        "COURIER" -> "Kurir"
        else -> "Pelanggan"
    }

    private fun storedSession() = StoredSession(
        token = "stored-token",
        userId = "1",
        role = "CUSTOMER",
        name = "Budi",
        email = "budi@example.com",
        status = "ACTIVE"
    )
}
