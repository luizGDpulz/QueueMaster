package br.dev.pulz.queuemaster.mobile.core.model

enum class AppNotificationType {
    QueueJoined,
    QueueNext,
    QueueCalled,
    QueueServing,
    QueueCompleted,
    QueueLeft,
    QueueCancelled,
    QueueNoShow,
    QueueRequeued,
    QueueUpdated
}

enum class NotificationContextType {
    QueueEntry,
    Appointment
}

data class AppNotificationItem(
    val id: String,
    val userId: Int,
    val type: AppNotificationType,
    val contextType: NotificationContextType,
    val contextKey: String,
    val contextTitle: String,
    val contextSubtitle: String? = null,
    val queueId: Int? = null,
    val title: String,
    val body: String,
    val createdAt: Long,
    val isRead: Boolean = false
)

data class AppNotificationGroup(
    val contextKey: String,
    val contextType: NotificationContextType,
    val contextTitle: String,
    val contextSubtitle: String? = null,
    val queueId: Int? = null,
    val unreadCount: Int,
    val lastEvent: AppNotificationItem,
    val events: List<AppNotificationItem>,
    val totalEvents: Int = events.size,
    val isActiveFlow: Boolean = false,
    val canJoinAgain: Boolean = false
)
