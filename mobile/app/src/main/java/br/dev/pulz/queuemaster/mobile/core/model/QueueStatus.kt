package br.dev.pulz.queuemaster.mobile.core.model

data class QueueStatus(
    val queue: QueueSummary,
    val statistics: QueueStatistics = QueueStatistics(),
    val userEntry: QueueUserEntry? = null
)
