package br.dev.pulz.queuemaster.mobile.core.model

data class ManualCodePayload(
    val accessCode: String,
    val source: String = "manual_entry"
)
