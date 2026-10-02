package com.kyusui.app.core

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class ResultTest {

    @Test
    fun `success holds data`() {
        val result = Result.success("test")
        assertTrue(result.isSuccess)
        assertFalse(result.isFailure)
        assertEquals("test", result.getOrNull())
        assertEquals("test", result.getOrThrow())
    }

    @Test
    fun `failure holds exception`() {
        val exception = Exception("error")
        val result: Result<String> = Result.failure(exception)
        assertFalse(result.isSuccess)
        assertTrue(result.isFailure)
        assertNull(result.getOrNull())
        assertEquals(exception, (result as Result.Failure).exception)
    }

    @Test
    fun `map transforms success`() {
        val result = Result.success(5).map { it * 2 }
        assertTrue(result.isSuccess)
        assertEquals(10, result.getOrThrow())
    }

    @Test
    fun `map preserves failure`() {
        val exception = Exception("error")
        val result = Result.failure<Int>(exception).map { it * 2 }
        assertTrue(result.isFailure)
        assertEquals(exception, (result as Result.Failure).exception)
    }

    @Test
    fun `flatMap chains success`() {
        val result = Result.success(5).flatMap { Result.success(it * 2) }
        assertTrue(result.isSuccess)
        assertEquals(10, result.getOrThrow())
    }

    @Test
    fun `flatMap preserves failure`() {
        val exception = Exception("error")
        val result = Result.failure<Int>(exception).flatMap { Result.success(it * 2) }
        assertTrue(result.isFailure)
        assertEquals(exception, (result as Result.Failure).exception)
    }

    @Test
    fun `onSuccess executes action`() {
        var captured: String? = null
        Result.success("test").onSuccess { captured = it }
        assertEquals("test", captured)
    }

    @Test
    fun `onFailure executes action`() {
        var captured: Exception? = null
        Result.failure<String>(Exception("error")).onFailure { captured = it }
        assertNotNull(captured)
    }

    @Test
    fun `getOrThrow throws on failure`() {
        val exception = Exception("error")
        val result = Result.failure<String>(exception)
        try {
            result.getOrThrow()
            throw AssertionError("Should have thrown")
        } catch (e: Exception) {
            assertEquals(exception, e)
        }
    }

    @Test
    fun `getOrDefault returns default on failure`() {
        assertEquals("fallback", Result.failure<String>(Exception("x")).getOrDefault("fallback"))
        assertEquals("value", Result.success("value").getOrDefault("fallback"))
    }

    @Test
    fun `toUnit maps success`() {
        val result = Result.success(5).toUnit()
        assertTrue(result.isSuccess)
    }
}
