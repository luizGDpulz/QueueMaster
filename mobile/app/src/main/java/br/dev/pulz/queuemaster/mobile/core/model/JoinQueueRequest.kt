package br.dev.pulz.queuemaster.mobile.core.model

data class JoinQueueRequest(
    val queueId: Int? = null,
    val accessCode: String? = null,
    val joinUrl: String? = null
)
