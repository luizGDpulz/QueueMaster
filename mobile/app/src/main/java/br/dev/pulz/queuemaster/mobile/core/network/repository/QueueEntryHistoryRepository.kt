package br.dev.pulz.queuemaster.mobile.core.network.repository

import br.dev.pulz.queuemaster.mobile.core.model.QueueEntryHistoryDetail
import br.dev.pulz.queuemaster.mobile.core.model.QueueEntryHistorySummary
import br.dev.pulz.queuemaster.mobile.core.model.QueueEntryHistoryTimeline
import br.dev.pulz.queuemaster.mobile.core.network.QueueMasterNetwork
import br.dev.pulz.queuemaster.mobile.core.network.api.QueueApiService
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.toQueueEntryHistoryDetail
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.toQueueEntryHistorySummary
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.toQueueEntryHistoryTimeline
import br.dev.pulz.queuemaster.mobile.core.network.safeApiCall

class QueueEntryHistoryRepository(
    private val api: QueueApiService = QueueMasterNetwork.createService(QueueApiService::class.java)
) {
    suspend fun getHistory(state: String? = null): List<QueueEntryHistorySummary> {
        val payload = safeApiCall {
            api.getQueueEntryHistory(state = state)
        }

        return payload.map { it.toQueueEntryHistorySummary() }
    }

    suspend fun getDetail(publicId: String): QueueEntryHistoryDetail {
        val payload = safeApiCall {
            api.getQueueEntry(publicId = publicId)
        }

        return payload.toQueueEntryHistoryDetail()
    }

    suspend fun getEvents(publicId: String): QueueEntryHistoryTimeline {
        val payload = safeApiCall {
            api.getQueueEntryEvents(publicId = publicId)
        }

        return payload.toQueueEntryHistoryTimeline()
    }
}
