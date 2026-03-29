package br.dev.pulz.queuemaster.mobile.core.network.repository

import br.dev.pulz.queuemaster.mobile.core.model.AuthenticatedUser
import br.dev.pulz.queuemaster.mobile.core.model.UserProfile
import br.dev.pulz.queuemaster.mobile.core.network.QueueMasterNetwork
import br.dev.pulz.queuemaster.mobile.core.network.api.AuthApiService
import br.dev.pulz.queuemaster.mobile.core.network.dto.auth.AuthGoogleRequestDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.auth.toAuthenticatedUser
import br.dev.pulz.queuemaster.mobile.core.network.dto.auth.toUserProfile
import br.dev.pulz.queuemaster.mobile.core.network.safeApiCall

class AuthRepository(
    private val api: AuthApiService = QueueMasterNetwork.createService(AuthApiService::class.java)
) {
    suspend fun loginWithGoogle(idToken: String): AuthenticatedUser {
        val payload = safeApiCall {
            api.loginWithGoogle(
                body = AuthGoogleRequestDto(idToken = idToken)
            )
        }

        return payload.user.toAuthenticatedUser()
    }

    suspend fun refreshSession(): AuthenticatedUser {
        val payload = safeApiCall {
            api.refreshSession()
        }

        return payload.user.toAuthenticatedUser()
    }

    suspend fun getCurrentAuthenticatedUser(): AuthenticatedUser {
        val payload = safeApiCall {
            api.me()
        }

        return payload.user.toAuthenticatedUser()
    }

    suspend fun getCurrentUserProfile(): UserProfile {
        val payload = safeApiCall {
            api.me()
        }

        return payload.user.toUserProfile()
    }

    suspend fun logout() {
        runCatching {
            safeApiCall {
                api.logout()
            }
        }
        QueueMasterNetwork.clearSessionCookies()
    }
}
