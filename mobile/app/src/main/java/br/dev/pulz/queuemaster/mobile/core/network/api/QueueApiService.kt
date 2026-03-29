package br.dev.pulz.queuemaster.mobile.core.network.api

import br.dev.pulz.queuemaster.mobile.core.network.dto.ApiEnvelopeDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.JoinQueueBodyDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.JoinQueueResponseDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.LeaveQueueResponseDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.QueueStatusResponseDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.ResolveQueueCodeResponseDto
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path

interface QueueApiService {
    @POST("queues/join")
    suspend fun joinQueueByCode(
        @Body body: JoinQueueBodyDto
    ): Response<ApiEnvelopeDto<JoinQueueResponseDto>>

    @GET("queues/resolve-code/{code}")
    suspend fun resolveQueueCode(
        @Path("code") accessCode: String
    ): Response<ApiEnvelopeDto<ResolveQueueCodeResponseDto>>

    @POST("queues/{id}/join")
    suspend fun joinQueue(
        @Path("id") queueId: Int,
        @Body body: JoinQueueBodyDto
    ): Response<ApiEnvelopeDto<JoinQueueResponseDto>>

    @GET("queues/{id}/status")
    suspend fun getQueueStatus(
        @Path("id") queueId: Int
    ): Response<ApiEnvelopeDto<QueueStatusResponseDto>>

    @POST("queues/{id}/leave")
    suspend fun leaveQueue(
        @Path("id") queueId: Int
    ): Response<ApiEnvelopeDto<LeaveQueueResponseDto>>
}
