package br.dev.pulz.queuemaster.mobile.features.notifications

import br.dev.pulz.queuemaster.mobile.core.model.AppNotificationGroup

sealed interface NotificationsUiState {
    data object Loading : NotificationsUiState
    data class Error(val message: String) : NotificationsUiState
    data object Empty : NotificationsUiState
    data class Loaded(
        val groups: List<AppNotificationGroup>
    ) : NotificationsUiState
}

sealed interface NotificationDetailsUiState {
    data object Idle : NotificationDetailsUiState
    data object Loading : NotificationDetailsUiState
    data class Error(val message: String) : NotificationDetailsUiState
    data object Empty : NotificationDetailsUiState
    data class Loaded(
        val group: AppNotificationGroup
    ) : NotificationDetailsUiState
}
