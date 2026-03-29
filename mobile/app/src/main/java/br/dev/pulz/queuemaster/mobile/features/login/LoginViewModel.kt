package br.dev.pulz.queuemaster.mobile.features.login

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import br.dev.pulz.queuemaster.mobile.core.model.AuthenticatedUser
import br.dev.pulz.queuemaster.mobile.core.network.ApiException
import br.dev.pulz.queuemaster.mobile.core.network.repository.AuthRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class LoginViewModel : ViewModel() {
    private val authRepository = AuthRepository()
    private val _uiState = MutableStateFlow<LoginUiState>(LoginUiState.Idle)
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

    init {
        restoreSession()
    }

    fun submitGoogleIdToken(idToken: String) {
        if (_uiState.value is LoginUiState.Loading) return

        viewModelScope.launch {
            _uiState.value = LoginUiState.Loading
            _uiState.value = runCatching {
                authRepository.loginWithGoogle(idToken = idToken)
            }.fold(
                onSuccess = { user ->
                    LoginUiState.Authenticated(user = user)
                },
                onFailure = { throwable ->
                    LoginUiState.Error(
                        message = throwable.toLoginMessage()
                    )
                }
            )
        }
    }

    fun onGoogleSignInError(message: String) {
        _uiState.value = LoginUiState.Error(message = message)
    }

    fun signOut() {
        _uiState.value = LoginUiState.Idle

        viewModelScope.launch {
            runCatching {
                authRepository.logout()
            }
        }
    }

    private fun restoreSession() {
        viewModelScope.launch {
            val user = runCatching {
                authRepository.getCurrentAuthenticatedUser()
            }.getOrNull() ?: return@launch

            _uiState.value = LoginUiState.Authenticated(
                user = user
            )
        }
    }
}

private fun Throwable.toLoginMessage(): String {
    return when (this) {
        is ApiException -> {
            when (statusCode) {
                401 -> "Sua autenticacao com o Google nao foi aceita. Tente novamente."
                403 -> message.ifBlank { "Sua conta nao tem acesso liberado no momento." }
                422 -> "Nao foi possivel validar o login enviado pelo Google."
                else -> message.ifBlank { "Nao foi possivel entrar agora. Tente novamente." }
            }
        }

        else -> message?.takeIf { it.isNotBlank() }
            ?: "Nao foi possivel entrar agora. Verifique sua conexao e tente novamente."
    }
}
