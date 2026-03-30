package br.dev.pulz.queuemaster.mobile.features.manualcode

import br.dev.pulz.queuemaster.mobile.core.model.JoinQueueResult

sealed interface ManualCodeUiState {
    data class Form(
        val accessCode: String = ""
    ) : ManualCodeUiState

    object Loading : ManualCodeUiState

    data class Success(
        val result: JoinQueueResult
    ) : ManualCodeUiState

    data class Error(
        val message: String,
        val attemptedCode: String
    ) : ManualCodeUiState
}
