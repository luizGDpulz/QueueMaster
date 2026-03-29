package br.dev.pulz.queuemaster.mobile.ui.components

import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.navigation.compose.currentBackStackEntryAsState
import br.dev.pulz.queuemaster.mobile.navigation.AppRoute
import br.dev.pulz.queuemaster.mobile.navigation.QueueMasterAppState

@Composable
fun QmAppScaffold(
    appState: QueueMasterAppState,
    modifier: Modifier = Modifier,
    content: @Composable (PaddingValues) -> Unit
) {
    val navBackStackEntry by appState.navController.currentBackStackEntryAsState()
    val currentRoute = navBackStackEntry?.destination?.route
    val showBottomBar = currentRoute in AppRoute.bottomBarRoutes

    Scaffold(
        modifier = modifier.fillMaxSize(),
        containerColor = MaterialTheme.colorScheme.background,
        bottomBar = {
            if (showBottomBar) {
                QmBottomBar(
                    currentRoute = currentRoute,
                    onDestinationClick = appState::navigateToBottomDestination
                )
            }
        },
        content = content
    )
}
