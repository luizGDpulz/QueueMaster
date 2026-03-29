package br.dev.pulz.queuemaster.mobile.core.model

data class AuthenticatedUser(
    val id: Int,
    val name: String,
    val email: String,
    val avatarUrl: String? = null,
    val roles: List<String> = emptyList()
)
