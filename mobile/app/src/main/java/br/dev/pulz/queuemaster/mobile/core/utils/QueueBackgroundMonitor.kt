package br.dev.pulz.queuemaster.mobile.core.utils

import android.app.Application
import androidx.lifecycle.DefaultLifecycleObserver
import androidx.lifecycle.LifecycleOwner
import androidx.lifecycle.ProcessLifecycleOwner
import br.dev.pulz.queuemaster.mobile.core.network.ApiException
import br.dev.pulz.queuemaster.mobile.core.network.repository.QueueRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

object QueueBackgroundMonitor : DefaultLifecycleObserver {
    private const val PollIntervalMillis = 20_000L

    private val applicationScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val queueRepository = QueueRepository()

    private var pollingJob: Job? = null
    private var isAppInForeground = true

    fun initialize(application: Application) {
        ProcessLifecycleOwner.get().lifecycle.removeObserver(this)
        ProcessLifecycleOwner.get().lifecycle.addObserver(this)
    }

    override fun onStart(owner: LifecycleOwner) {
        isAppInForeground = true
        pollingJob?.cancel()
        pollingJob = null
    }

    override fun onStop(owner: LifecycleOwner) {
        isAppInForeground = false
        startPollingIfNeeded()
    }

    private fun startPollingIfNeeded() {
        if (pollingJob?.isActive == true) return

        pollingJob = applicationScope.launch {
            while (isActive && !isAppInForeground) {
                val session = QueueSessionStore.restore()
                if (session == null) {
                    pollingJob = null
                    return@launch
                }

                if (AppPreferencesStore.systemNotificationsEnabled.value) {
                    pollQueueState(session)
                }

                delay(PollIntervalMillis)
            }
        }
    }

    private suspend fun pollQueueState(session: PersistedQueueSession) {
        runCatching {
            queueRepository.getQueueStatus(
                queueId = session.queueId,
                authenticatedUserId = session.authenticatedUserId,
                joinedAt = session.joinedAt,
                accessCode = session.accessCode
            )
        }.fold(
            onSuccess = { queueStatus ->
                val previousEntry = session.toPreviousUserEntry()
                val currentEntry = queueStatus.userEntry

                if (currentEntry == null) {
                    if (previousEntry != null) {
                        QueueMasterNotificationManager.notifyQueueCompleted(
                            userId = session.authenticatedUserId,
                            queueId = session.queueId,
                            entryPublicId = session.entryPublicId ?: previousEntry.entryPublicId,
                            queueName = session.queueName
                        )
                    }
                    QueueSessionStore.clear()
                    return
                }

                QueueMasterNotificationManager.notifyQueueProgress(
                    userId = session.authenticatedUserId,
                    queueId = session.queueId,
                    currentEntry = currentEntry,
                    previousEntry = previousEntry,
                    queueName = queueStatus.queue.name
                )

                QueueSessionStore.save(
                    session.withSnapshot(
                        queueName = queueStatus.queue.name,
                        userEntry = currentEntry
                    )
                )
            },
            onFailure = { throwable ->
                if (throwable is ApiException && throwable.statusCode == 404) {
                    QueueSessionStore.clear()
                }
            }
        )
    }
}
