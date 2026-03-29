package br.dev.pulz.queuemaster.mobile.core.network

import br.dev.pulz.queuemaster.mobile.BuildConfig

object NetworkConfig {
    val ApiBaseUrl: String = BuildConfig.API_BASE_URL.ensureSuffix("/")
    val SiteBaseUrl: String = ApiBaseUrl.substringBefore("/api/v1").ensureSuffix("/")
    const val DefaultTimeoutMillis: Long = 15_000
    const val RefreshEndpoint = "auth/refresh"
    const val GoogleWebClientId: String = BuildConfig.GOOGLE_WEB_CLIENT_ID

    fun resolveAbsoluteUrl(path: String?): String? {
        if (path.isNullOrBlank()) return null
        if (path.startsWith("http://") || path.startsWith("https://")) return path
        val normalizedPath = path.trimStart('/')
        return "${ApiBaseUrl}${normalizedPath}"
    }
}

private fun String.ensureSuffix(suffix: String): String {
    return if (endsWith(suffix)) this else this + suffix
}
