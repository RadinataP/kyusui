package com.kyusui.app.ui.state

import com.kyusui.app.core.Result

sealed interface UiState<out T> {
    data class Loading<out T>(val message: String? = null) : UiState<T>
    data class Success<out T>(val data: T) : UiState<T>
    data class Error<out T>(
        val message: String,
        val throwable: Throwable? = null,
        val isRetryable: Boolean = true
    ) : UiState<T>

    data class Empty<out T>(val message: String = "Tidak ada data") : UiState<T>

    val isLoading: Boolean get() = this is Loading
    val isSuccess: Boolean get() = this is Success
    val isError: Boolean get() = this is Error
    val isEmpty: Boolean get() = this is Empty

    val dataOrNull: T? get() = (this as? Success)?.data

    val errorOrNull: String? get() = (this as? Error)?.message

    fun <R> map(transform: (T) -> R): UiState<R> = when (this) {
        is Loading -> Loading(message)
        is Success -> Success(transform(data))
        is Error -> Error(message, throwable, isRetryable)
        is Empty -> Empty(message)
    }

    fun <R> flatMap(transform: (T) -> UiState<R>): UiState<R> = when (this) {
        is Loading -> Loading(message)
        is Success -> transform(data)
        is Error -> Error(message, throwable, isRetryable)
        is Empty -> Empty(message)
    }

    fun onEachState(
        onLoading: (String?) -> Unit = {},
        onSuccess: (T) -> Unit = {},
        onError: (String, Boolean) -> Unit = { _, _ -> },
        onEmpty: (String) -> Unit = {}
    ) {
        when (this) {
            is Loading -> onLoading(message)
            is Success -> onSuccess(data)
            is Error -> onError(message, isRetryable)
            is Empty -> onEmpty(message)
        }
    }

    companion object {
        fun <T> loading(message: String? = null): UiState<T> = Loading(message)

        fun <T> success(data: T): UiState<T> = Success(data)

        fun <T> error(
            message: String,
            throwable: Throwable? = null,
            isRetryable: Boolean = true
        ): UiState<T> = Error(message, throwable, isRetryable)

        fun <T> empty(message: String = "Tidak ada data"): UiState<T> = Empty(message)
    }
}

fun <T> Result<T>.toUiState(): UiState<T> = when (this) {
    is Result.Success -> UiState.Success(data)
    is Result.Failure -> UiState.Error(
        message = exception.message ?: "Terjadi kesalahan tidak diketahui",
        throwable = exception
    )
}
