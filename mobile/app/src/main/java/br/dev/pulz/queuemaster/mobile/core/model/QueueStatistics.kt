package br.dev.pulz.queuemaster.mobile.core.model

data class QueueStatistics(
    val totalWaiting: Int = 0,
    val totalServing: Int = 0,
    val totalCompletedToday: Int = 0,
    val averageWaitTimeMinutes: Int? = null
)
