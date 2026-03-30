package br.dev.pulz.queuemaster.mobile.features.queuestatus

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import br.dev.pulz.queuemaster.mobile.core.model.JoinQueueResult
import br.dev.pulz.queuemaster.mobile.core.model.QueueStatus
import br.dev.pulz.queuemaster.mobile.core.network.ApiException
import br.dev.pulz.queuemaster.mobile.core.network.repository.QueueRepository
import br.dev.pulz.queuemaster.mobile.core.utils.PersistedQueueSession
import br.dev.pulz.queuemaster.mobile.core.utils.QueueMasterNotificationManager
import br.dev.pulz.queuemaster.mobile.core.utils.QueueSessionStore
import br.dev.pulz.queuemaster.mobile.core.utils.buildQueueNotificationFlowKey
import br.dev.pulz.queuemaster.mobile.core.utils.withSnapshot
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class QueueStatusViewModel : ViewModel() {
    private val queueRepository = QueueRepository()
    private var activeQueueSession: ActiveQueueSession? = null
    private var restoredUserId: Int? = null
    private var leaveInFlight = false

    private val _uiState = MutableStateFlow<QueueStatusUiState>(
        QueueStatusUiState.NoActiveQueue
    )
    val uiState: StateFlow<QueueStatusUiState> = _uiState.asStateFlow()

    private val _isRefreshing = MutableStateFlow(false)
    val isRefreshing: StateFlow<Boolean> = _isRefreshing.asStateFlow()

    private val _lastUpdatedAt = MutableStateFlow<Long?>(null)
    val lastUpdatedAt: StateFlow<Long?> = _lastUpdatedAt.asStateFlow()

    fun showJoinedQueue(result: JoinQueueResult, authenticatedUserId: Int) {
        val notificationFlowKey = buildQueueNotificationFlowKey(
            entryPublicId = result.entryPublicId,
            queueId = result.queueId,
            joinedAt = result.joinedAt
        )

        activeQueueSession = ActiveQueueSession(
            authenticatedUserId = authenticatedUserId,
            queueId = result.queueId,
            entryPublicId = result.entryPublicId,
            joinedAt = result.joinedAt,
            accessCode = result.accessCode,
            notificationFlowKey = notificationFlowKey
        )
        QueueSessionStore.save(
            PersistedQueueSession(
                authenticatedUserId = authenticatedUserId,
                queueId = result.queueId,
                entryPublicId = result.entryPublicId,
                notificationFlowKey = notificationFlowKey,
                joinedAt = result.joinedAt,
                accessCode = result.accessCode,
                queueName = result.queueName,
                lastEntryStatus = result.entryStatus
            )
        )
        if (result.joinedSuccessfully) {
            QueueMasterNotificationManager.notifyQueueJoined(
                userId = authenticatedUserId,
                queueId = result.queueId,
                entryPublicId = result.entryPublicId,
                queueName = result.queueName,
                flowKey = notificationFlowKey
            )
        }
        refresh(showLoading = true)
    }

    fun leaveQueue() {
        val session = activeQueueSession ?: run {
            _uiState.value = QueueStatusUiState.NoActiveQueue
            return
        }
        val activeQueueName = (_uiState.value as? QueueStatusUiState.Active)?.queueStatus?.queue?.name

        viewModelScope.launch {
            leaveInFlight = true
            _uiState.value = QueueStatusUiState.Loading
            _uiState.value = runCatching {
                queueRepository.leaveQueue(session.queueId)
            }.fold(
                onSuccess = {
                    QueueMasterNotificationManager.notifyQueueLeft(
                        userId = session.authenticatedUserId,
                        queueId = session.queueId,
                        entryPublicId = session.entryPublicId,
                        queueName = activeQueueName,
                        flowKey = session.notificationFlowKey
                    )
                    activeQueueSession = null
                    QueueSessionStore.clear()
                    leaveInFlight = false
                    QueueStatusUiState.NoActiveQueue
                },
                onFailure = { throwable ->
                    leaveInFlight = false
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
        val previousQueueStatus = (previousState as? QueueStatusUiState.Active)?.queueStatus

        viewModelScope.launch {
            if (showLoading || previousState !is QueueStatusUiState.Active) {
                _uiState.value = QueueStatusUiState.Loading
            } else {
                _isRefreshing.value = true
            }

            runCatching {
                queueRepository.getQueueStatus(
                    queueId = session.queueId,
                    authenticatedUserId = session.authenticatedUserId,
                    joinedAt = session.joinedAt,
                    accessCode = session.accessCode
                )
            }.fold(
                onSuccess = { queueStatus ->
                    if (queueStatus.userEntry == null) {
                        if (previousQueueStatus?.userEntry != null && !leaveInFlight) {
                            QueueMasterNotificationManager.notifyQueueCompleted(
                                userId = session.authenticatedUserId,
                                queueId = session.queueId,
                                entryPublicId = session.entryPublicId
                                    ?: previousQueueStatus.userEntry?.entryPublicId,
                                queueName = previousQueueStatus.queue.name,
                                flowKey = session.notificationFlowKey
                            )
                        }
                        activeQueueSession = null
                        QueueSessionStore.clear()
                        leaveInFlight = false
                        _uiState.value = QueueStatusUiState.NoActiveQueue
                    } else {
                        handleNotificationTransitions(
                            session = session,
                            previousQueueStatus = previousQueueStatus,
                            currentQueueStatus = queueStatus
                        )
                        val updatedSession = session.copy(
                            entryPublicId = queueStatus.userEntry.entryPublicId
                        )
                        activeQueueSession = updatedSession
                        QueueSessionStore.save(
                            PersistedQueueSession(
                                authenticatedUserId = session.authenticatedUserId,
                                queueId = session.queueId,
                                entryPublicId = queueStatus.userEntry.entryPublicId,
                                notificationFlowKey = updatedSession.notificationFlowKey,
                                joinedAt = session.joinedAt,
                                accessCode = session.accessCode
                            ).withSnapshot(
                                queueName = queueStatus.queue.name,
                                userEntry = queueStatus.userEntry
                            )
                        )
                        leaveInFlight = false
                        _lastUpdatedAt.value = System.currentTimeMillis()
                        _uiState.value = QueueStatusUiState.Active(queueStatus = queueStatus)
                    }
                },
                onFailure = { throwable ->
                    if (throwable is ApiException && throwable.statusCode == 404) {
                        activeQueueSession = null
                        QueueSessionStore.clear()
                        leaveInFlight = false
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

    suspend fun restoreOrFetchActiveSession(authenticatedUserId: Int): Boolean {
        if (restoredUserId == authenticatedUserId && activeQueueSession != null) {
            return true
        }

        restoredUserId = authenticatedUserId
        val persisted = QueueSessionStore.restoreForUser(authenticatedUserId)
        activeQueueSession = persisted?.toActiveQueueSession()

        val currentActiveQueue = try {
            queueRepository.getCurrentActiveQueue()
        } catch (throwable: Throwable) {
            if (persisted != null) {
                return true
            }
            throw throwable
        }

        if (currentActiveQueue == null) {
            activeQueueSession = null
            QueueSessionStore.clear()
            _uiState.value = QueueStatusUiState.NoActiveQueue
            return false
        }

        val notificationFlowKey = persisted?.notificationFlowKey?.takeIf {
            persisted.queueId == currentActiveQueue.queueId &&
                persisted.entryPublicId != null &&
                persisted.entryPublicId == currentActiveQueue.entryPublicId
        } ?: buildQueueNotificationFlowKey(
            entryPublicId = currentActiveQueue.entryPublicId,
            queueId = currentActiveQueue.queueId,
            joinedAt = currentActiveQueue.joinedAt
        )
        val restoredAccessCode = persisted?.accessCode?.takeIf {
            persisted.queueId == currentActiveQueue.queueId
        }

        activeQueueSession = ActiveQueueSession(
            authenticatedUserId = authenticatedUserId,
            queueId = currentActiveQueue.queueId,
            entryPublicId = currentActiveQueue.entryPublicId,
            joinedAt = currentActiveQueue.joinedAt,
            accessCode = restoredAccessCode,
            notificationFlowKey = notificationFlowKey
        )
        QueueSessionStore.save(
            PersistedQueueSession(
                authenticatedUserId = authenticatedUserId,
                queueId = currentActiveQueue.queueId,
                entryPublicId = currentActiveQueue.entryPublicId,
                notificationFlowKey = notificationFlowKey,
                joinedAt = currentActiveQueue.joinedAt,
                accessCode = restoredAccessCode,
                queueName = currentActiveQueue.queueName,
                lastEntryStatus = currentActiveQueue.entryStatus
            )
        )
        return true
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
    val entryPublicId: String? = null,
    val joinedAt: String? = null,
    val accessCode: String? = null,
    val notificationFlowKey: String
)

private fun PersistedQueueSession.toActiveQueueSession(): ActiveQueueSession {
    return ActiveQueueSession(
        authenticatedUserId = authenticatedUserId,
        queueId = queueId,
        entryPublicId = entryPublicId,
        joinedAt = joinedAt,
        accessCode = accessCode,
        notificationFlowKey = notificationFlowKey ?: buildQueueNotificationFlowKey(
            entryPublicId = entryPublicId,
            queueId = queueId,
            joinedAt = joinedAt
        )
    )
}

private fun QueueStatusViewModel.handleNotificationTransitions(
    session: ActiveQueueSession,
    previousQueueStatus: QueueStatus?,
    currentQueueStatus: QueueStatus
) {
    val currentUserEntry = currentQueueStatus.userEntry ?: return
    QueueMasterNotificationManager.notifyQueueProgress(
        userId = session.authenticatedUserId,
        queueId = session.queueId,
        currentEntry = currentUserEntry,
        previousEntry = previousQueueStatus?.userEntry,
        queueName = currentQueueStatus.queue.name,
        flowKey = session.notificationFlowKey
    )
}

private fun Throwable.toQueueStatusMessage(): String {
    return when (this) {
        is ApiException -> message.ifBlank { "Nao foi possivel atualizar os dados da fila." }
        else -> message?.takeIf { it.isNotBlank() }
            ?: "Nao foi possivel atualizar os dados da fila."
    }
}
