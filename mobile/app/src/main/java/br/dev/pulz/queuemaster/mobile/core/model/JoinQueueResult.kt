package br.dev.pulz.queuemaster.mobile.core.model

data class JoinQueueResult(
    val queueId: Int,
    val entryId: Int? = null,
    val queueName: String? = null,
    val entryStatus: String,
    val joinedAt: String? = null,
    val accessCode: String? = null,
    val joinedSuccessfully: Boolean = true
)
