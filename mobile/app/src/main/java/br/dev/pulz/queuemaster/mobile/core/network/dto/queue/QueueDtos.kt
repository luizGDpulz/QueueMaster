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

data class CurrentActiveQueueResponseDto(
    val queue: QueueDto,
    val entry: QueueEntryDto
)

data class ResolveQueueCodeResponseDto(
    val queue: QueueDto,
    val accessCode: ResolvedAccessCodeDto
)

data class QueueStatusResponseDto(
    val queue: QueueDto,
    val statistics: QueueStatisticsDto,
    val entriesServing: List<ServingQueueEntryDto> = emptyList(),
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

data class ServingQueueEntryDto(
    val id: Int,
    val userId: Int? = null,
    val status: String,
    val createdAt: String? = null,
    val calledAt: String? = null,
    val servingSinceMinutes: Int? = null,
    val professionalName: String? = null
)

data class UserEntryStatusDto(
    val entryId: Int,
    val status: String? = null,
    val position: Int? = null,
    val queuePosition: Int? = null,
    val peopleAhead: Int? = null,
    val estimatedWaitMinutes: Int? = null,
    val joinedAt: String? = null,
    val calledAt: String? = null,
    val servingSinceMinutes: Int? = null,
    val professionalName: String? = null
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
        entryId = entry.id,
        entryStatus = entry.status,
        joinedAt = entry.createdAt,
        accessCode = accessCode
    )
}

fun CurrentActiveQueueResponseDto.toJoinQueueResult(): JoinQueueResult {
    return JoinQueueResult(
        queueId = queue.id,
        entryId = entry.id,
        queueName = queue.name,
        entryStatus = entry.status,
        joinedAt = entry.createdAt,
        joinedSuccessfully = false
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

fun QueueStatusResponseDto.toQueueStatus(
    joinedAt: String? = null,
    accessCode: String? = null,
    authenticatedUserId: Int? = null
): QueueStatus {
    val resolvedUserEntry = userEntry ?: entriesServing.firstOrNull { entry ->
        authenticatedUserId != null && entry.userId == authenticatedUserId
    }?.toUserEntryStatus()

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
        userEntry = resolvedUserEntry?.let {
            val resolvedPosition = it.queuePosition ?: it.position
            QueueUserEntry(
                entryId = it.entryId,
                status = it.status ?: "waiting",
                position = resolvedPosition,
                peopleAhead = it.peopleAhead ?: (resolvedPosition ?: 1).coerceAtLeast(1) - 1,
                estimatedWaitMinutes = it.estimatedWaitMinutes,
                joinedAt = it.joinedAt ?: joinedAt,
                calledAt = it.calledAt,
                servingSinceMinutes = it.servingSinceMinutes ?: 0,
                professionalName = it.professionalName,
                accessCode = accessCode
            )
        }
    )
}

private fun ServingQueueEntryDto.toUserEntryStatus(): UserEntryStatusDto {
    return UserEntryStatusDto(
        entryId = id,
        status = status,
        position = null,
        queuePosition = null,
        peopleAhead = 0,
        estimatedWaitMinutes = 0,
        joinedAt = createdAt,
        calledAt = calledAt,
        servingSinceMinutes = servingSinceMinutes,
        professionalName = professionalName
    )
}
