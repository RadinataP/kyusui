package com.kyusui.app.core

sealed class Result<out T> {
    data class Success<out T>(val data: T) : Result<T>()
    data class Failure(val exception: Exception) : Result<Nothing>()

    companion object {
        fun <T> success(data: T): Result<T> = Success(data)
        fun <T> failure(exception: Exception): Result<T> = Failure(exception)
        fun <T> failure(message: String): Result<T> = Failure(Exception(message))
    }

    val isSuccess: Boolean get() = this is Success

    val isFailure: Boolean get() = this is Failure

    val dataOrNull: T? get() = (this as? Success)?.data

    val exceptionOrNull: Exception? get() = (this as? Failure)?.exception

    fun getOrNull(): T? = dataOrNull

    fun getOrThrow(): T = when (this) {
        is Success -> data
        is Failure -> throw exception
    }

    fun getOrDefault(defaultValue: @UnsafeVariance T): T = dataOrNull ?: defaultValue

    fun onSuccess(action: (T) -> Unit): Result<T> {
        if (this is Success) action(data)
        return this
    }

    fun onFailure(action: (Exception) -> Unit): Result<T> {
        if (this is Failure) action(exception)
        return this
    }

    fun <R> map(transform: (T) -> R): Result<R> = when (this) {
        is Success -> Success(transform(data))
        is Failure -> this
    }

    fun <R> flatMap(transform: (T) -> Result<R>): Result<R> = when (this) {
        is Success -> transform(data)
        is Failure -> this
    }

    fun toUnit(): Result<Unit> = map { }
}
