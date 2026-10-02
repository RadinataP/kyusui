package com.kyusui.app.ui.state

import com.kyusui.app.core.Result
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class UiStateTest {

    @Test
    fun `loading state has correct properties`() {
        val state = UiState.Loading<String>("Loading...")
        assertTrue(state.isLoading)
        assertFalse(state.isSuccess)
        assertFalse(state.isError)
        assertFalse(state.isEmpty)
        assertNull(state.dataOrNull)
        assertNull(state.errorOrNull)
    }

    @Test
    fun `success state has correct properties`() {
        val state = UiState.Success("data")
        assertFalse(state.isLoading)
        assertTrue(state.isSuccess)
        assertFalse(state.isError)
        assertFalse(state.isEmpty)
        assertEquals("data", state.dataOrNull)
        assertNull(state.errorOrNull)
    }

    @Test
    fun `error state has correct properties`() {
        val state = UiState.Error<String>("Error message", Exception("error"), true)
        assertFalse(state.isLoading)
        assertFalse(state.isSuccess)
        assertTrue(state.isError)
        assertFalse(state.isEmpty)
        assertNull(state.dataOrNull)
        assertEquals("Error message", state.errorOrNull)
    }

    @Test
    fun `empty state has correct properties`() {
        val state = UiState.Empty<String>("No data")
        assertFalse(state.isLoading)
        assertFalse(state.isSuccess)
        assertFalse(state.isError)
        assertTrue(state.isEmpty)
        assertNull(state.dataOrNull)
        assertNull(state.errorOrNull)
    }

    @Test
    fun `map transforms success`() {
        val state = UiState.Success(5).map { it * 2 }
        assertTrue(state.isSuccess)
        assertEquals(10, state.dataOrNull)
    }

    @Test
    fun `map preserves loading`() {
        val state = UiState.Loading<Int>().map { it * 2 }
        assertTrue(state.isLoading)
    }

    @Test
    fun `map preserves error`() {
        val state = UiState.Error<Int>("Error", Exception("error")).map { it * 2 }
        assertTrue(state.isError)
    }

    @Test
    fun `map preserves empty`() {
        val state = UiState.Empty<Int>().map { it * 2 }
        assertTrue(state.isEmpty)
    }

    @Test
    fun `flatMap chains success`() {
        val state = UiState.Success(5).flatMap { UiState.Success(it * 2) }
        assertTrue(state.isSuccess)
        assertEquals(10, state.dataOrNull)
    }

    @Test
    fun `flatMap can change state`() {
        val state = UiState.Success(5).flatMap { UiState.Error<Int>("Failed") }
        assertTrue(state.isError)
    }

    @Test
    fun `companion object creates states`() {
        assertTrue(UiState.loading<String>().isLoading)
        assertTrue(UiState.success("data").isSuccess)
        assertTrue(UiState.error<String>("Error").isError)
        assertTrue(UiState.empty<String>().isEmpty)
    }

    @Test
    fun `result toUiState conversion`() {
        val successResult = Result.success("data")
        val successState = successResult.toUiState()
        assertTrue(successState.isSuccess)
        assertEquals("data", successState.dataOrNull)

        val failureResult = Result.failure<String>(Exception("error"))
        val failureState = failureResult.toUiState()
        assertTrue(failureState.isError)
        assertEquals("error", failureState.errorOrNull)
    }
}