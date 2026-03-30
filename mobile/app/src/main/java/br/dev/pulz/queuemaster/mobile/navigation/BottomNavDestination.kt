package br.dev.pulz.queuemaster.mobile.navigation

import androidx.annotation.StringRes
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ViewList
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.Settings
import androidx.compose.ui.graphics.vector.ImageVector
import br.dev.pulz.queuemaster.mobile.R

data class BottomNavDestination(
    val route: AppRoute,
    @param:StringRes val labelRes: Int,
    val icon: ImageVector
)

val bottomNavDestinations = listOf(
    BottomNavDestination(
        route = AppRoute.JoinQueue,
        labelRes = R.string.qm_route_join_queue,
        icon = Icons.Filled.ConfirmationNumber
    ),
    BottomNavDestination(
        route = AppRoute.QueueStatus,
        labelRes = R.string.qm_route_queue_status,
        icon = Icons.AutoMirrored.Filled.ViewList
    ),
    BottomNavDestination(
        route = AppRoute.Notifications,
        labelRes = R.string.qm_route_notifications,
        icon = Icons.Filled.Notifications
    ),
    BottomNavDestination(
        route = AppRoute.Settings,
        labelRes = R.string.qm_route_settings,
        icon = Icons.Filled.Settings
    )
)
