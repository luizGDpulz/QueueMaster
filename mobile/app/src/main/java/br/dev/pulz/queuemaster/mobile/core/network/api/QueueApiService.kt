package br.dev.pulz.queuemaster.mobile.core.network.api

import br.dev.pulz.queuemaster.mobile.core.network.dto.ApiEnvelopeDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.CurrentActiveQueueResponseDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.JoinQueueBodyDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.JoinQueueResponseDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.LeaveQueueResponseDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.QueueEntryEventsResponseDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.QueueEntryHistorySummaryDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.QueueEntrySingleResponseDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.QueueStatusResponseDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.ResolveQueueCodeResponseDto
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

interface QueueApiService {
    @GET("queues/current")
    suspend fun getCurrentActiveQueue(): Response<ApiEnvelopeDto<CurrentActiveQueueResponseDto>>

    @GET("queue-entries/history")
    suspend fun getQueueEntryHistory(
        @Query("state") state: String? = null
    ): Response<ApiEnvelopeDto<List<QueueEntryHistorySummaryDto>>>

    @GET("queue-entries/{publicId}")
    suspend fun getQueueEntry(
        @Path("publicId") publicId: String
    ): Response<ApiEnvelopeDto<QueueEntrySingleResponseDto>>

    @GET("queue-entries/{publicId}/events")
    suspend fun getQueueEntryEvents(
        @Path("publicId") publicId: String
    ): Response<ApiEnvelopeDto<QueueEntryEventsResponseDto>>

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
