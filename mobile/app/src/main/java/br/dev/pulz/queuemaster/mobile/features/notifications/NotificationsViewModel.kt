package br.dev.pulz.queuemaster.mobile.features.notifications

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import br.dev.pulz.queuemaster.mobile.core.model.AppNotificationGroup
import br.dev.pulz.queuemaster.mobile.core.model.AppNotificationItem
import br.dev.pulz.queuemaster.mobile.core.model.AppNotificationType
import br.dev.pulz.queuemaster.mobile.core.model.NotificationContextType
import br.dev.pulz.queuemaster.mobile.core.model.QueueEntryHistoryEvent
import br.dev.pulz.queuemaster.mobile.core.model.QueueEntryHistorySummary
import br.dev.pulz.queuemaster.mobile.core.model.QueueEntryHistoryTimeline
import br.dev.pulz.queuemaster.mobile.core.network.ApiException
import br.dev.pulz.queuemaster.mobile.core.network.repository.QueueEntryHistoryRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class NotificationsViewModel : ViewModel() {
    private val repository = QueueEntryHistoryRepository()
    private var activeUserId: Int? = null
    private var selectedContextKey: String? = null
    private var cachedGroups: List<AppNotificationGroup> = emptyList()

    private val _uiState = MutableStateFlow<NotificationsUiState>(NotificationsUiState.Loading)
    val uiState: StateFlow<NotificationsUiState> = _uiState.asStateFlow()

    private val _detailsUiState = MutableStateFlow<NotificationDetailsUiState>(NotificationDetailsUiState.Idle)
    val detailsUiState: StateFlow<NotificationDetailsUiState> = _detailsUiState.asStateFlow()

    fun showUser(userId: Int?) {
        activeUserId = userId
        if (userId == null) {
            selectedContextKey = null
            cachedGroups = emptyList()
            _uiState.value = NotificationsUiState.Empty
            _detailsUiState.value = NotificationDetailsUiState.Idle
            return
        }

        refresh()
    }

    fun refresh() {
        val userId = activeUserId ?: run {
            _uiState.value = NotificationsUiState.Empty
            return
        }

        viewModelScope.launch {
            if (cachedGroups.isEmpty()) {
                _uiState.value = NotificationsUiState.Loading
            }

            runCatching {
                repository.getHistory()
            }.fold(
                onSuccess = { history ->
                    cachedGroups = history
                        .map { it.toNotificationGroup(userId) }
                        .sortedByDescending { it.lastEvent.createdAt }

                    _uiState.value = if (cachedGroups.isEmpty()) {
                        NotificationsUiState.Empty
                    } else {
                        NotificationsUiState.Loaded(cachedGroups)
                    }

                    selectedContextKey?.let { contextKey ->
                        if (cachedGroups.any { it.contextKey == contextKey }) {
                            openGroup(contextKey)
                        } else {
                            _detailsUiState.value = NotificationDetailsUiState.Empty
                        }
                    }
                },
                onFailure = { throwable ->
                    _uiState.value = NotificationsUiState.Error(
                        message = throwable.toNotificationsMessage()
                    )
                }
            )
        }
    }

    fun openGroup(contextKey: String) {
        val userId = activeUserId ?: return
        selectedContextKey = contextKey

        viewModelScope.launch {
            _detailsUiState.value = NotificationDetailsUiState.Loading

            runCatching {
                repository.getEvents(contextKey)
            }.fold(
                onSuccess = { timeline ->
                    val group = timeline.toNotificationGroup(userId)
                    cachedGroups = (
                        cachedGroups.filterNot { it.contextKey == contextKey } + group
                        ).sortedByDescending { it.lastEvent.createdAt }

                    _uiState.value = if (cachedGroups.isEmpty()) {
                        NotificationsUiState.Empty
                    } else {
                        NotificationsUiState.Loaded(cachedGroups)
                    }
                    _detailsUiState.value = NotificationDetailsUiState.Loaded(group)
                },
                onFailure = { throwable ->
                    _detailsUiState.value = when {
                        throwable is ApiException && throwable.statusCode == 404 -> {
                            NotificationDetailsUiState.Empty
                        }

                        else -> {
                            NotificationDetailsUiState.Error(
                                message = throwable.toNotificationsMessage()
                            )
                        }
                    }
                }
            )
        }
    }

    fun clearSelectedGroup() {
        selectedContextKey = null
        _detailsUiState.value = NotificationDetailsUiState.Idle
    }
}

private fun QueueEntryHistorySummary.toNotificationGroup(userId: Int): AppNotificationGroup {
    val eventAt = lastEventAt ?: joinedAt ?: System.currentTimeMillis()
    val lastEvent = AppNotificationItem(
        id = "${entryPublicId}_${lastEventType}_$eventAt",
        userId = userId,
        type = lastEventType.toNotificationType(),
        contextType = NotificationContextType.QueueEntry,
        contextKey = entryPublicId,
        contextTitle = queueName,
        contextSubtitle = buildNotificationSubtitle(
            establishmentName = establishmentName,
            serviceName = serviceName
        ),
        queueId = null,
        title = lastEventType.toNotificationTitle(),
        body = lastEventType.toNotificationBody(
            queueName = queueName,
            professionalName = professionalName,
            payload = emptyMap()
        ),
        createdAt = eventAt,
        isRead = true
    )

    return AppNotificationGroup(
        contextKey = entryPublicId,
        contextType = NotificationContextType.QueueEntry,
        contextTitle = queueName,
        contextSubtitle = buildNotificationSubtitle(
            establishmentName = establishmentName,
            serviceName = serviceName
        ),
        queueId = null,
        unreadCount = 0,
        lastEvent = lastEvent,
        events = listOf(lastEvent),
        totalEvents = eventsCount.coerceAtLeast(1),
        isActiveFlow = isActive,
        canJoinAgain = canJoinAgain
    )
}

private fun QueueEntryHistoryTimeline.toNotificationGroup(userId: Int): AppNotificationGroup {
    val subtitle = buildNotificationSubtitle(
        establishmentName = entry.establishmentName,
        serviceName = entry.serviceName
    )
    val mappedEvents = events
        .mapIndexed { index, event ->
            event.toNotificationItem(
                userId = userId,
                contextKey = entry.publicId,
                queueName = entry.queueName,
                subtitle = subtitle,
                professionalName = entry.professionalName,
                fallbackOrder = index
            )
        }
        .sortedByDescending { it.createdAt }

    val lastEvent = mappedEvents.firstOrNull()
        ?: AppNotificationItem(
            id = "${entry.publicId}_empty",
            userId = userId,
            type = AppNotificationType.QueueUpdated,
            contextType = NotificationContextType.QueueEntry,
            contextKey = entry.publicId,
            contextTitle = entry.queueName,
            contextSubtitle = subtitle,
            queueId = null,
            title = "Fluxo da fila",
            body = "Nenhum evento foi registrado ainda para esta entrada.",
            createdAt = entry.joinedAt ?: System.currentTimeMillis(),
            isRead = true
        )

    return AppNotificationGroup(
        contextKey = entry.publicId,
        contextType = NotificationContextType.QueueEntry,
        contextTitle = entry.queueName,
        contextSubtitle = subtitle,
        queueId = null,
        unreadCount = 0,
        lastEvent = lastEvent,
        events = if (mappedEvents.isEmpty()) listOf(lastEvent) else mappedEvents,
        totalEvents = if (mappedEvents.isEmpty()) 1 else mappedEvents.size,
        isActiveFlow = entry.isActive,
        canJoinAgain = !entry.isActive && entry.queueStatus == "open"
    )
}

private fun QueueEntryHistoryEvent.toNotificationItem(
    userId: Int,
    contextKey: String,
    queueName: String,
    subtitle: String?,
    professionalName: String?,
    fallbackOrder: Int
): AppNotificationItem {
    val eventAt = occurredAt ?: (System.currentTimeMillis() + fallbackOrder)
    return AppNotificationItem(
        id = "${contextKey}_${type}_$eventAt",
        userId = userId,
        type = type.toNotificationType(),
        contextType = NotificationContextType.QueueEntry,
        contextKey = contextKey,
        contextTitle = queueName,
        contextSubtitle = subtitle,
        queueId = null,
        title = type.toNotificationTitle(),
        body = type.toNotificationBody(
            queueName = queueName,
            professionalName = professionalName,
            payload = payload
        ),
        createdAt = eventAt,
        isRead = true
    )
}

private fun buildNotificationSubtitle(
    establishmentName: String?,
    serviceName: String?
): String? {
    return listOfNotNull(establishmentName, serviceName)
        .takeIf { it.isNotEmpty() }
        ?.joinToString(" - ")
}

private fun String.toNotificationType(): AppNotificationType {
    return when (lowercase()) {
        "joined" -> AppNotificationType.QueueJoined
        "next_up" -> AppNotificationType.QueueNext
        "called" -> AppNotificationType.QueueCalled
        "serving_started" -> AppNotificationType.QueueServing
        "completed" -> AppNotificationType.QueueCompleted
        "left" -> AppNotificationType.QueueLeft
        "cancelled" -> AppNotificationType.QueueCancelled
        "no_show" -> AppNotificationType.QueueNoShow
        "requeued" -> AppNotificationType.QueueRequeued
        else -> AppNotificationType.QueueUpdated
    }
}

private fun String.toNotificationTitle(): String {
    return when (lowercase()) {
        "joined" -> "Entrada confirmada"
        "next_up" -> "Voce e o proximo"
        "called" -> "Voce foi chamado"
        "serving_started" -> "Atendimento iniciado"
        "completed" -> "Atendimento concluido"
        "left" -> "Saida da fila"
        "cancelled" -> "Entrada cancelada"
        "no_show" -> "Ausencia registrada"
        "requeued" -> "Retorno para a fila"
        else -> "Atualizacao da fila"
    }
}

private fun String.toNotificationBody(
    queueName: String,
    professionalName: String?,
    payload: Map<String, Any?>
): String {
    return when (lowercase()) {
        "joined" -> "Voce entrou na fila $queueName."
        "next_up" -> {
            val peopleAhead = (payload["people_ahead"] as? Number)?.toInt()
            if (peopleAhead != null && peopleAhead > 0) {
                "Falta pouco para o seu atendimento em $queueName. Pessoas a frente: $peopleAhead."
            } else {
                "Seu atendimento em $queueName esta proximo."
            }
        }

        "called" -> "Dirija-se ao atendimento em $queueName."
        "serving_started" -> professionalName?.let {
            "Seu atendimento em $queueName comecou com $it."
        } ?: "Seu atendimento em $queueName ja comecou."
        "completed" -> "O fluxo de atendimento em $queueName foi concluido."
        "left" -> "Sua participacao em $queueName foi encerrada a pedido do cliente."
        "cancelled" -> "A entrada desta fila foi cancelada por um membro da equipe."
        "no_show" -> "Sua entrada foi marcada como ausencia em $queueName."
        "requeued" -> "Sua entrada voltou para a fila em $queueName."
        else -> "O fluxo da sua fila em $queueName foi atualizado."
    }
}

private fun Throwable.toNotificationsMessage(): String {
    return when (this) {
        is ApiException -> message.ifBlank { "Nao foi possivel carregar o historico das filas." }
        else -> message?.takeIf { it.isNotBlank() }
            ?: "Nao foi possivel carregar o historico das filas."
    }
}
