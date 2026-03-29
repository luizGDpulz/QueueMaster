package br.dev.pulz.queuemaster.mobile.core.network.dto.queue

import br.dev.pulz.queuemaster.mobile.core.model.JoinQueueResult
import br.dev.pulz.queuemaster.mobile.core.model.QueueStatistics
import br.dev.pulz.queuemaster.mobile.core.model.QueueStatus
import br.dev.pulz.queuemaster.mobile.core.model.QueueSummary
import br.dev.pulz.queuemaster.mobile.core.model.QueueUserEntry
import br.dev.pulz.queuemaster.mobile.core.model.ResolvedQueueCode

data class JoinQueueBodyDto(
    val accessCode: String? = null
)

data class JoinQueueResponseDto(
    val entry: QueueEntryDto,
    val message: String
)

data class LeaveQueueResponseDto(
    val message: String
)

data class ResolveQueueCodeResponseDto(
    val queue: QueueDto,
    val accessCode: ResolvedAccessCodeDto
)

data class QueueStatusResponseDto(
    val queue: QueueDto,
    val statistics: QueueStatisticsDto,
    val userEntry: UserEntryStatusDto? = null
)

data class QueueDto(
    val id: Int,
    val name: String,
    val establishmentName: String? = null,
    val serviceName: String? = null,
    val status: String? = null
)

data class QueueStatisticsDto(
    val totalWaiting: Int = 0,
    val totalBeingServed: Int = 0,
    val totalCompletedToday: Int = 0,
    val averageWaitTimeMinutes: Int? = null
)

data class QueueEntryDto(
    val id: Int,
    val queueId: Int,
    val status: String,
    val position: Int? = null,
    val createdAt: String? = null
)

data class UserEntryStatusDto(
    val entryId: Int,
    val position: Int? = null,
    val estimatedWaitMinutes: Int? = null
)

data class ResolvedAccessCodeDto(
    val code: String,
    val expiresAt: String? = null,
    val maxUses: Int? = null,
    val uses: Int = 0,
    val remainingUses: Int? = null
)

fun JoinQueueResponseDto.toJoinQueueResult(
    accessCode: String?
): JoinQueueResult {
    return JoinQueueResult(
        queueId = entry.queueId,
        entryStatus = entry.status,
        joinedAt = entry.createdAt,
        accessCode = accessCode
    )
}

fun ResolveQueueCodeResponseDto.toResolvedQueueCode(): ResolvedQueueCode {
    return ResolvedQueueCode(
        queueId = queue.id,
        queueName = queue.name,
        establishmentName = queue.establishmentName,
        serviceName = queue.serviceName,
        accessCode = accessCode.code
    )
}

fun QueueStatusResponseDto.toQueueStatus(joinedAt: String? = null, accessCode: String? = null): QueueStatus {
    val position = userEntry?.position
    return QueueStatus(
        queue = QueueSummary(
            id = queue.id,
            name = queue.name,
            establishmentName = queue.establishmentName.orEmpty(),
            serviceName = queue.serviceName,
            isOpen = queue.status != "closed",
            accessCodeRequired = true
        ),
        statistics = QueueStatistics(
            totalWaiting = statistics.totalWaiting,
            totalServing = statistics.totalBeingServed,
            totalCompletedToday = statistics.totalCompletedToday,
            averageWaitTimeMinutes = statistics.averageWaitTimeMinutes
        ),
        userEntry = userEntry?.let {
            QueueUserEntry(
                entryId = it.entryId,
                status = "waiting",
                position = it.position,
                peopleAhead = (position ?: 1).coerceAtLeast(1) - 1,
                estimatedWaitMinutes = it.estimatedWaitMinutes,
                joinedAt = joinedAt,
                accessCode = accessCode
            )
        }
    )
}
