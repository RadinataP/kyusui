package com.kyusui.app.ui.auth

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.kyusui.app.ui.components.InlineError
import com.kyusui.app.ui.components.LoadingState
import com.kyusui.app.ui.components.PrimaryButton
import com.kyusui.app.ui.components.SecondaryButton

/**
 * Layar pertama setelah launch.
 *
 * Bertanggung jawab memulihkan session lalu mengarahkan ke home sesuai role
 * yang berasal dari backend. Role hanya memilih layar; authorization tetap
 * dikerjakan backend.
 */
@Composable
fun SplashScreen(
    viewModel: AuthViewModel,
    onEntryPointResolved: (AuthEntryPoint) -> Unit
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    val entryPoint by viewModel.entryPoint.collectAsStateWithLifecycle()

    LaunchedEffect(entryPoint) {
        entryPoint?.let(onEntryPointResolved)
    }

    when (val state = uiState) {
        is AuthUiState.RestoringSession -> SplashLoading()
        is AuthUiState.RestoreFailed -> SplashRestoreFailed(
            message = state.message,
            onRetry = viewModel::restoreSession,
            onContinueToLogin = {
                viewModel.clearError()
                onEntryPointResolved(AuthEntryPoint.LOGIN)
            }
        )
        else -> SplashLoading()
    }
}

@Composable
private fun SplashLoading() {
    Column(
        modifier = Modifier.fillMaxSize(),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Text(
            text = "KYŪSUI",
            style = MaterialTheme.typography.displaySmall,
            color = MaterialTheme.colorScheme.primary
        )
        Text(
            text = "Memulihkan sesi Anda",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
            modifier = Modifier.padding(top = 8.dp, bottom = 32.dp)
        )
        LoadingState(message = "Mohon tunggu sebentar")
    }
}

@Composable
private fun SplashRestoreFailed(
    message: String,
    onRetry: () -> Unit,
    onContinueToLogin: () -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        verticalArrangement = Arrangement.Center
    ) {
        InlineError(
            message = message,
            onRetry = onRetry,
            modifier = Modifier.padding(bottom = 16.dp)
        )
        PrimaryButton(
            text = "Coba Lagi",
            onClick = onRetry,
            fillWidth = true
        )
        SecondaryButton(
            text = "Lanjut ke Login",
            onClick = onContinueToLogin,
            fillWidth = true,
            modifier = Modifier.padding(top = 8.dp)
        )
    }
}
