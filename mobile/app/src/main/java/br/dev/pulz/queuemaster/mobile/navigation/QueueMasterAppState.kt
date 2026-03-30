package br.dev.pulz.queuemaster.mobile.navigation

import androidx.compose.runtime.Composable
import androidx.compose.runtime.Stable
import androidx.compose.runtime.remember
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.NavHostController
import androidx.navigation.compose.rememberNavController

@Stable
class QueueMasterAppState(
    val navController: NavHostController
) {
    fun navigate(route: AppRoute) {
        navController.navigate(route.route)
    }

    fun navigateToBottomDestination(route: AppRoute) {
        navController.navigate(route.route) {
            popUpTo(navController.graph.findStartDestination().id) {
                saveState = true
            }
            launchSingleTop = true
            restoreState = true
        }
    }

    fun popBackStack() {
        navController.popBackStack()
    }
}

@Composable
fun rememberQueueMasterAppState(
    navController: NavHostController = rememberNavController()
): QueueMasterAppState = remember(navController) {
    QueueMasterAppState(navController)
}
