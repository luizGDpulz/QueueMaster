package br.dev.pulz.queuemaster.mobile.core.utils

import android.net.Uri
import br.dev.pulz.queuemaster.mobile.core.model.QueueJoinPayload

object QueueJoinPayloadParser {
    fun parse(rawValue: String): QueueJoinPayload? {
        val sanitized = rawValue.trim()
        if (sanitized.isBlank()) return null

        val plainAccessCode = sanitized
            .uppercase()
            .takeIf { Regex("^[A-Z0-9]{6,20}$").matches(it) }

        if (plainAccessCode != null) {
            return QueueJoinPayload(
                queueId = null,
                accessCode = plainAccessCode,
                rawValue = sanitized
            )
        }

        val uri = runCatching { Uri.parse(sanitized) }.getOrNull() ?: return null
        val queueId = uri.getQueryParameter("queue_id")
            ?.toIntOrNull()
            ?.takeIf { it > 0 }

        val accessCode = uri.getQueryParameter("access_code")
            ?.trim()
            ?.uppercase()
            ?.takeIf { it.isNotBlank() }

        if (queueId == null && accessCode == null) return null

        return QueueJoinPayload(
            queueId = queueId,
            accessCode = accessCode,
            rawValue = sanitized
        )
    }
}
