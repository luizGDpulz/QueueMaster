package br.dev.pulz.queuemaster.mobile.navigation

import androidx.annotation.StringRes
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ViewList
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material.icons.filled.Person
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
        route = AppRoute.Profile,
        labelRes = R.string.qm_route_profile,
        icon = Icons.Filled.Person
    )
)
