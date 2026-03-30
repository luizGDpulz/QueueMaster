package br.dev.pulz.queuemaster.mobile.core.utils

import android.content.Context
import br.dev.pulz.queuemaster.mobile.core.model.AppNotificationGroup
import br.dev.pulz.queuemaster.mobile.core.model.AppNotificationItem
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

object AppNotificationStore {
    private const val PrefsName = "qm_mobile_notifications"
    private const val NotificationsKey = "notifications"

    private val gson = Gson()
    private val listType = object : TypeToken<List<AppNotificationItem>>() {}.type
    private val preferences by lazy {
        AppRuntime.context().getSharedPreferences(PrefsName, Context.MODE_PRIVATE)
    }

    private val _notifications = MutableStateFlow(loadNotifications())
    val notifications: StateFlow<List<AppNotificationItem>> = _notifications.asStateFlow()

    fun add(notification: AppNotificationItem) {
        val updated = listOf(notification) + _notifications.value
        persist(updated)
    }

    fun markAllRead(userId: Int) {
        val updated = _notifications.value.map { notification ->
            if (notification.userId == userId) {
                notification.copy(isRead = true)
            } else {
                notification
            }
        }
        persist(updated)
    }

    fun markGroupRead(userId: Int, contextKey: String) {
        val updated = _notifications.value.map { notification ->
            if (notification.userId == userId && notification.contextKey == contextKey) {
                notification.copy(isRead = true)
            } else {
                notification
            }
        }
        persist(updated)
    }

    fun groupsForUser(userId: Int): List<AppNotificationGroup> {
        return _notifications.value
            .filter { it.userId == userId }
            .groupBy { it.contextKey }
            .values
            .map { events ->
                val sortedEvents = events.sortedByDescending { it.createdAt }
                val lastEvent = sortedEvents.first()
                AppNotificationGroup(
                    contextKey = lastEvent.contextKey,
                    contextType = lastEvent.contextType,
                    contextTitle = lastEvent.contextTitle,
                    contextSubtitle = lastEvent.contextSubtitle,
                    queueId = lastEvent.queueId,
                    unreadCount = sortedEvents.count { !it.isRead },
                    lastEvent = lastEvent,
                    events = sortedEvents
                )
            }
            .sortedByDescending { it.lastEvent.createdAt }
    }

    fun clearForUser(userId: Int) {
        val updated = _notifications.value.filterNot { it.userId == userId }
        persist(updated)
    }

    private fun loadNotifications(): List<AppNotificationItem> {
        val rawJson = preferences.getString(NotificationsKey, null) ?: return emptyList()
        return runCatching {
            gson.fromJson<List<AppNotificationItem>>(rawJson, listType).orEmpty()
        }.getOrDefault(emptyList())
    }

    private fun persist(notifications: List<AppNotificationItem>) {
        preferences.edit()
            .putString(NotificationsKey, gson.toJson(notifications, listType))
            .apply()
        _notifications.value = notifications
    }
}
