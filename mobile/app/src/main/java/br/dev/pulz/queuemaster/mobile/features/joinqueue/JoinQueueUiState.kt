package br.dev.pulz.queuemaster.mobile.features.joinqueue

import br.dev.pulz.queuemaster.mobile.core.model.JoinQueueResult

sealed interface JoinQueueUiState {
    object Idle : JoinQueueUiState

    object Loading : JoinQueueUiState

    data class Success(
        val result: JoinQueueResult
    ) : JoinQueueUiState

    data class Error(
        val message: String
    ) : JoinQueueUiState
}
