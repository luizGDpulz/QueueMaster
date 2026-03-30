package br.dev.pulz.queuemaster.mobile.features.joinqueue

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import br.dev.pulz.queuemaster.mobile.core.model.JoinQueueResult
import br.dev.pulz.queuemaster.mobile.core.network.ApiException
import br.dev.pulz.queuemaster.mobile.core.network.repository.QueueRepository
import br.dev.pulz.queuemaster.mobile.core.utils.QueueJoinPayloadParser
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class JoinQueueViewModel : ViewModel() {
    private val queueRepository = QueueRepository()
    private val _uiState = MutableStateFlow<JoinQueueUiState>(JoinQueueUiState.Idle)
    val uiState: StateFlow<JoinQueueUiState> = _uiState.asStateFlow()

    fun showError(message: String) {
        _uiState.value = JoinQueueUiState.Error(message = message)
    }

    fun joinFromQrPayload(payload: String) {
        if (_uiState.value is JoinQueueUiState.Loading) return

        val parsedPayload = QueueJoinPayloadParser.parse(payload)
        if (parsedPayload == null) {
            _uiState.value = JoinQueueUiState.Error(
                message = "Não foi possível entender o QR code desta fila."
            )
            return
        }

        viewModelScope.launch {
            _uiState.value = JoinQueueUiState.Loading
            _uiState.value = runCatching {
                queueRepository.joinQueue(
                    queueId = parsedPayload.queueId,
                    accessCode = parsedPayload.accessCode
                )
            }.fold(
                onSuccess = { result ->
                    JoinQueueUiState.Success(result = result)
                },
                onFailure = { throwable ->
                    val apiError = throwable as? ApiException
                    val existingQueueResult = apiError?.toExistingActiveQueueResult(
                        fallbackQueueId = parsedPayload.queueId
                    )

                    existingQueueResult?.let { result ->
                        JoinQueueUiState.Success(result = result)
                    } ?: JoinQueueUiState.Error(
                        message = throwable.toJoinQueueMessage()
                    )
                }
            )
        }
    }

    fun reset() {
        _uiState.value = JoinQueueUiState.Idle
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

private fun ApiException.toExistingActiveQueueResult(
    fallbackQueueId: Int?
): JoinQueueResult? {
    if (code != "ALREADY_IN_QUEUE" && code != "ALREADY_IN_ACTIVE_QUEUE") {
        return null
    }

    val resolvedQueueId = details.intValue("queue_id") ?: fallbackQueueId ?: return null
    return JoinQueueResult(
        queueId = resolvedQueueId,
        entryPublicId = details.stringValue("entry_public_id"),
        queueName = details.stringValue("queue_name"),
        entryStatus = details.stringValue("entry_status") ?: "waiting",
        accessCode = null,
        joinedSuccessfully = false
    )
}

private fun Throwable.toJoinQueueMessage(): String {
    return when (this) {
        is ApiException -> {
            when (code) {
                "INVALID_CODE" -> "Esse QR code não e valido ou já expirou."
                "QUEUE_CLOSED" -> "Essa fila está fechada no momento."
                "NOT_FOUND" -> "Não encontramos a fila indicada por esse QR code."
                "ALREADY_IN_ACTIVE_QUEUE" -> "Você já possui uma fila ativa no momento."
                else -> message.ifBlank { "Não foi possível entrar na fila agora." }
            }
        }

        else -> message?.takeIf { it.isNotBlank() }
            ?: "Não foi possível entrar na fila agora."
    }
}
