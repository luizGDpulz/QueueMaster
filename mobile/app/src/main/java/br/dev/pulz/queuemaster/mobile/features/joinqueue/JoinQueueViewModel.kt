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
                message = "Nao foi possivel entender o QR code desta fila."
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
                    if (throwable is ApiException && throwable.code == "ALREADY_IN_QUEUE") {
                        val queueId = throwable.details.intValue("queue_id") ?: parsedPayload.queueId
                        JoinQueueUiState.Success(
                            result = JoinQueueResult(
                                queueId = queueId ?: 0,
                                entryStatus = "waiting",
                                accessCode = parsedPayload.accessCode,
                                joinedSuccessfully = true
                            )
                        )
                    } else {
                        JoinQueueUiState.Error(
                            message = throwable.toJoinQueueMessage()
                        )
                    }
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

private fun Throwable.toJoinQueueMessage(): String {
    return when (this) {
        is ApiException -> {
            when (code) {
                "INVALID_CODE" -> "Esse QR code nao e valido ou ja expirou."
                "QUEUE_CLOSED" -> "Essa fila esta fechada no momento."
                "NOT_FOUND" -> "Nao encontramos a fila indicada por esse QR code."
                else -> message.ifBlank { "Nao foi possivel entrar na fila agora." }
            }
        }

        else -> message?.takeIf { it.isNotBlank() }
            ?: "Nao foi possivel entrar na fila agora."
    }
}
