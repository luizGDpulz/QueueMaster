package br.dev.pulz.queuemaster.mobile.core.model

data class ResolvedQueueCode(
    val queueId: Int,
    val queueName: String? = null,
    val establishmentName: String? = null,
    val serviceName: String? = null,
    val accessCode: String
)
