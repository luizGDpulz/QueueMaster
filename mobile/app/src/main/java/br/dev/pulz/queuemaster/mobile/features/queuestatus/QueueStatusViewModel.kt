package br.dev.pulz.queuemaster.mobile.features.queuestatus

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import br.dev.pulz.queuemaster.mobile.core.model.JoinQueueResult
import br.dev.pulz.queuemaster.mobile.core.network.ApiException
import br.dev.pulz.queuemaster.mobile.core.network.repository.QueueRepository
import br.dev.pulz.queuemaster.mobile.core.utils.PersistedQueueSession
import br.dev.pulz.queuemaster.mobile.core.utils.QueueSessionStore
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class QueueStatusViewModel : ViewModel() {
    private val queueRepository = QueueRepository()
    private var activeQueueSession: ActiveQueueSession? = null
    private var restoredUserId: Int? = null

    private val _uiState = MutableStateFlow<QueueStatusUiState>(
        QueueStatusUiState.NoActiveQueue
    )
    val uiState: StateFlow<QueueStatusUiState> = _uiState.asStateFlow()

    private val _isRefreshing = MutableStateFlow(false)
    val isRefreshing: StateFlow<Boolean> = _isRefreshing.asStateFlow()

    private val _lastUpdatedAt = MutableStateFlow<Long?>(null)
    val lastUpdatedAt: StateFlow<Long?> = _lastUpdatedAt.asStateFlow()

    fun showJoinedQueue(result: JoinQueueResult, authenticatedUserId: Int) {
        activeQueueSession = ActiveQueueSession(
            authenticatedUserId = authenticatedUserId,
            queueId = result.queueId,
            joinedAt = result.joinedAt,
            accessCode = result.accessCode
        )
        QueueSessionStore.save(
            PersistedQueueSession(
                authenticatedUserId = authenticatedUserId,
                queueId = result.queueId,
                joinedAt = result.joinedAt,
                accessCode = result.accessCode
            )
        )
        refresh(showLoading = true)
    }

    fun leaveQueue() {
        val session = activeQueueSession ?: run {
            _uiState.value = QueueStatusUiState.NoActiveQueue
            return
        }

        viewModelScope.launch {
            _uiState.value = QueueStatusUiState.Loading
            _uiState.value = runCatching {
                queueRepository.leaveQueue(session.queueId)
            }.fold(
                onSuccess = {
                    activeQueueSession = null
                    QueueSessionStore.clear()
                    QueueStatusUiState.NoActiveQueue
                },
                onFailure = { throwable ->
                    QueueStatusUiState.Error(
                        message = throwable.toQueueStatusMessage()
                    )
                }
            )
        }
    }

    fun refresh(showLoading: Boolean = true) {
        val session = activeQueueSession ?: run {
            _uiState.value = QueueStatusUiState.NoActiveQueue
            return
        }
        val previousState = _uiState.value

        viewModelScope.launch {
            if (showLoading || previousState !is QueueStatusUiState.Active) {
                _uiState.value = QueueStatusUiState.Loading
            } else {
                _isRefreshing.value = true
            }

            runCatching {
                queueRepository.getQueueStatus(
                    queueId = session.queueId,
                    joinedAt = session.joinedAt,
                    accessCode = session.accessCode
                )
            }.fold(
                onSuccess = { queueStatus ->
                    if (queueStatus.userEntry == null) {
                        activeQueueSession = null
                        QueueSessionStore.clear()
                        _uiState.value = QueueStatusUiState.NoActiveQueue
                    } else {
                        _lastUpdatedAt.value = System.currentTimeMillis()
                        _uiState.value = QueueStatusUiState.Active(queueStatus = queueStatus)
                    }
                },
                onFailure = { throwable ->
                    if (throwable is ApiException && throwable.statusCode == 404) {
                        activeQueueSession = null
                        QueueSessionStore.clear()
                        _uiState.value = QueueStatusUiState.NoActiveQueue
                    } else {
                        if (showLoading || previousState !is QueueStatusUiState.Active) {
                            _uiState.value = QueueStatusUiState.Error(
                                message = throwable.toQueueStatusMessage()
                            )
                        } else {
                            _uiState.value = previousState
                        }
                    }
                }
            )
            _isRefreshing.value = false
        }
    }

    fun hasActiveQueueSession(): Boolean = activeQueueSession != null

    fun restorePersistedSession(authenticatedUserId: Int) {
        if (restoredUserId == authenticatedUserId) return
        restoredUserId = authenticatedUserId

        val persisted = QueueSessionStore.restoreForUser(authenticatedUserId) ?: run {
            activeQueueSession = null
            return
        }

        activeQueueSession = ActiveQueueSession(
            authenticatedUserId = authenticatedUserId,
            queueId = persisted.queueId,
            joinedAt = persisted.joinedAt,
            accessCode = persisted.accessCode
        )
    }

    fun clear() {
        restoredUserId = null
        activeQueueSession = null
        QueueSessionStore.clear()
        _isRefreshing.value = false
        _lastUpdatedAt.value = null
        _uiState.value = QueueStatusUiState.NoActiveQueue
    }
}

private data class ActiveQueueSession(
    val authenticatedUserId: Int,
    val queueId: Int,
    val joinedAt: String? = null,
    val accessCode: String? = null
)

private fun Throwable.toQueueStatusMessage(): String {
    return when (this) {
        is ApiException -> message.ifBlank { "Nao foi possivel atualizar os dados da fila." }
        else -> message?.takeIf { it.isNotBlank() }
            ?: "Nao foi possivel atualizar os dados da fila."
    }
}
