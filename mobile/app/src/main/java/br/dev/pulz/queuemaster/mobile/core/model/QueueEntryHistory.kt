package br.dev.pulz.queuemaster.mobile.core.model

data class QueueEntryHistorySummary(
    val entryPublicId: String,
    val status: String,
    val isActive: Boolean,
    val joinedAt: Long?,
    val calledAt: Long?,
    val servedAt: Long?,
    val completedAt: Long?,
    val lastEventType: String,
    val lastEventAt: Long?,
    val eventsCount: Int,
    val canJoinAgain: Boolean,
    val queueName: String,
    val queueStatus: String? = null,
    val establishmentName: String? = null,
    val establishmentSlug: String? = null,
    val serviceName: String? = null,
    val professionalName: String? = null
)

data class QueueEntryHistoryDetail(
    val publicId: String,
    val status: String,
    val isActive: Boolean,
    val queueName: String,
    val queueStatus: String? = null,
    val establishmentName: String? = null,
    val establishmentSlug: String? = null,
    val serviceName: String? = null,
    val professionalName: String? = null,
    val position: Int? = null,
    val peopleAhead: Int? = null,
    val estimatedWaitMinutes: Int? = null,
    val joinedAt: Long? = null,
    val calledAt: Long? = null,
    val servedAt: Long? = null,
    val completedAt: Long? = null
)

data class QueueEntryHistoryEvent(
    val type: String,
    val occurredAt: Long?,
    val actorType: String? = null,
    val actorUserName: String? = null,
    val payload: Map<String, Any?> = emptyMap()
)

data class QueueEntryHistoryTimeline(
    val entry: QueueEntryHistoryDetail,
    val events: List<QueueEntryHistoryEvent>
)
