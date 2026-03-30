package br.dev.pulz.queuemaster.mobile.features.manualcode

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import br.dev.pulz.queuemaster.mobile.core.model.JoinQueueResult
import br.dev.pulz.queuemaster.mobile.core.network.ApiException
import br.dev.pulz.queuemaster.mobile.core.network.repository.QueueRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class ManualCodeEntryViewModel : ViewModel() {
    private val queueRepository = QueueRepository()
    private var lastAccessCode: String = ""

    private val _uiState = MutableStateFlow<ManualCodeUiState>(
        ManualCodeUiState.Form()
    )
    val uiState: StateFlow<ManualCodeUiState> = _uiState.asStateFlow()

    fun updateAccessCode(input: String) {
        val sanitized = input
            .uppercase()
            .filter { it.isLetterOrDigit() }
            .take(12)

        lastAccessCode = sanitized
        _uiState.value = ManualCodeUiState.Form(
            accessCode = sanitized
        )
    }

    fun submit() {
        val currentCode = currentAccessCode()
        lastAccessCode = currentCode

        if (!isValid(currentCode)) {
            _uiState.value = ManualCodeUiState.Error(
                message = "Digite um codigo valido com 6 a 12 caracteres.",
                attemptedCode = currentCode
            )
            return
        }

        viewModelScope.launch {
            _uiState.value = ManualCodeUiState.Loading
            _uiState.value = runCatching {
                queueRepository.joinQueue(
                    accessCode = currentCode
                )
            }.fold(
                onSuccess = { result ->
                    ManualCodeUiState.Success(
                        result = result
                    )
                },
                onFailure = { throwable ->
                    val apiError = throwable as? ApiException
                    val existingQueueResult = apiError?.toExistingActiveQueueResult()

                    existingQueueResult?.let { result ->
                        ManualCodeUiState.Success(result = result)
                    } ?: ManualCodeUiState.Error(
                        message = throwable.toManualCodeMessage(),
                        attemptedCode = currentCode
                    )
                }
            )
        }
    }

    fun reset() {
        lastAccessCode = ""
        _uiState.value = ManualCodeUiState.Form()
    }

    fun currentAccessCode(): String = when (val state = _uiState.value) {
        is ManualCodeUiState.Form -> state.accessCode
        is ManualCodeUiState.Error -> state.attemptedCode
        is ManualCodeUiState.Success -> state.result.accessCode.orEmpty()
        ManualCodeUiState.Loading -> lastAccessCode
    }

    private fun isValid(code: String): Boolean {
        val regex = Regex("^[A-Z0-9]{6,12}$")
        return regex.matches(code)
    }
}

private fun Map<String, Any?>.intValue(key: String): Int? {
    return when (val value = this[key]) {
        is Int -> value
        is Long -> value.toInt()
        is Double -> value.toInt()
        is Float -> value.toInt()
        is String -> value.toIntOrNull()
        else -> null
    }
}

private fun Map<String, Any?>.stringValue(key: String): String? {
    return (this[key] as? String)
        ?.trim()
        ?.takeIf { it.isNotBlank() }
}

private fun ApiException.toExistingActiveQueueResult(): JoinQueueResult? {
    if (code != "ALREADY_IN_QUEUE" && code != "ALREADY_IN_ACTIVE_QUEUE") {
        return null
    }

    val resolvedQueueId = details.intValue("queue_id") ?: return null
    return JoinQueueResult(
        queueId = resolvedQueueId,
        entryPublicId = details.stringValue("entry_public_id"),
        queueName = details.stringValue("queue_name"),
        entryStatus = details.stringValue("entry_status") ?: "waiting",
        accessCode = null,
        joinedSuccessfully = false
    )
}

private fun Throwable.toManualCodeMessage(): String {
    return when (this) {
        is ApiException -> {
            when (code) {
                "INVALID_CODE" -> "Esse codigo nao e valido ou ja expirou."
                "QUEUE_CLOSED" -> "A fila vinculada a esse codigo esta fechada."
                "NOT_FOUND" -> "Nao encontramos a fila vinculada a esse codigo."
                "ALREADY_IN_ACTIVE_QUEUE" -> "Voce ja possui uma fila ativa no momento."
                "HTTP_404" -> "A entrada manual por codigo ainda nao esta publicada no servidor."
                "HTTP_401" -> "Sua sessao expirou. Entre novamente para continuar."
                else -> message.ifBlank { "Nao foi possivel entrar na fila com esse codigo." }
            }
        }

        else -> message?.takeIf { it.isNotBlank() }
            ?: "Nao foi possivel entrar na fila com esse codigo."
    }
}
