package br.dev.pulz.queuemaster.mobile.features.login

import br.dev.pulz.queuemaster.mobile.core.model.AuthenticatedUser

sealed interface LoginUiState {
    object Idle : LoginUiState

    object Loading : LoginUiState

    data class Authenticated(
        val user: AuthenticatedUser
    ) : LoginUiState

    data class Error(
        val message: String
    ) : LoginUiState
}
