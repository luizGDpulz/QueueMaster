package br.dev.pulz.queuemaster.mobile.core.model

data class QueueSummary(
    val id: Int,
    val name: String,
    val establishmentName: String,
    val serviceName: String? = null,
    val isOpen: Boolean = true,
    val accessCodeRequired: Boolean = false
)
