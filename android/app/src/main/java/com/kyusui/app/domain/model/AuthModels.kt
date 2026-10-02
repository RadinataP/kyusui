package com.kyusui.app.domain.model

import kotlinx.datetime.LocalDate
import kotlinx.datetime.LocalDateTime

data class User(
    val id: String,
    val name: String,
    val email: String? = null,
    val role: UserRole,
    val phone: String? = null,
    val address: String? = null,
    val avatar: String? = null,
    val emailVerifiedAt: LocalDateTime? = null,
    val createdAt: LocalDateTime? = null,
    val updatedAt: LocalDateTime? = null,
    val roleDisplayName: String? = null,
    val status: String? = null
) {
    fun isActive(): Boolean = status.equals(ACTIVE_STATUS, ignoreCase = true)

    fun roleLabel(): String = roleDisplayName?.takeIf { it.isNotBlank() } ?: role.defaultDisplayName

    private companion object {
        const val ACTIVE_STATUS = "ACTIVE"
    }
}

enum class UserRole(val apiValue: String, val defaultDisplayName: String) {
    CUSTOMER("CUSTOMER", "Pelanggan"),
    OWNER("OWNER", "Pemilik Toko"),
    COURIER("COURIER", "Kurir");

    companion object {
        fun fromApiValue(value: String?): UserRole? =
            entries.firstOrNull { it.apiValue.equals(value?.trim(), ignoreCase = true) }
    }
}

data class AuthResult(
    val user: User,
    val token: String
)

data class AuthSession(
    val user: User,
    val token: String
)

data class CustomerProfile(
    val id: String,
    val userId: String,
    val name: String,
    val phone: String? = null,
    val email: String? = null,
    val defaultAddress: String? = null
)
