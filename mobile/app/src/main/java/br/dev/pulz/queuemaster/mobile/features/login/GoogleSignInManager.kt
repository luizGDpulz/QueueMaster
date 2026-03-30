package br.dev.pulz.queuemaster.mobile.features.login

import android.app.Activity
import android.content.Context
import android.util.Log
import android.util.Base64
import androidx.credentials.ClearCredentialStateRequest
import androidx.credentials.CredentialManager
import androidx.credentials.CustomCredential
import androidx.credentials.GetCredentialRequest
import androidx.credentials.exceptions.GetCredentialException
import androidx.credentials.exceptions.GetCredentialCancellationException
import br.dev.pulz.queuemaster.mobile.BuildConfig
import br.dev.pulz.queuemaster.mobile.core.network.NetworkConfig
import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import com.google.android.libraries.identity.googleid.GoogleIdTokenParsingException
import java.security.SecureRandom

class GoogleSignInManager(
    context: Context
) {
    private val credentialManager = CredentialManager.create(context)

    suspend fun requestIdToken(
        activity: Activity
    ): GoogleSignInResult {
        return try {
            val request = GetCredentialRequest.Builder()
                .addCredentialOption(
                    GetSignInWithGoogleOption.Builder(
                        serverClientId = NetworkConfig.GoogleWebClientId
                    )
                        .setNonce(generateSecureRandomNonce())
                        .build()
                )
                .build()

            val result = credentialManager.getCredential(
                context = activity,
                request = request
            )
            extractIdToken(result.credential)
        } catch (exception: Throwable) {
            exception.toGoogleSignInResult()
        }
    }

    private fun extractIdToken(
        credential: androidx.credentials.Credential
    ): GoogleSignInResult {
        if (credential !is CustomCredential ||
            (
                credential.type != GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL &&
                    credential.type != GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_SIWG_CREDENTIAL
                )
        ) {
            Log.w(TAG, "Unexpected credential type returned by Credential Manager: ${credential.type}")
            return GoogleSignInResult.Error(
                message = "Não foi possível identificar a conta Google selecionada."
            )
        }

        val googleIdTokenCredential = GoogleIdTokenCredential.createFrom(credential.data)
        return GoogleSignInResult.Success(
            idToken = googleIdTokenCredential.idToken
        )
    }

    suspend fun clearCredentialState() {
        runCatching {
            credentialManager.clearCredentialState(
                request = ClearCredentialStateRequest()
            )
        }
    }

    private fun generateSecureRandomNonce(
        byteLength: Int = 32
    ): String {
        val randomBytes = ByteArray(byteLength)
        SecureRandom().nextBytes(randomBytes)
        return Base64.encodeToString(
            randomBytes,
            Base64.NO_WRAP or Base64.URL_SAFE or Base64.NO_PADDING
        )
    }

    private fun Throwable.toGoogleSignInResult(): GoogleSignInResult {
        return when (this) {
            is GetCredentialCancellationException -> {
                Log.w(TAG, "Google sign-in was cancelled or dismissed", this)
                GoogleSignInResult.Error(
                    message = debugMessage(
                        "O login com Google foi fechado antes da conclusao."
                    )
                )
            }

            is GoogleIdTokenParsingException -> {
                Log.e(TAG, "Received invalid Google ID token payload", this)
                GoogleSignInResult.Error(
                    message = debugMessage(
                        "Recebemos um retorno invalido do Google. Tente novamente."
                    )
                )
            }

            is GetCredentialException -> {
                Log.e(TAG, "Credential Manager failed during Google sign-in", this)
                GoogleSignInResult.Error(
                    message = debugMessage(
                        message
                            ?.takeIf { it.isNotBlank() }
                            ?: "Não foi possível abrir o login do Google agora."
                    )
                )
            }

            else -> {
                Log.e(TAG, "Unexpected Google sign-in failure", this)
                GoogleSignInResult.Error(
                    message = debugMessage(
                        message
                            ?.takeIf { it.isNotBlank() }
                            ?: "Não foi possível entrar com Google agora."
                    )
                )
            }
        }
    }

    private fun Throwable.debugMessage(
        baseMessage: String
    ): String {
        if (!BuildConfig.DEBUG) return baseMessage

        val detail = buildString {
            append(javaClass.simpleName)
            message?.takeIf { it.isNotBlank() }?.let {
                append(": ")
                append(it)
            }
        }

        return "$baseMessage [$detail]"
    }

    private companion object {
        const val TAG = "GoogleSignInManager"
    }
}

sealed interface GoogleSignInResult {
    data class Success(
        val idToken: String
    ) : GoogleSignInResult

    data object Cancelled : GoogleSignInResult

    data class Error(
        val message: String
    ) : GoogleSignInResult
}
