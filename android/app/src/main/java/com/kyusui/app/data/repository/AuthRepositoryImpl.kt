package com.kyusui.app.data.repository

import com.kyusui.app.core.Result
import com.kyusui.app.core.UnauthorizedException
import com.kyusui.app.core.safeApiCall
import com.kyusui.app.data.api.AuthApi
import com.kyusui.app.data.api.AuthEnvelopeDto
import com.kyusui.app.data.api.AuthLoginRequestDto
import com.kyusui.app.data.api.AuthRegisterRequestDto
import com.kyusui.app.data.datastore.PreferencesDataSource
import com.kyusui.app.data.mapper.toDomain
import com.kyusui.app.data.mapper.toSession
import com.kyusui.app.domain.model.AuthResult
import com.kyusui.app.domain.model.AuthSession
import com.kyusui.app.domain.model.User
import com.kyusui.app.domain.model.UserRole
import com.kyusui.app.domain.repository.AuthRepository
import com.kyusui.app.domain.repository.LoginRequest
import com.kyusui.app.domain.repository.RegisterRequest
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class AuthRepositoryImpl @Inject constructor(
    private val authApi: AuthApi,
    private val preferencesRepository: PreferencesDataSource
) : AuthRepository {

    override val session: Flow<AuthSession?> = preferencesRepository.storedSession.map { stored ->
        stored?.let {
            AuthSession(
                user = User(
                    id = it.userId,
                    name = it.name,
                    email = it.email,
                    role = UserRole.fromApiValue(it.role) ?: UserRole.CUSTOMER,
                    status = it.status
                ),
                token = it.token
            )
        }
    }

    override suspend fun register(request: RegisterRequest): Result<AuthResult> = safeApiCall {
        val envelope = authApi.register(
            AuthRegisterRequestDto(
                name = request.name.trim(),
                phone = request.phone.trim(),
                email = request.email?.trim()?.takeIf { it.isNotEmpty() },
                password = request.password,
                passwordConfirmation = request.passwordConfirmation
            )
        )
        val session = envelope.requireData().toSession()
        persistSession(session)
        AuthResult(user = session.user, token = session.token)
    }

    override suspend fun login(request: LoginRequest): Result<AuthResult> = safeApiCall {
        val envelope = authApi.login(
            AuthLoginRequestDto(
                login = request.login.trim(),
                password = request.password
            )
        )
        val session = envelope.requireData().toSession()
        persistSession(session)
        AuthResult(user = session.user, token = session.token)
    }

    override suspend fun logout(): Result<Unit> {
        val serverResult = safeApiCall {
            val response = authApi.logout()
            if (!response.isSuccessful) {
                throw UnauthorizedException(UNAUTHORIZED_MESSAGE)
            }
        }
        clearLocalSession()
        return serverResult
    }

    override suspend fun restoreSession(): Result<AuthSession?> {
        val stored = preferencesRepository.storedSession.first()
            ?: return Result.success(null)

        return when (val result = safeApiCall { authApi.currentUser().resolveUser()?.toDomain() }) {
            is Result.Failure -> {
                if (result.exception is UnauthorizedException) {
                    clearLocalSession()
                    Result.success(null)
                } else {
                    result
                }
            }
            is Result.Success -> {
                val user = result.data
                if (user == null) {
                    clearLocalSession()
                    Result.success(null)
                } else {
                    val session = AuthSession(user = user, token = stored.token)
                    persistSession(session)
                    Result.success(session)
                }
            }
        }
    }

    override suspend fun currentUser(): Result<User> = safeApiCall {
        authApi.currentUser().resolveUser()?.toDomain()
            ?: throw MissingSessionDataException()
    }

    override suspend fun clearLocalSession(): Result<Unit> = preferencesRepository.clearAuthData()

    private suspend fun persistSession(session: AuthSession) {
        preferencesRepository.saveAuthData(
            accessToken = session.token,
            userId = session.user.id,
            role = session.user.role.apiValue,
            email = session.user.email,
            name = session.user.name,
            status = session.user.status
        )
    }

    private fun <T> AuthEnvelopeDto<T>.requireData(): T =
        data ?: throw MissingSessionDataException()

    private class MissingSessionDataException :
        IllegalStateException("Respons backend tidak memuat data sesi.")

    private companion object {
        const val UNAUTHORIZED_MESSAGE =
            "Sesi tidak valid atau telah berakhir. Silakan masuk kembali."
    }
}
