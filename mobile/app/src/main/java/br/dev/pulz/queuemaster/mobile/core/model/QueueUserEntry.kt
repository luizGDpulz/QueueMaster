package br.dev.pulz.queuemaster.mobile.core.model

data class QueueUserEntry(
    val entryId: Int,
    val status: String,
    val position: Int? = null,
    val peopleAhead: Int = 0,
    val estimatedWaitMinutes: Int? = null,
    val joinedAt: String? = null,
    val accessCode: String? = null
)
