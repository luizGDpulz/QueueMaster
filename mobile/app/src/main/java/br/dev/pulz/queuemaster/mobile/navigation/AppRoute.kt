package br.dev.pulz.queuemaster.mobile.navigation

sealed class AppRoute(val route: String) {
    data object Login : AppRoute("login")
    data object JoinQueue : AppRoute("join_queue")
    data object QrScanner : AppRoute("qr_scanner")
    data object ManualCodeEntry : AppRoute("manual_code_entry")
    data object QueueStatus : AppRoute("queue_status")
    data object Notifications : AppRoute("notifications")
    data object NotificationDetails : AppRoute("notification_details/{contextKey}") {
        fun createRoute(contextKey: String): String = "notification_details/$contextKey"
    }
    data object Settings : AppRoute("settings")
    data object Profile : AppRoute("profile")

    companion object {
        val bottomBarRoutes = setOf(
            JoinQueue.route,
            QueueStatus.route,
            Notifications.route,
            Settings.route
        )
    }
}
