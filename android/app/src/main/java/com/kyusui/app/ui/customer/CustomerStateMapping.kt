package com.kyusui.app.ui.customer

import com.kyusui.app.core.ForbiddenException
import com.kyusui.app.core.NotFoundException
import com.kyusui.app.core.Result
import com.kyusui.app.core.UnauthorizedException
import com.kyusui.app.core.userMessage
import com.kyusui.app.domain.model.PaginatedResult
import com.kyusui.app.ui.state.LoadState
import com.kyusui.app.ui.state.SubmitState

/**
 * Pemetaan hasil repository ke state UI.
 *
 * `isUnauthorized` dan `isRetryable` ditandai terpisah supaya layer UI bisa
 * mengarahkan customer kembali ke login saat sesi sudah tidak valid, bukan
 * sekadar menampilkan pesan error lalu mencoba reload terus-menerus.
 */

internal fun Result.Failure.toLoadError(): LoadState.Error = LoadState.Error(
    message = exception.userMessage(),
    isUnauthorized = exception is UnauthorizedException,
    isRetryable = exception !is UnauthorizedException && exception !is ForbiddenException,
    isNotFound = exception is NotFoundException
)

internal fun Result.Failure.toSubmitError(): SubmitState.Error = SubmitState.Error(
    message = exception.userMessage(),
    isUnauthorized = exception is UnauthorizedException,
    isRetryable = exception !is UnauthorizedException && exception !is ForbiddenException
)

/**
 * Memetakan list datar, dengan memisahkan kasus "sukses tapi kosong" dari
 * "sukses dengan data".
 */
internal fun <T> List<T>.toListLoadState(emptyMessage: String): LoadState<List<T>> =
    if (isEmpty()) LoadState.Empty(emptyMessage) else LoadState.Success(this)

/**
 * Memetakan halaman ber-paginasi menjadi list datar. Penanda `hasMore`
 * tetap dibaca terpisah oleh ViewModel untuk infinite scroll.
 */
internal fun <T> Result<PaginatedResult<T>>.toPagedListLoadState(
    emptyMessage: String
): LoadState<List<T>> = when (this) {
    is Result.Success -> data.data.toListLoadState(emptyMessage)
    is Result.Failure -> toLoadError()
}