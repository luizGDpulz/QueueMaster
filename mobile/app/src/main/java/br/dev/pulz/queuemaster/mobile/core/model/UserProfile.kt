package br.dev.pulz.queuemaster.mobile.core.model

data class UserProfile(
    val id: Int,
    val fullName: String,
    val email: String,
    val phoneNumber: String? = null,
    val avatarUrl: String? = null,
    val preferredLanguage: String = "pt-BR"
)
