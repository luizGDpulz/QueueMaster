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
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

object QueueBackgroundMonitor : DefaultLifecycleObserver {
    private const val PollIntervalMillis = 15_000L

    private val applicationScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val queueRepository = QueueRepository()

    private var pollingJob: Job? = null
    private var isAuthenticatedSessionReady = false
    private var isAppInForeground = true
    private var isQueueStatusScreenActive = false

    fun initialize(application: Application) {
        ProcessLifecycleOwner.get().lifecycle.removeObserver(this)
        ProcessLifecycleOwner.get().lifecycle.addObserver(this)

        applicationScope.launch {
            AppPreferencesStore.systemNotificationsEnabled.collectLatest {
                refreshPollingState()
            }
        }
    }

    override fun onStart(owner: LifecycleOwner) {
        isAppInForeground = true
        refreshPollingState()
    }

    override fun onStop(owner: LifecycleOwner) {
        isAppInForeground = false
        refreshPollingState()
    }

    fun setQueueStatusScreenActive(active: Boolean) {
        isQueueStatusScreenActive = active
        refreshPollingState()
    }

    fun setAuthenticatedSessionReady(isReady: Boolean) {
        isAuthenticatedSessionReady = isReady
        refreshPollingState()
    }

    fun notifyQueueSessionChanged() {
        refreshPollingState()
    }

    private fun refreshPollingState() {
        if (!shouldRunPolling()) {
            pollingJob?.cancel()
            pollingJob = null
            return
        }

        if (pollingJob?.isActive == true) return

        pollingJob = applicationScope.launch {
            while (isActive) {
                val session = QueueSessionStore.restore()
                if (session == null || !shouldRunPolling()) {
                    break
                }

                pollQueueState(session)
                delay(PollIntervalMillis)
            }

            pollingJob = null
        }
    }

    private fun shouldRunPolling(): Boolean {
        if (!isAuthenticatedSessionReady) {
            return false
        }

        if (!AppPreferencesStore.systemNotificationsEnabled.value) {
            return false
        }

        QueueSessionStore.restore() ?: return false

        return if (isAppInForeground) {
            !isQueueStatusScreenActive
        } else {
            BatteryOptimizationHelper.isIgnoringBatteryOptimizations(AppRuntime.context())
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
                    refreshPollingState()
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
                    refreshPollingState()
                }
            }
        )
    }
}
