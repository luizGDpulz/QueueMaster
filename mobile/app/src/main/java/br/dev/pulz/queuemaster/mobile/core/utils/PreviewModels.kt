package br.dev.pulz.queuemaster.mobile.core.utils

import br.dev.pulz.queuemaster.mobile.core.model.AuthenticatedUser
import br.dev.pulz.queuemaster.mobile.core.model.JoinQueueResult
import br.dev.pulz.queuemaster.mobile.core.model.QueueStatistics
import br.dev.pulz.queuemaster.mobile.core.model.QueueStatus
import br.dev.pulz.queuemaster.mobile.core.model.QueueSummary
import br.dev.pulz.queuemaster.mobile.core.model.QueueUserEntry
import br.dev.pulz.queuemaster.mobile.core.model.UserProfile

object PreviewModels {
    const val SampleQueueId = 1
    private const val SampleEntryId = 101

    val authenticatedUser = AuthenticatedUser(
        id = 7,
        name = PreviewData.SampleUserName,
        email = PreviewData.SampleUserEmail
    )

    val profile = UserProfile(
        id = 7,
        fullName = PreviewData.SampleUserName,
        email = PreviewData.SampleUserEmail,
        phoneNumber = PreviewData.SampleUserPhone,
        preferredLanguage = PreviewData.SampleProfileLanguage
    )

    val queueSummary = QueueSummary(
        id = SampleQueueId,
        name = PreviewData.SampleQueueName,
        establishmentName = PreviewData.SampleQueuePlace,
        serviceName = PreviewData.SampleServiceName,
        accessCodeRequired = true
    )

    fun joinResult(accessCode: String = PreviewData.SampleAccessCode) = JoinQueueResult(
        queueId = SampleQueueId,
        queueName = PreviewData.SampleQueueName,
        entryStatus = "waiting",
        accessCode = accessCode
    )

    fun queueStatus(accessCode: String = PreviewData.SampleAccessCode) = QueueStatus(
        queue = queueSummary,
        statistics = QueueStatistics(
            totalWaiting = 12,
            totalServing = 2,
            totalCompletedToday = 18,
            averageWaitTimeMinutes = 15
        ),
        userEntry = QueueUserEntry(
            entryId = SampleEntryId,
            status = "waiting",
            position = 12,
            peopleAhead = 11,
            estimatedWaitMinutes = 15,
            joinedAt = PreviewData.SampleJoinedAt,
            accessCode = accessCode
        )
    )
}
