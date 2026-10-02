package com.kyusui.app.ui.components

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.Snackbar
import androidx.compose.material3.SnackbarDuration
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.SnackbarResult
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.Modifier
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.launch

class SnackbarManager(
    private val hostState: SnackbarHostState,
    private val scope: CoroutineScope
) {

    fun show(
        message: String,
        actionLabel: String? = null,
        duration: SnackbarDuration = SnackbarDuration.Short,
        onAction: (() -> Unit)? = null
    ) {
        scope.launch {
            val result = hostState.showSnackbar(
                message = message,
                actionLabel = actionLabel,
                withDismissAction = actionLabel == null,
                duration = duration
            )
            if (result == SnackbarResult.ActionPerformed) {
                onAction?.invoke()
            }
        }
    }

    fun showError(message: String, onRetry: (() -> Unit)? = null) {
        show(
            message = message,
            actionLabel = if (onRetry != null) "Coba Lagi" else null,
            duration = SnackbarDuration.Long,
            onAction = onRetry
        )
    }

    fun showSuccess(message: String) {
        show(message = message, duration = SnackbarDuration.Short)
    }

    fun showInfo(message: String) {
        show(message = message, duration = SnackbarDuration.Short)
    }
}

val LocalSnackbarManager = staticCompositionLocalOf<SnackbarManager> {
    error("SnackbarManager tidak tersedia. Bungkus content dengan ProvideSnackbarManager.")
}

@Composable
fun ProvideSnackbarManager(content: @Composable () -> Unit) {
    val hostState = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()
    val manager = remember(hostState, scope) { SnackbarManager(hostState, scope) }

    CompositionLocalProvider(LocalSnackbarManager provides manager) {
        Box(modifier = Modifier.fillMaxSize()) {
            content()
            SnackbarHost(
                hostState = hostState,
                modifier = Modifier
            ) { data ->
                Snackbar(snackbarData = data)
            }
        }
    }
}

@Composable
fun rememberSnackbarManager(): SnackbarManager = LocalSnackbarManager.current
