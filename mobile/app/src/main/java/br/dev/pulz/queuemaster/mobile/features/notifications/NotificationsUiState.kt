package br.dev.pulz.queuemaster.mobile.features.notifications

import br.dev.pulz.queuemaster.mobile.core.model.AppNotificationGroup

sealed interface NotificationsUiState {
    data object Loading : NotificationsUiState
    data object Empty : NotificationsUiState
    data class Loaded(
        val groups: List<AppNotificationGroup>
    ) : NotificationsUiState
}
