package br.dev.pulz.queuemaster.mobile.core.network

import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import br.dev.pulz.queuemaster.mobile.core.network.dto.ApiEnvelopeDto
import br.dev.pulz.queuemaster.mobile.core.network.dto.ApiErrorEnvelopeDto
import retrofit2.Response

private val errorParser = Gson()

suspend fun <T> safeApiCall(
    request: suspend () -> Response<ApiEnvelopeDto<T>>
): T {
    val response = request()

    if (response.isSuccessful) {
        val body = response.body()
            ?: throw ApiException(
                statusCode = response.code(),
                code = "EMPTY_BODY",
                message = "A API respondeu sem corpo."
            )

        if (!body.success) {
            throw ApiException(
                statusCode = response.code(),
                code = "UNEXPECTED_ENVELOPE",
                message = "A API retornou um envelope invalido."
            )
        }

        return body.data
            ?: throw ApiException(
                statusCode = response.code(),
                code = "EMPTY_DATA",
                message = "A API respondeu sem o bloco data."
            )
    }

    val parsedError = response.errorBody()
        ?.string()
        ?.takeIf { it.isNotBlank() }
        ?.let { content ->
            runCatching {
                errorParser.fromJson(content, ApiErrorEnvelopeDto::class.java)
            }.getOrNull()
        }
        ?.error

    throw ApiException(
        statusCode = response.code(),
        code = parsedError?.code ?: "HTTP_${response.code()}",
        message = parsedError?.message ?: response.message().ifBlank { "Falha na chamada da API." },
        requestId = parsedError?.requestId,
        details = parsedError?.details ?: emptyMap()
    )
}
