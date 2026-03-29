package br.dev.pulz.queuemaster.mobile.core.model

data class QueueJoinPayload(
    val queueId: Int? = null,
    val accessCode: String? = null,
    val rawValue: String
)
