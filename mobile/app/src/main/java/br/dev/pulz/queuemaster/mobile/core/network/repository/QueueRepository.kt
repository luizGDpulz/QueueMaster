package br.dev.pulz.queuemaster.mobile.core.network.repository

import br.dev.pulz.queuemaster.mobile.core.model.JoinQueueResult
import br.dev.pulz.queuemaster.mobile.core.model.QueueStatus
import br.dev.pulz.queuemaster.mobile.core.model.ResolvedQueueCode
import br.dev.pulz.queuemaster.mobile.core.network.ApiException
import br.dev.pulz.queuemaster.mobile.core.network.QueueMasterNetwork
import br.dev.pulz.queuemaster.mobile.core.network.api.QueueApiService
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.JoinQueueBodyDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.toJoinQueueResult
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.toQueueStatus
import br.dev.pulz.queuemaster.mobile.core.network.dto.queue.toResolvedQueueCode
import br.dev.pulz.queuemaster.mobile.core.network.safeApiCall

class QueueRepository(
    private val api: QueueApiService = QueueMasterNetwork.createService(QueueApiService::class.java)
) {
    suspend fun getCurrentActiveQueue(): JoinQueueResult? {
        return runCatching {
            safeApiCall {
                api.getCurrentActiveQueue()
            }.toJoinQueueResult()
        }.getOrElse { throwable ->
            if (throwable is ApiException && throwable.statusCode == 404) {
                null
            } else {
                throw throwable
            }
        }
    }

    suspend fun resolveQueueCode(
        accessCode: String
    ): ResolvedQueueCode {
        val payload = safeApiCall {
            api.resolveQueueCode(accessCode = accessCode)
        }

        return payload.toResolvedQueueCode()
    }

    suspend fun joinQueue(
        queueId: Int? = null,
        accessCode: String? = null
    ): JoinQueueResult {
        val payload = safeApiCall {
            if (!accessCode.isNullOrBlank()) {
                api.joinQueueByCode(
                    body = JoinQueueBodyDto(accessCode = accessCode)
                )
            } else {
                api.joinQueue(
                    queueId = requireNotNull(queueId) { "queueId is required when accessCode is not provided." },
                    body = JoinQueueBodyDto(accessCode = accessCode)
                )
            }
        }

        return payload.toJoinQueueResult(
            accessCode = accessCode
        )
    }

    suspend fun getQueueStatus(
        queueId: Int,
        authenticatedUserId: Int? = null,
        joinedAt: String? = null,
        accessCode: String? = null
    ): QueueStatus {
        val payload = safeApiCall {
            api.getQueueStatus(queueId)
        }

        return payload.toQueueStatus(
            authenticatedUserId = authenticatedUserId,
            joinedAt = joinedAt,
            accessCode = accessCode
        )
    }

    suspend fun leaveQueue(queueId: Int) {
        safeApiCall {
            api.leaveQueue(queueId)
        }
    }
}
