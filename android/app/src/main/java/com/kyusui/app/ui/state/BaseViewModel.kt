package com.kyusui.app.ui.state

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

open class BaseViewModel : ViewModel() {

    protected fun launchTask(block: suspend () -> Unit): Job = viewModelScope.launch {
        block()
    }
}

abstract class BaseStateViewModel<T> : BaseViewModel() {

    private val _uiState = MutableStateFlow<UiState<T>>(UiState.Loading())
    val uiState: StateFlow<UiState<T>> = _uiState.asStateFlow()

    protected fun setLoading(message: String? = null) {
        _uiState.value = UiState.Loading(message)
    }

    protected fun setSuccess(data: T) {
        _uiState.value = UiState.Success(data)
    }

    protected fun setError(
        message: String,
        throwable: Throwable? = null,
        isRetryable: Boolean = true
    ) {
        _uiState.value = UiState.Error(message, throwable, isRetryable)
    }

    protected fun setEmpty(message: String = "Tidak ada data") {
        _uiState.value = UiState.Empty(message)
    }
}
