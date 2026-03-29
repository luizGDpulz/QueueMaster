package br.dev.pulz.queuemaster.mobile

import androidx.compose.foundation.layout.padding
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import br.dev.pulz.queuemaster.mobile.navigation.AppNavHost
import br.dev.pulz.queuemaster.mobile.navigation.rememberQueueMasterAppState
import br.dev.pulz.queuemaster.mobile.ui.components.QmAppScaffold

@Composable
fun QueueMasterApp(
    pendingJoinPayload: String?,
    onJoinPayloadConsumed: () -> Unit,
    modifier: Modifier = Modifier
) {
    val appState = rememberQueueMasterAppState()

    QmAppScaffold(
        modifier = modifier,
        appState = appState,
    ) { innerPadding ->
        AppNavHost(
            navController = appState.navController,
            pendingJoinPayload = pendingJoinPayload,
            onJoinPayloadConsumed = onJoinPayloadConsumed,
            modifier = Modifier.padding(innerPadding),
        )
    }
}
