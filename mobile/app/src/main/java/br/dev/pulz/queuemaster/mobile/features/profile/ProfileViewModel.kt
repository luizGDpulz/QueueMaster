package br.dev.pulz.queuemaster.mobile.features.profile

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import br.dev.pulz.queuemaster.mobile.core.model.AuthenticatedUser
import br.dev.pulz.queuemaster.mobile.core.model.UserProfile
import br.dev.pulz.queuemaster.mobile.core.network.repository.AuthRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class ProfileViewModel : ViewModel() {
    private val authRepository = AuthRepository()
    private val _uiState = MutableStateFlow<ProfileUiState>(ProfileUiState.Loading)
    val uiState: StateFlow<ProfileUiState> = _uiState.asStateFlow()

    fun showAuthenticatedUser(user: AuthenticatedUser) {
        _uiState.value = ProfileUiState.Loaded(
            profile = user.toUserProfile()
        )
    }

    fun refreshProfile() {
        viewModelScope.launch {
            val currentState = _uiState.value
            val refreshedProfile = runCatching {
                authRepository.getCurrentUserProfile()
            }.getOrNull() ?: run {
                if (currentState !is ProfileUiState.Loaded) {
                    _uiState.value = ProfileUiState.Error(
                        message = "Não foi possível carregar seus dados agora."
                    )
                }
                return@launch
            }

            _uiState.value = ProfileUiState.Loaded(
                profile = refreshedProfile
            )
        }
    }

    fun clear() {
        _uiState.value = ProfileUiState.Loading
    }
}

private fun AuthenticatedUser.toUserProfile(): UserProfile {
    return UserProfile(
        id = id,
        fullName = name,
        email = email,
        avatarUrl = avatarUrl
    )
}
