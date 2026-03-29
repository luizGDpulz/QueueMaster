package br.dev.pulz.queuemaster.mobile.core.model

data class AppSession(
    val isAuthenticated: Boolean = false,
    val activeQueueId: Int? = null
)
