package com.kyusui.app.ui.auth

import com.kyusui.app.core.Result
import com.kyusui.app.domain.model.AuthResult
import com.kyusui.app.domain.model.AuthSession
import com.kyusui.app.domain.model.User
import com.kyusui.app.domain.model.UserRole
import com.kyusui.app.domain.repository.AuthRepository
import com.kyusui.app.domain.repository.LoginRequest
import com.kyusui.app.domain.repository.RegisterRequest
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.mockk
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class AuthViewModelTest {

    private val dispatcher = StandardTestDispatcher()
    private lateinit var repository: AuthRepository
    private lateinit var viewModel: AuthViewModel

    @Before
    fun setup() {
        Dispatchers.setMain(dispatcher)
        repository = mockk(relaxed = true)
        coEvery { repository.session } returns flowOf(null)
        coEvery { repository.restoreSession() } returns Result.success(null)
        viewModel = AuthViewModel(repository)
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    // --- 7. Role routing ---

    @Test
    fun `each canonical role maps to its own entry point`() {
        assertEquals(AuthEntryPoint.CUSTOMER_HOME, AuthEntryPoint.fromRole(UserRole.CUSTOMER))
        assertEquals(AuthEntryPoint.OWNER_HOME, AuthEntryPoint.fromRole(UserRole.OWNER))
        assertEquals(AuthEntryPoint.COURIER_HOME, AuthEntryPoint.fromRole(UserRole.COURIER))
    }

    @Test
    fun `login as owner resolves to owner entry point`() = runTest(dispatcher) {
        coEvery { repository.restoreSession() } returns Result.success(null)
        coEvery { repository.login(any()) } returns Result.success(authResult(UserRole.OWNER))

        val viewModel = AuthViewModel(repository)
        advanceUntilIdle()
        viewModel.login("081234567890", "secret")
        advanceUntilIdle()

        assertEquals(AuthEntryPoint.OWNER_HOME, viewModel.entryPoint.value)
        assertEquals(UserRole.OWNER, (viewModel.uiState.value as AuthUiState.Authenticated).user.role)
    }

    @Test
    fun `login as courier resolves to courier entry point`() = runTest(dispatcher) {
        coEvery { repository.login(any()) } returns Result.success(authResult(UserRole.COURIER))

        viewModel.login("081234567890", "secret")
        advanceUntilIdle()

        assertEquals(AuthEntryPoint.COURIER_HOME, viewModel.entryPoint.value)
    }

    // --- Session restoration ---

    @Test
    fun `restored session resolves entry point without asking for login again`() = runTest(dispatcher) {
        coEvery { repository.restoreSession() } returns Result.success(
            AuthSession(user = user(UserRole.CUSTOMER), token = "stored-token")
        )

        val viewModel = AuthViewModel(repository)
        advanceUntilIdle()

        assertEquals(AuthEntryPoint.CUSTOMER_HOME, viewModel.entryPoint.value)
        assertTrue(viewModel.uiState.value is AuthUiState.Authenticated)
    }

    @Test
    fun `empty session leaves the app on login entry point`() = runTest(dispatcher) {
        val viewModel = AuthViewModel(repository)
        advanceUntilIdle()

        assertEquals(AuthEntryPoint.LOGIN, viewModel.entryPoint.value)
        assertTrue(viewModel.uiState.value is AuthUiState.Unauthenticated)
    }

    @Test
    fun `failed restoration exposes a recoverable state`() = runTest(dispatcher) {
        coEvery { repository.restoreSession() } returns
            Result.failure(com.kyusui.app.core.NoNetworkException())

        val viewModel = AuthViewModel(repository)
        advanceUntilIdle()

        assertTrue(viewModel.uiState.value is AuthUiState.RestoreFailed)
        assertNull(viewModel.entryPoint.value)
    }

    // --- Loading state ---

    @Test
    fun `login shows submitting state while the request is in flight`() = runTest(dispatcher) {
        coEvery { repository.login(any()) } returns Result.success(authResult(UserRole.CUSTOMER))

        viewModel.login("081234567890", "secret")

        assertTrue((viewModel.uiState.value as AuthUiState.Unauthenticated).isSubmitting)
        advanceUntilIdle()
        assertTrue(viewModel.uiState.value is AuthUiState.Authenticated)
    }

    // --- Logout ---

    @Test
    fun `logout returns to login entry point`() = runTest(dispatcher) {
        coEvery { repository.restoreSession() } returns Result.success(
            AuthSession(user = user(UserRole.CUSTOMER), token = "stored-token")
        )
        coEvery { repository.logout() } returns Result.success(Unit)

        val viewModel = AuthViewModel(repository)
        advanceUntilIdle()
        viewModel.logout()
        advanceUntilIdle()

        assertEquals(AuthEntryPoint.LOGIN, viewModel.entryPoint.value)
        assertTrue(viewModel.uiState.value is AuthUiState.Unauthenticated)
        coVerify(exactly = 1) { repository.logout() }
    }

    // --- Validation ---

    @Test
    fun `blank credentials are rejected without hitting the network`() = runTest(dispatcher) {
        viewModel.login("", "")

        val state = viewModel.uiState.value as AuthUiState.Unauthenticated
        assertTrue(state.errorMessage!!.isNotBlank())
        coVerify(exactly = 0) { repository.login(any()) }
    }

    @Test
    fun `short password is rejected before the request`() = runTest(dispatcher) {
        viewModel.register(
            RegisterRequest(
                name = "Budi",
                phone = "081234567890",
                password = "short",
                passwordConfirmation = "short"
            )
        )

        val state = viewModel.uiState.value as AuthUiState.Unauthenticated
        assertEquals(
            "Kata sandi minimal 8 karakter.",
            state.fieldErrors[AuthViewModel.FIELD_PASSWORD]
        )
        coVerify(exactly = 0) { repository.register(any()) }
    }

    @Test
    fun `mismatched confirmation is rejected before the request`() = runTest(dispatcher) {
        viewModel.register(
            RegisterRequest(
                name = "Budi",
                phone = "081234567890",
                password = "secret-password",
                passwordConfirmation = "different-password"
            )
        )

        val state = viewModel.uiState.value as AuthUiState.Unauthenticated
        assertEquals(
            "Konfirmasi kata sandi tidak cocok.",
            state.fieldErrors[AuthViewModel.FIELD_PASSWORD_CONFIRMATION]
        )
        coVerify(exactly = 0) { repository.register(any()) }
    }

    @Test
    fun `server validation errors are mapped to the matching form field`() = runTest(dispatcher) {
        coEvery { repository.login(any()) } returns Result.failure(
            com.kyusui.app.core.ValidationException(
                errors = mapOf("login" to listOf("The login field is required."))
            )
        )

        viewModel.login("0812", "secret")
        advanceUntilIdle()

        val state = viewModel.uiState.value as AuthUiState.Unauthenticated
        assertEquals(
            "The login field is required.",
            state.fieldErrors[AuthViewModel.FIELD_LOGIN]
        )
    }

    @Test
    fun `clearError removes the message and the field errors`() = runTest(dispatcher) {
        viewModel.login("", "")
        viewModel.clearError()

        val state = viewModel.uiState.value as AuthUiState.Unauthenticated
        assertNull(state.errorMessage)
        assertTrue(state.fieldErrors.isEmpty())
    }

    private fun authResult(role: UserRole) = AuthResult(user = user(role), token = "sanctum-token")

    private fun user(role: UserRole) = User(
        id = "1",
        name = "Budi",
        email = "budi@example.com",
        role = role,
        status = "ACTIVE"
    )
}
