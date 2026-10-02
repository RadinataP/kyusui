package com.kyusui.app.data.api

import com.kyusui.app.BuildConfig
import coil.ImageLoader
import android.content.Context
import com.kyusui.app.core.Constants
import com.kyusui.app.data.datastore.PreferencesDataSource
import dagger.Module
import dagger.Provides
import dagger.hilt.EntryPoint
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import androidx.lifecycle.ViewModel
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import kotlinx.serialization.json.Json
import okhttp3.Interceptor
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Response
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import java.util.concurrent.TimeUnit
import javax.inject.Inject
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object RetrofitModule {

    private val jsonMediaType = Constants.CONTENT_TYPE_JSON.toMediaType()

    @Provides
    @Singleton
    fun provideJson(): Json = Json {
        ignoreUnknownKeys = true
        isLenient = true
        explicitNulls = false
        coerceInputValues = true
    }

    @Provides
    @Singleton
    fun provideAuthTokenStore(
        preferencesRepository: PreferencesDataSource
    ): AuthTokenStore = AuthTokenStore(preferencesRepository)

    @Provides
    @Singleton
    fun provideAuthInterceptor(authTokenStore: AuthTokenStore): AuthInterceptor =
        AuthInterceptor(authTokenStore)

    @Provides
    @Singleton
    fun provideApiErrorParser(json: Json): ApiErrorParser = ApiErrorParser(json)

    @Provides
    @Singleton
    fun provideOkHttpClient(
        authInterceptor: AuthInterceptor,
        apiErrorParser: ApiErrorParser
    ): OkHttpClient {
        return OkHttpClient.Builder()
            .connectTimeout(Constants.DEFAULT_TIMEOUT_SECONDS, TimeUnit.SECONDS)
            .readTimeout(Constants.DEFAULT_TIMEOUT_SECONDS, TimeUnit.SECONDS)
            .writeTimeout(Constants.DEFAULT_TIMEOUT_SECONDS, TimeUnit.SECONDS)
            .retryOnConnectionFailure(true)
            .addInterceptor(authInterceptor)
            .addInterceptor(SafeLoggingInterceptor())
            .addNetworkInterceptor(ResponseValidationInterceptor(apiErrorParser))
            .build()
    }

    @Provides
    @Singleton
    fun provideRetrofit(okHttpClient: OkHttpClient, json: Json): Retrofit = Retrofit.Builder()
        .baseUrl(Constants.BASE_URL_DEV)
        .client(okHttpClient)
        .addConverterFactory(json.asConverterFactory(jsonMediaType))
        .build()

    @Provides
    @Singleton
    fun provideAuthApi(retrofit: Retrofit): AuthApi = retrofit.create(AuthApi::class.java)

    @Provides
    @Singleton
    fun provideCustomerApi(retrofit: Retrofit): CustomerApi = retrofit.create(CustomerApi::class.java)

    /**
     * Coil memakai OkHttpClient yang sama dengan Retrofit.
     *
     * QRIS image adalah konfigurasi privat toko dan dilayani lewat endpoint yang
     * butuh `Authorization`. Tanpa [AuthInterceptor], Coil memakai client
     * default-nya sendiri yang tidak membawa token, sehingga gambar gagal dimuat
     * dan QRIS tidak bisa dipakai.
     */
    @Provides
    @Singleton
    fun provideImageLoader(
        @ApplicationContext context: Context,
        okHttpClient: OkHttpClient
    ): ImageLoader = ImageLoader.Builder(context)
        // Coil memakai `Call.Factory` yang sama dengan Retrofit, sehingga
        // request gambar ikut membawa header Authorization.
        .callFactory(okHttpClient)
        .build()
}

/**
 * Entry point untuk mengambil [ImageLoader] dari luar[ViewModel].
 *
 * Coil butuh `OkHttpClient` yang sama dengan Retrofit agar request gambar
 * membawa token. `hiltViewModel()` hanya berlaku untuk ViewModel, jadi
 * dependency ini diambil lewat entry point.
 */
@EntryPoint
@InstallIn(SingletonComponent::class)
interface ImageLoaderEntryPoint {
    fun imageLoader(): ImageLoader
}

@Singleton
class AuthTokenStore @Inject constructor(
    private val preferencesRepository: PreferencesDataSource
) {
    @Volatile
    private var cachedToken: String? = null

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    init {
        scope.launch {
            preferencesRepository.accessToken.collect { token ->
                cachedToken = token?.takeIf { it.isNotBlank() }
            }
        }
    }

    fun currentToken(): String? = cachedToken

    suspend fun refresh(): String? = preferencesRepository.getAccessTokenOnce()
        ?.takeIf { it.isNotBlank() }
        .also { cachedToken = it }
}

@Singleton
class AuthInterceptor @Inject constructor(
    private val authTokenStore: AuthTokenStore
) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val originalRequest = chain.request()
        val requestBuilder = originalRequest.newBuilder()
            .header(Constants.ACCEPT_HEADER, Constants.ACCEPT_JSON)

        authTokenStore.currentToken()?.let { token ->
            requestBuilder.header(Constants.AUTH_HEADER, "${Constants.BEARER_PREFIX}$token")
        }

        return chain.proceed(requestBuilder.build())
    }
}

/**
 * Logger yang aman untuk session data.
 *
 * Token tidak boleh logged. Header `Authorization` selalu disamarkan, dan level
 * logging dibatasi ke HEADERS sehingga body respons `/auth/login` dan
 * `/auth/register` yang memuat token tidak pernah ikut tercetak. Body request
 * juga memuat kata sandi, sehingga tidak pernah dicatat di level mana pun.
 */
class SafeLoggingInterceptor : Interceptor {

    private val delegate: HttpLoggingInterceptor = HttpLoggingInterceptor().apply {
        level = if (BuildConfig.DEBUG) {
            HttpLoggingInterceptor.Level.HEADERS
        } else {
            HttpLoggingInterceptor.Level.NONE
        }
        redactHeader(Constants.AUTH_HEADER)
    }

    override fun intercept(chain: Interceptor.Chain): Response = delegate.intercept(chain)
}

class ResponseValidationInterceptor(
    private val errorParser: ApiErrorParser
) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val response = chain.proceed(chain.request())
        val code = response.code

        if (code in 200..299) return response

        val errorBody = runCatching { response.peekBody(MAX_ERROR_BODY_BYTES).string() }.getOrNull()

        throw errorParser.parse(
            statusCode = code,
            rawBody = errorBody,
            httpMessage = response.message
        )
    }

    private companion object {
        const val MAX_ERROR_BODY_BYTES = 4L * 1024L
    }
}
