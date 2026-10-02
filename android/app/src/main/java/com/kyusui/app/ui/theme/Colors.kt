package com.kyusui.app.ui.theme

import androidx.compose.ui.graphics.Color

object KyusuiColors {
    // Role-based colors
    val CustomerPrimary = Color(0xFF006874)
    val OwnerPrimary = Color(0xFF6B5B00)
    val CourierPrimary = Color(0xFF385E2D)

    // Status colors
    val StatusPending = Color(0xFFF9A825)
    val StatusProcessing = Color(0xFF1976D2)
    val StatusCompleted = Color(0xFF388E3C)
    val StatusCancelled = Color(0xFFD32F2F)
    val StatusWaitingVerification = Color(0xFFFF8F00)

    // Payment method colors
    val PaymentQris = Color(0xFF006874)
    val PaymentCash = Color(0xFF6B5B00)

    // Semantic colors
    val Success = Color(0xFF388E3C)
    val Warning = Color(0xFFF9A825)
    val Error = Color(0xFFD32F2F)
    val Info = Color(0xFF1976D2)

    // Surface variants
    val SurfaceContainerHigh = Color(0xFFE8ECEB)
    val SurfaceContainerHighest = Color(0xFFDADDDD)
}