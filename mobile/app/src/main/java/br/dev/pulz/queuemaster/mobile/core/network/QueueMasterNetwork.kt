package br.dev.pulz.queuemaster.mobile.core.network

import com.google.gson.FieldNamingPolicy
import com.google.gson.Gson
import com.google.gson.GsonBuilder
import okhttp3.Authenticator
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.Response
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit
import br.dev.pulz.queuemaster.mobile.BuildConfig
import br.dev.pulz.queuemaster.mobile.core.utils.AppRuntime

object QueueMasterNetwork {
    private val cookieJar: PersistentCookieJar by lazy {
        PersistentCookieJar(AppRuntime.context())
    }

    private val gson: Gson = GsonBuilder()
        .setFieldNamingPolicy(FieldNamingPolicy.LOWER_CASE_WITH_UNDERSCORES)
        .serializeNulls()
        .create()

    private val refreshClient: OkHttpClient by lazy {
        OkHttpClient.Builder()
            .cookieJar(cookieJar)
            .connectTimeout(NetworkConfig.DefaultTimeoutMillis, TimeUnit.MILLISECONDS)
            .readTimeout(NetworkConfig.DefaultTimeoutMillis, TimeUnit.MILLISECONDS)
            .writeTimeout(NetworkConfig.DefaultTimeoutMillis, TimeUnit.MILLISECONDS)
            .build()
    }

    private val authenticator = Authenticator { _, response ->
        if (!shouldRefresh(response)) {
            return@Authenticator null
        }

        val refreshed = refreshSession()
        if (!refreshed) {
            return@Authenticator null
        }

        response.request.newBuilder()
            .header("X-Auth-Retry", "1")
            .build()
    }

    private val okHttpClient: OkHttpClient by lazy {
        OkHttpClient.Builder()
            .cookieJar(cookieJar)
            .authenticator(authenticator)
            .addInterceptor(
                HttpLoggingInterceptor().apply {
                    level = if (BuildConfig.DEBUG) {
                        HttpLoggingInterceptor.Level.BODY
                    } else {
                        HttpLoggingInterceptor.Level.BASIC
                    }
                }
            )
            .connectTimeout(NetworkConfig.DefaultTimeoutMillis, TimeUnit.MILLISECONDS)
            .readTimeout(NetworkConfig.DefaultTimeoutMillis, TimeUnit.MILLISECONDS)
            .writeTimeout(NetworkConfig.DefaultTimeoutMillis, TimeUnit.MILLISECONDS)
            .build()
    }

    private val retrofit: Retrofit by lazy {
        Retrofit.Builder()
            .baseUrl(NetworkConfig.ApiBaseUrl)
            .client(okHttpClient)
            .addConverterFactory(GsonConverterFactory.create(gson))
            .build()
    }

    fun <T> createService(serviceClass: Class<T>): T = retrofit.create(serviceClass)

    fun newImageClient(): OkHttpClient {
        return okHttpClient.newBuilder().build()
    }

    fun clearSessionCookies() {
        cookieJar.clear()
    }

    private fun shouldRefresh(response: Response): Boolean {
        if (response.code != 401) return false
        if (response.request.header("X-Auth-Retry") == "1") return false

        val path = response.request.url.encodedPath
        return !path.endsWith("/auth/google")
            && !path.endsWith("/auth/refresh")
            && !path.endsWith("/auth/logout")
    }

    @Synchronized
    private fun refreshSession(): Boolean {
        val refreshRequest = Request.Builder()
            .url("${NetworkConfig.ApiBaseUrl}${NetworkConfig.RefreshEndpoint}")
            .post("{}".toRequestBody("application/json".toMediaType()))
            .build()

        return runCatching {
            refreshClient.newCall(refreshRequest).execute().use { refreshResponse ->
                refreshResponse.isSuccessful
            }
        }.getOrDefault(false)
    }
}
