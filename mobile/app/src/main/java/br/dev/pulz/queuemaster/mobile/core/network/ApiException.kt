package br.dev.pulz.queuemaster.mobile.core.network

import java.io.IOException

class ApiException(
    val statusCode: Int,
    val code: String,
    override val message: String,
    val requestId: String? = null,
    val details: Map<String, Any?> = emptyMap()
) : IOException(message)
