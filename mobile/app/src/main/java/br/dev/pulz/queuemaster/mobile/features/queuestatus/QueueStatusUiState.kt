package br.dev.pulz.queuemaster.mobile.features.queuestatus

import br.dev.pulz.queuemaster.mobile.core.model.QueueStatus

sealed interface QueueStatusUiState {
    object Loading : QueueStatusUiState

    data class Active(
        val queueStatus: QueueStatus
    ) : QueueStatusUiState

    object NoActiveQueue : QueueStatusUiState

    data class Error(
        val message: String
    ) : QueueStatusUiState
}
