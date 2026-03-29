package br.dev.pulz.queuemaster.mobile.core.network.api

import br.dev.pulz.queuemaster.mobile.core.network.dto.ApiEnvelopeDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.auth.AuthGoogleRequestDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.auth.AuthGoogleResponseDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.auth.AuthMeResponseDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.auth.LogoutResponseDto
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST

interface AuthApiService {
    @POST("auth/google")
    suspend fun loginWithGoogle(
        @Body body: AuthGoogleRequestDto
    ): Response<ApiEnvelopeDto<AuthGoogleResponseDto>>

    @POST("auth/refresh")
    suspend fun refreshSession(): Response<ApiEnvelopeDto<AuthMeResponseDto>>

    @GET("auth/me")
    suspend fun me(): Response<ApiEnvelopeDto<AuthMeResponseDto>>

    @POST("auth/logout")
    suspend fun logout(): Response<ApiEnvelopeDto<LogoutResponseDto>>
}
