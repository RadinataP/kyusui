package com.kyusui.app.data.mapper

import com.kyusui.app.core.UnknownException
import com.kyusui.app.data.api.AuthSessionDto
import com.kyusui.app.data.api.AuthUserDto
import com.kyusui.app.domain.model.AuthResult
import com.kyusui.app.domain.model.AuthSession
import com.kyusui.app.domain.model.User
import com.kyusui.app.domain.model.UserRole

/**
 * Role berasal dari backend dan hanya dipakai untuk navigasi serta presentation.
 * Role yang tidak dikenal ditolak secara eksplisit, bukan dipetakan diam-diam
 * ke CUSTOMER, supaya user tidak pernah diarahkan ke home yang salah.
 */
fun AuthUserDto.toDomain(): User {
    val resolvedRole = UserRole.fromApiValue(role.name)
        ?: throw UnknownException(UNSUPPORTED_ROLE_MESSAGE)
    return User(
        id = id.toString(),
        name = name,
        email = email,
        phone = phone,
        role = resolvedRole,
        roleDisplayName = role.display_name,
        status = status
    )
}

fun AuthSessionDto.toDomain(): AuthResult = AuthResult(
    user = user.toDomain(),
    token = token
)

fun AuthSessionDto.toSession(): AuthSession = AuthSession(
    user = user.toDomain(),
    token = token
)

const val UNSUPPORTED_ROLE_MESSAGE = "Peran akun tidak dikenali. Silakan hubungi admin depo."
