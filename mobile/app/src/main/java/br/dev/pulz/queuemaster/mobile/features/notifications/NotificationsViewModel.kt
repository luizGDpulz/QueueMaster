package br.dev.pulz.queuemaster.mobile.features.notifications

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import br.dev.pulz.queuemaster.mobile.core.model.AppNotificationGroup
import br.dev.pulz.queuemaster.mobile.core.utils.AppNotificationStore
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.stateIn

class NotificationsViewModel : ViewModel() {
    private val currentUserId = MutableStateFlow<Int?>(null)

    val uiState: StateFlow<NotificationsUiState> = combine(
        currentUserId,
        AppNotificationStore.notifications
    ) { userId, _ ->
        if (userId == null) {
            NotificationsUiState.Empty
        } else {
            val groups = AppNotificationStore.groupsForUser(userId)
            if (groups.isEmpty()) {
                NotificationsUiState.Empty
            } else {
                NotificationsUiState.Loaded(groups)
            }
        }
    }.stateIn(
        scope = viewModelScope,
        started = SharingStarted.WhileSubscribed(5_000),
        initialValue = NotificationsUiState.Loading
    )

    val unreadCount: StateFlow<Int> = combine(
        currentUserId,
        AppNotificationStore.notifications
    ) { userId, notifications ->
        notifications.count { notification ->
            userId != null && notification.userId == userId && !notification.isRead
        }
    }.stateIn(
        scope = viewModelScope,
        started = SharingStarted.WhileSubscribed(5_000),
        initialValue = 0
    )

    fun showUser(userId: Int?) {
        currentUserId.value = userId
    }

    fun markAllRead() {
        val userId = currentUserId.value ?: return
        AppNotificationStore.markAllRead(userId)
    }

    fun markGroupRead(contextKey: String) {
        val userId = currentUserId.value ?: return
        AppNotificationStore.markGroupRead(userId, contextKey)
    }

    fun groupForContext(contextKey: String): AppNotificationGroup? {
        val userId = currentUserId.value ?: return null
        return AppNotificationStore.groupsForUser(userId)
            .firstOrNull { it.contextKey == contextKey }
    }
}
