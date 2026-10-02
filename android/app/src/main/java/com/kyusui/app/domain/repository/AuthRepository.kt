package com.kyusui.app.domain.repository

import com.kyusui.app.core.Result
import com.kyusui.app.domain.model.AuthResult
import com.kyusui.app.domain.model.AuthSession
import com.kyusui.app.domain.model.User
import kotlinx.coroutines.flow.Flow

/**
 * Register Customer sesuai API specification section 8.1.
 *
 * `role` tidak ada di sini. Registrasi adalah customer anonim dan role
 * ditetapkan backend, sehingga Android tidak pernah mengirimnya.
 */
data class RegisterRequest(
    val name: String,
    val phone: String,
    val password: String,
    val passwordConfirmation: String,
    val email: String? = null
)

/**
 * Login sesuai API specification section 8.2.
 *
 * Backend menerima satu field `login`, yang berisi nomor telepon.
 */
data class LoginRequest(
    val login: String,
    val password: String
)

interface AuthRepository {

    val session: Flow<AuthSession?>

    suspend fun register(request: RegisterRequest): Result<AuthResult>

    suspend fun login(request: LoginRequest): Result<AuthResult>

    /**
     * Selalu membersihkan session lokal, baik server menerima logout
     * maupun tidak. Token lokal tidak boleh tetap hidup setelah logout.
     */
    suspend fun logout(): Result<Unit>

    /**
     * Session restoration. Memakai token tersimpan untuk memvalidasi ulang
     * lewat `GET /auth/me`. Bila backend menjawab 401, session lokal dihapus.
     */
    suspend fun restoreSession(): Result<AuthSession?>

    suspend fun currentUser(): Result<User>

    suspend fun clearLocalSession(): Result<Unit>
}
