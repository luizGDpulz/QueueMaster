package br.dev.pulz.queuemaster.mobile.core.utils

import android.content.Context
import br.dev.pulz.queuemaster.mobile.core.model.QueueUserEntry

data class PersistedQueueSession(
    val authenticatedUserId: Int,
    val queueId: Int,
    val entryPublicId: String? = null,
    val notificationFlowKey: String? = null,
    val joinedAt: String? = null,
    val accessCode: String? = null,
    val queueName: String? = null,
    val lastEntryStatus: String? = null,
    val lastPeopleAhead: Int? = null,
    val lastPosition: Int? = null,
    val lastEstimatedWaitMinutes: Int? = null,
    val lastCalledAt: String? = null,
    val lastServingSinceMinutes: Int? = null,
    val lastProfessionalName: String? = null
)

object QueueSessionStore {
    private const val PrefsName = "qm_mobile_queue_session"
    private const val UserIdKey = "authenticated_user_id"
    private const val QueueIdKey = "queue_id"
    private const val EntryPublicIdKey = "entry_public_id"
    private const val NotificationFlowKey = "notification_flow_key"
    private const val JoinedAtKey = "joined_at"
    private const val AccessCodeKey = "access_code"
    private const val QueueNameKey = "queue_name"
    private const val LastEntryStatusKey = "last_entry_status"
    private const val LastPeopleAheadKey = "last_people_ahead"
    private const val LastPositionKey = "last_position"
    private const val LastEstimatedWaitMinutesKey = "last_estimated_wait_minutes"
    private const val LastCalledAtKey = "last_called_at"
    private const val LastServingSinceMinutesKey = "last_serving_since_minutes"
    private const val LastProfessionalNameKey = "last_professional_name"

    private val preferences by lazy {
        AppRuntime.context().getSharedPreferences(PrefsName, Context.MODE_PRIVATE)
    }

    fun save(session: PersistedQueueSession) {
        preferences.edit()
            .putInt(UserIdKey, session.authenticatedUserId)
            .putInt(QueueIdKey, session.queueId)
            .putString(EntryPublicIdKey, session.entryPublicId)
            .putString(NotificationFlowKey, session.notificationFlowKey)
            .putString(JoinedAtKey, session.joinedAt)
            .putString(AccessCodeKey, session.accessCode)
            .putString(QueueNameKey, session.queueName)
            .putString(LastEntryStatusKey, session.lastEntryStatus)
            .putInt(LastPeopleAheadKey, session.lastPeopleAhead ?: -1)
            .putInt(LastPositionKey, session.lastPosition ?: -1)
            .putInt(LastEstimatedWaitMinutesKey, session.lastEstimatedWaitMinutes ?: -1)
            .putString(LastCalledAtKey, session.lastCalledAt)
            .putInt(LastServingSinceMinutesKey, session.lastServingSinceMinutes ?: -1)
            .putString(LastProfessionalNameKey, session.lastProfessionalName)
            .apply()
    }

    fun restore(): PersistedQueueSession? {
        val storedUserId = preferences.getInt(UserIdKey, -1)
        return restoreForUser(storedUserId.takeIf { it > 0 } ?: return null)
    }

    fun restoreForUser(userId: Int): PersistedQueueSession? {
        val storedUserId = preferences.getInt(UserIdKey, -1)
        val storedQueueId = preferences.getInt(QueueIdKey, -1)

        if (storedUserId != userId || storedQueueId <= 0) {
            return null
        }

        return PersistedQueueSession(
            authenticatedUserId = storedUserId,
            queueId = storedQueueId,
            entryPublicId = preferences.getString(EntryPublicIdKey, null),
            notificationFlowKey = preferences.getString(NotificationFlowKey, null),
            joinedAt = preferences.getString(JoinedAtKey, null),
            accessCode = preferences.getString(AccessCodeKey, null),
            queueName = preferences.getString(QueueNameKey, null),
            lastEntryStatus = preferences.getString(LastEntryStatusKey, null),
            lastPeopleAhead = preferences.getInt(LastPeopleAheadKey, -1).takeIf { it >= 0 },
            lastPosition = preferences.getInt(LastPositionKey, -1).takeIf { it >= 0 },
            lastEstimatedWaitMinutes = preferences.getInt(LastEstimatedWaitMinutesKey, -1).takeIf { it >= 0 },
            lastCalledAt = preferences.getString(LastCalledAtKey, null),
            lastServingSinceMinutes = preferences.getInt(LastServingSinceMinutesKey, -1).takeIf { it >= 0 },
            lastProfessionalName = preferences.getString(LastProfessionalNameKey, null)
        )
    }

    fun clear() {
        preferences.edit().clear().apply()
    }
}

fun PersistedQueueSession.withSnapshot(
    queueName: String?,
    userEntry: QueueUserEntry
): PersistedQueueSession {
    return copy(
        entryPublicId = userEntry.entryPublicId ?: entryPublicId,
        joinedAt = joinedAt ?: userEntry.joinedAt,
        queueName = queueName ?: this.queueName,
        lastEntryStatus = userEntry.status,
        lastPeopleAhead = userEntry.peopleAhead,
        lastPosition = userEntry.position,
        lastEstimatedWaitMinutes = userEntry.estimatedWaitMinutes,
        lastCalledAt = userEntry.calledAt,
        lastServingSinceMinutes = userEntry.servingSinceMinutes,
        lastProfessionalName = userEntry.professionalName
    )
}

fun PersistedQueueSession.toPreviousUserEntry(): QueueUserEntry? {
    val status = lastEntryStatus ?: return null

    return QueueUserEntry(
        entryPublicId = entryPublicId,
        status = status,
        position = lastPosition,
        peopleAhead = lastPeopleAhead ?: 0,
        estimatedWaitMinutes = lastEstimatedWaitMinutes,
        joinedAt = joinedAt,
        calledAt = lastCalledAt,
        servingSinceMinutes = lastServingSinceMinutes ?: 0,
        professionalName = lastProfessionalName,
        accessCode = accessCode
    )
}
