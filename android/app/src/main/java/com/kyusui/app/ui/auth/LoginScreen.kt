package com.kyusui.app.ui.auth

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.PhoneAndroid
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.kyusui.app.ui.components.InlineError
import com.kyusui.app.ui.components.InputField
import com.kyusui.app.ui.components.PasswordField
import com.kyusui.app.ui.components.PrimaryButton
import com.kyusui.app.ui.components.TextButton

@Composable
fun LoginScreen(
    viewModel: AuthViewModel,
    onRegisterClick: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    var login by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }

    val state = uiState as? AuthUiState.Unauthenticated
    val isSubmitting = state?.isSubmitting == true
    val loginError = state?.fieldErrors?.get(AuthViewModel.FIELD_LOGIN)
        ?: state?.fieldErrors?.get(AuthViewModel.FIELD_PHONE)
    val passwordError = state?.fieldErrors?.get(AuthViewModel.FIELD_PASSWORD)

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .imePadding()
            .padding(horizontal = 24.dp, vertical = 32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Text(
            text = "KYŪSUI",
            style = MaterialTheme.typography.displaySmall,
            color = MaterialTheme.colorScheme.primary
        )
        Text(
            text = "Masuk untuk melanjutkan",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
            modifier = Modifier.padding(top = 8.dp, bottom = 32.dp)
        )

        state?.errorMessage?.let { message ->
            InlineError(
                message = message,
                modifier = Modifier.padding(bottom = 16.dp)
            )
        }

        InputField(
            label = "Nomor Telepon",
            value = login,
            onValueChange = { input ->
                login = input
                viewModel.clearError()
            },
            placeholder = "081234567890",
            keyboardType = KeyboardType.Phone,
            leadingIcon = Icons.Outlined.PhoneAndroid,
            isError = loginError != null,
            errorMessage = loginError,
            isEnabled = !isSubmitting
        )

        PasswordField(
            label = "Kata Sandi",
            value = password,
            onValueChange = { input ->
                password = input
                viewModel.clearError()
            },
            isError = passwordError != null,
            errorMessage = passwordError,
            isEnabled = !isSubmitting
        )

        PrimaryButton(
            text = "Masuk",
            onClick = { viewModel.login(login, password) },
            isLoading = isSubmitting,
            enabled = !isSubmitting,
            modifier = Modifier.padding(top = 24.dp)
        )

        RowRegisterFooter(
            prompt = "Belum punya akun?",
            action = "Daftar",
            onClick = onRegisterClick,
            enabled = !isSubmitting
        )
    }
}

@Composable
private fun RowRegisterFooter(
    prompt: String,
    action: String,
    onClick: () -> Unit,
    enabled: Boolean
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(top = 16.dp),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = prompt,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
        TextButton(
            text = action,
            onClick = onClick,
            enabled = enabled
        )
    }
}
