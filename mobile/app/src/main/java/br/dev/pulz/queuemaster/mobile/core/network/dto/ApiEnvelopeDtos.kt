package br.dev.pulz.queuemaster.mobile.core.network.dto

import com.google.gson.annotations.SerializedName

data class ApiEnvelopeDto<T>(
    val success: Boolean,
    val data: T?,
    val meta: Map<String, Any?>? = null
)

data class ApiErrorEnvelopeDto(
    val success: Boolean = false,
    val error: ApiErrorDto? = null
)

data class ApiErrorDto(
    val code: String,
    val message: String,
    @SerializedName("request_id")
    val requestId: String? = null,
    val details: Map<String, Any?>? = null
)
