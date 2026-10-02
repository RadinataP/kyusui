package com.kyusui.app.data.datastore

import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.emptyPreferences
import androidx.datastore.preferences.core.stringPreferencesKey
import com.kyusui.app.core.Result
import com.kyusui.app.core.safeApiCall
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.catch
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import java.io.IOException
import javax.inject.Inject
import javax.inject.Singleton

interface PreferencesDataSource {
    val accessToken: Flow<String?>
    val refreshToken: Flow<String?>
    val userId: Flow<String?>
    val userRole: Flow<String?>
    val userEmail: Flow<String?>
    val userName: Flow<String?>
    val userStatus: Flow<String?>
    val deviceToken: Flow<String?>
    val isFirstLaunch: Flow<Boolean>

    /**
     * Menggabungkan token dan role menjadi satu state supaya entry point tidak
     * pernah membaca role dari session yang sudah tidak punya token.
     */
    val storedSession: Flow<StoredSession?>

    suspend fun saveAuthData(
        accessToken: String,
        userId: String,
        role: String,
        email: String?,
        name: String,
        status: String?
    ): Result<Unit>

    suspend fun saveDeviceToken(token: String): Result<Unit>
    suspend fun setFirstLaunchComplete(): Result<Unit>
    suspend fun getAccessTokenOnce(): String?
    suspend fun clearAuthData(): Result<Unit>
}

data class StoredSession(
    val token: String,
    val userId: String,
    val role: String,
    val name: String,
    val email: String?,
    val status: String?
)

@Singleton
class PreferencesRepository @Inject constructor(
    private val dataStore: DataStore<Preferences>
) : PreferencesDataSource {

    private val preferences: Flow<Preferences> = dataStore.data
        .catch { throwable ->
            if (throwable is IOException) emit(emptyPreferences()) else throw throwable
        }

    override val accessToken: Flow<String?> = preferences.map { it[Keys.ACCESS_TOKEN] }
    override val refreshToken: Flow<String?> = preferences.map { it[Keys.REFRESH_TOKEN] }
    override val userId: Flow<String?> = preferences.map { it[Keys.USER_ID] }
    override val userRole: Flow<String?> = preferences.map { it[Keys.USER_ROLE] }
    override val userEmail: Flow<String?> = preferences.map { it[Keys.USER_EMAIL] }
    override val userName: Flow<String?> = preferences.map { it[Keys.USER_NAME] }
    override val userStatus: Flow<String?> = preferences.map { it[Keys.USER_STATUS] }
    override val deviceToken: Flow<String?> = preferences.map { it[Keys.DEVICE_TOKEN] }
    override val isFirstLaunch: Flow<Boolean> = preferences.map { it[Keys.IS_FIRST_LAUNCH] ?: true }

    override val storedSession: Flow<StoredSession?> = preferences.map { prefs ->
        val token = prefs[Keys.ACCESS_TOKEN]?.takeIf { it.isNotBlank() }
        val userId = prefs[Keys.USER_ID]?.takeIf { it.isNotBlank() }
        val role = prefs[Keys.USER_ROLE]?.takeIf { it.isNotBlank() }
        if (token == null || userId == null || role == null) {
            null
        } else {
            StoredSession(
                token = token,
                userId = userId,
                role = role,
                name = prefs[Keys.USER_NAME].orEmpty(),
                email = prefs[Keys.USER_EMAIL],
                status = prefs[Keys.USER_STATUS]
            )
        }
    }

    override suspend fun saveAuthData(
        accessToken: String,
        userId: String,
        role: String,
        email: String?,
        name: String,
        status: String?
    ): Result<Unit> = safeApiCall {
        dataStore.edit { prefs ->
            prefs[Keys.ACCESS_TOKEN] = accessToken
            prefs[Keys.USER_ID] = userId
            prefs[Keys.USER_ROLE] = role
            prefs[Keys.USER_NAME] = name
            prefs[Keys.IS_FIRST_LAUNCH] = false
            status?.let { prefs[Keys.USER_STATUS] = it }
            if (email.isNullOrBlank()) {
                prefs.remove(Keys.USER_EMAIL)
            } else {
                prefs[Keys.USER_EMAIL] = email
            }
        }
    }

    override suspend fun saveDeviceToken(token: String): Result<Unit> = safeApiCall {
        dataStore.edit { prefs -> prefs[Keys.DEVICE_TOKEN] = token }
    }

    override suspend fun setFirstLaunchComplete(): Result<Unit> = safeApiCall {
        dataStore.edit { prefs -> prefs[Keys.IS_FIRST_LAUNCH] = false }
    }

    override suspend fun getAccessTokenOnce(): String? = accessToken.first()

    override suspend fun clearAuthData(): Result<Unit> = safeApiCall {
        dataStore.edit { prefs ->
            prefs.remove(Keys.ACCESS_TOKEN)
            prefs.remove(Keys.REFRESH_TOKEN)
            prefs.remove(Keys.USER_ID)
            prefs.remove(Keys.USER_ROLE)
            prefs.remove(Keys.USER_EMAIL)
            prefs.remove(Keys.USER_NAME)
            prefs.remove(Keys.USER_STATUS)
            prefs[Keys.IS_FIRST_LAUNCH] = false
        }
    }

    private object Keys {
        val ACCESS_TOKEN = stringPreferencesKey("access_token")
        val REFRESH_TOKEN = stringPreferencesKey("refresh_token")
        val USER_ID = stringPreferencesKey("user_id")
        val USER_ROLE = stringPreferencesKey("user_role")
        val USER_EMAIL = stringPreferencesKey("user_email")
        val USER_NAME = stringPreferencesKey("user_name")
        val USER_STATUS = stringPreferencesKey("user_status")
        val DEVICE_TOKEN = stringPreferencesKey("device_token")
        val IS_FIRST_LAUNCH = booleanPreferencesKey("is_first_launch")
    }
}
