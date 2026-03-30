package br.dev.pulz.queuemaster.mobile.features.profile

import br.dev.pulz.queuemaster.mobile.core.model.UserProfile

sealed interface ProfileUiState {
    object Loading : ProfileUiState

    data class Loaded(
        val profile: UserProfile
    ) : ProfileUiState

    data class Error(
        val message: String
    ) : ProfileUiState
}
