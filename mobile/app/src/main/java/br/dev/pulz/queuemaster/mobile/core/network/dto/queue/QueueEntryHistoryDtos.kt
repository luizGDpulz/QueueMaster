package br.dev.pulz.queuemaster.mobile.core.network.dto.queue

import br.dev.pulz.queuemaster.mobile.core.model.QueueEntryHistoryDetail
import br.dev.pulz.queuemaster.mobile.core.model.QueueEntryHistoryEvent
import br.dev.pulz.queuemaster.mobile.core.model.QueueEntryHistorySummary
import br.dev.pulz.queuemaster.mobile.core.model.QueueEntryHistoryTimeline
import java.text.SimpleDateFormat
import java.util.Locale

data class QueueEntryHistoryQueueDto(
    val id: Int? = null,
    val name: String,
    val status: String? = null
)

data class QueueEntryHistoryEstablishmentDto(
    val name: String? = null,
    val slug: String? = null
)

data class QueueEntryHistorySummaryDto(
    val entryPublicId: String,
    val status: String,
    val isActive: Boolean,
    val joinedAt: String? = null,
    val calledAt: String? = null,
    val servedAt: String? = null,
    val completedAt: String? = null,
    val lastEventType: String,
    val lastEventAt: String? = null,
    val eventsCount: Int = 0,
    val canJoinAgain: Boolean = false,
    val queue: QueueEntryHistoryQueueDto,
    val establishment: QueueEntryHistoryEstablishmentDto = QueueEntryHistoryEstablishmentDto(),
    val serviceName: String? = null,
    val professionalName: String? = null
)

data class QueueEntryHistoryDetailDto(
    val publicId: String,
    val status: String,
    val isActive: Boolean,
    val queue: QueueEntryHistoryQueueDto,
    val establishment: QueueEntryHistoryEstablishmentDto = QueueEntryHistoryEstablishmentDto(),
    val serviceName: String? = null,
    val professionalName: String? = null,
    val position: Int? = null,
    val peopleAhead: Int? = null,
    val estimatedWaitMinutes: Int? = null,
    val joinedAt: String? = null,
    val calledAt: String? = null,
    val servedAt: String? = null,
    val completedAt: String? = null
)

data class QueueEntryEventDto(
    val type: String,
    val occurredAt: String? = null,
    val actorType: String? = null,
    val actorUserName: String? = null,
    val payload: Map<String, Any?>? = emptyMap()
)

data class QueueEntrySingleResponseDto(
    val entry: QueueEntryHistoryDetailDto
)

data class QueueEntryEventsResponseDto(
    val entry: QueueEntryHistoryDetailDto,
    val events: List<QueueEntryEventDto> = emptyList()
)

fun QueueEntryHistorySummaryDto.toQueueEntryHistorySummary(): QueueEntryHistorySummary {
    return QueueEntryHistorySummary(
        entryPublicId = entryPublicId,
        status = status,
        isActive = isActive,
        joinedAt = joinedAt.parseQueueMasterTimestamp(),
        calledAt = calledAt.parseQueueMasterTimestamp(),
        servedAt = servedAt.parseQueueMasterTimestamp(),
        completedAt = completedAt.parseQueueMasterTimestamp(),
        lastEventType = lastEventType,
        lastEventAt = lastEventAt.parseQueueMasterTimestamp(),
        eventsCount = eventsCount,
        canJoinAgain = canJoinAgain,
        queueName = queue.name,
        queueStatus = queue.status,
        establishmentName = establishment.name,
        establishmentSlug = establishment.slug,
        serviceName = serviceName,
        professionalName = professionalName
    )
}

fun QueueEntrySingleResponseDto.toQueueEntryHistoryDetail(): QueueEntryHistoryDetail {
    return entry.toQueueEntryHistoryDetail()
}

fun QueueEntryEventsResponseDto.toQueueEntryHistoryTimeline(): QueueEntryHistoryTimeline {
    return QueueEntryHistoryTimeline(
        entry = entry.toQueueEntryHistoryDetail(),
        events = events.map { it.toQueueEntryHistoryEvent() }
    )
}

private fun QueueEntryHistoryDetailDto.toQueueEntryHistoryDetail(): QueueEntryHistoryDetail {
    return QueueEntryHistoryDetail(
        publicId = publicId,
        status = status,
        isActive = isActive,
        queueName = queue.name,
        queueStatus = queue.status,
        establishmentName = establishment.name,
        establishmentSlug = establishment.slug,
        serviceName = serviceName,
        professionalName = professionalName,
        position = position,
        peopleAhead = peopleAhead,
        estimatedWaitMinutes = estimatedWaitMinutes,
        joinedAt = joinedAt.parseQueueMasterTimestamp(),
        calledAt = calledAt.parseQueueMasterTimestamp(),
        servedAt = servedAt.parseQueueMasterTimestamp(),
        completedAt = completedAt.parseQueueMasterTimestamp()
    )
}

private fun QueueEntryEventDto.toQueueEntryHistoryEvent(): QueueEntryHistoryEvent {
    return QueueEntryHistoryEvent(
        type = type,
        occurredAt = occurredAt.parseQueueMasterTimestamp(),
        actorType = actorType,
        actorUserName = actorUserName,
        payload = payload.orEmpty()
    )
}

private fun String?.parseQueueMasterTimestamp(): Long? {
    if (this.isNullOrBlank()) return null

    val patterns = listOf(
        "yyyy-MM-dd HH:mm:ss",
        "yyyy-MM-dd'T'HH:mm:ss",
        "yyyy-MM-dd'T'HH:mm:ssXXX",
        "yyyy-MM-dd'T'HH:mm:ss.SSSXXX"
    )

    patterns.forEach { pattern ->
        runCatching {
            SimpleDateFormat(pattern, Locale.US).parse(this)?.time
        }.getOrNull()?.let { parsed ->
            return parsed
        }
    }

    return null
}
