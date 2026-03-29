package br.dev.pulz.queuemaster.mobile.core.utils

import android.content.Context

data class PersistedQueueSession(
    val authenticatedUserId: Int,
    val queueId: Int,
    val joinedAt: String? = null,
    val accessCode: String? = null
)

object QueueSessionStore {
    private const val PrefsName = "qm_mobile_queue_session"
    private const val UserIdKey = "authenticated_user_id"
    private const val QueueIdKey = "queue_id"
    private const val JoinedAtKey = "joined_at"
    private const val AccessCodeKey = "access_code"

    private val preferences by lazy {
        AppRuntime.context().getSharedPreferences(PrefsName, Context.MODE_PRIVATE)
    }

    fun save(session: PersistedQueueSession) {
        preferences.edit()
            .putInt(UserIdKey, session.authenticatedUserId)
            .putInt(QueueIdKey, session.queueId)
            .putString(JoinedAtKey, session.joinedAt)
            .putString(AccessCodeKey, session.accessCode)
            .apply()
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
            joinedAt = preferences.getString(JoinedAtKey, null),
            accessCode = preferences.getString(AccessCodeKey, null)
        )
    }

    fun clear() {
        preferences.edit().clear().apply()
    }
}
