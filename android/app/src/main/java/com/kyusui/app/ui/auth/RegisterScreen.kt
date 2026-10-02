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
import androidx.compose.material.icons.outlined.AlternateEmail
import androidx.compose.material.icons.outlined.Badge
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
import com.kyusui.app.domain.repository.RegisterRequest
import com.kyusui.app.ui.components.InlineError
import com.kyusui.app.ui.components.InputField
import com.kyusui.app.ui.components.PasswordField
import com.kyusui.app.ui.components.PrimaryButton
import com.kyusui.app.ui.components.TextButton

@Composable
fun RegisterScreen(
    viewModel: AuthViewModel,
    onLoginClick: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    var name by rememberSaveable { mutableStateOf("") }
    var phone by rememberSaveable { mutableStateOf("") }
    var email by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }
    var passwordConfirmation by rememberSaveable { mutableStateOf("") }

    val state = uiState as? AuthUiState.Unauthenticated
    val isSubmitting = state?.isSubmitting == true
    val errors = state?.fieldErrors.orEmpty()

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
            text = "Daftar Akun",
            style = MaterialTheme.typography.headlineSmall,
            color = MaterialTheme.colorScheme.primary
        )
        Text(
            text = "Buat akun pelanggan KYŪSUI",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
            modifier = Modifier.padding(top = 8.dp, bottom = 24.dp)
        )

        state?.errorMessage?.let { message ->
            InlineError(
                message = message,
                modifier = Modifier.padding(bottom = 16.dp)
            )
        }

        InputField(
            label = "Nama Lengkap",
            value = name,
            onValueChange = { input ->
                name = input
                viewModel.clearError()
            },
            placeholder = "Budi",
            leadingIcon = Icons.Outlined.Badge,
            isError = errors.containsKey(AuthViewModel.FIELD_NAME),
            errorMessage = errors[AuthViewModel.FIELD_NAME],
            isEnabled = !isSubmitting
        )

        InputField(
            label = "Nomor Telepon",
            value = phone,
            onValueChange = { input ->
                phone = input
                viewModel.clearError()
            },
            placeholder = "081234567890",
            keyboardType = KeyboardType.Phone,
            leadingIcon = Icons.Outlined.PhoneAndroid,
            isError = errors.containsKey(AuthViewModel.FIELD_PHONE),
            errorMessage = errors[AuthViewModel.FIELD_PHONE],
            isEnabled = !isSubmitting
        )

        InputField(
            label = "Email (Opsional)",
            value = email,
            onValueChange = { input ->
                email = input
                viewModel.clearError()
            },
            placeholder = "budi@example.com",
            keyboardType = KeyboardType.Email,
            leadingIcon = Icons.Outlined.AlternateEmail,
            isError = errors.containsKey(AuthViewModel.FIELD_EMAIL),
            errorMessage = errors[AuthViewModel.FIELD_EMAIL],
            isEnabled = !isSubmitting
        )

        PasswordField(
            label = "Kata Sandi",
            value = password,
            onValueChange = { input ->
                password = input
                viewModel.clearError()
            },
            isError = errors.containsKey(AuthViewModel.FIELD_PASSWORD),
            errorMessage = errors[AuthViewModel.FIELD_PASSWORD],
            isEnabled = !isSubmitting
        )

        PasswordField(
            label = "Konfirmasi Kata Sandi",
            value = passwordConfirmation,
            onValueChange = { input ->
                passwordConfirmation = input
                viewModel.clearError()
            },
            isError = errors.containsKey(AuthViewModel.FIELD_PASSWORD_CONFIRMATION),
            errorMessage = errors[AuthViewModel.FIELD_PASSWORD_CONFIRMATION],
            isEnabled = !isSubmitting
        )

        PrimaryButton(
            text = "Daftar",
            onClick = {
                viewModel.register(
                    RegisterRequest(
                        name = name,
                        phone = phone,
                        password = password,
                        passwordConfirmation = passwordConfirmation,
                        email = email.takeIf { it.isNotBlank() }
                    )
                )
            },
            isLoading = isSubmitting,
            enabled = !isSubmitting,
            modifier = Modifier.padding(top = 24.dp)
        )

        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 16.dp),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = "Sudah punya akun?",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            TextButton(
                text = "Masuk",
                onClick = onLoginClick,
                enabled = !isSubmitting
            )
        }
    }
}
