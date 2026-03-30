package br.dev.pulz.queuemaster.mobile.core.network

import android.content.Context
import android.content.SharedPreferences
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import okhttp3.Cookie
import okhttp3.CookieJar
import okhttp3.HttpUrl

class PersistentCookieJar(
    context: Context
) : CookieJar {
    private val preferences: SharedPreferences =
        context.getSharedPreferences(PrefsName, Context.MODE_PRIVATE)
    private val gson = Gson()
    private val lock = Any()
    private val cookies = linkedMapOf<String, Cookie>()

    init {
        restoreCookies()
    }

    override fun saveFromResponse(url: HttpUrl, cookies: List<Cookie>) {
        synchronized(lock) {
            val now = System.currentTimeMillis()
            cookies.forEach { cookie ->
                val key = cookie.key()
                if (cookie.expiresAt <= now) {
                    this.cookies.remove(key)
                } else {
                    this.cookies[key] = cookie
                }
            }
            persistCookies()
        }
    }

    override fun loadForRequest(url: HttpUrl): List<Cookie> {
        synchronized(lock) {
            pruneExpiredCookies()
            return cookies.values.filter { it.matches(url) }
        }
    }

    fun clear() {
        synchronized(lock) {
            cookies.clear()
            preferences.edit().remove(CookiesKey).apply()
        }
    }

    private fun restoreCookies() {
        synchronized(lock) {
            val raw = preferences.getString(CookiesKey, null) ?: return
            val type = object : TypeToken<List<PersistedCookie>>() {}.type
            val persistedCookies = runCatching {
                gson.fromJson<List<PersistedCookie>>(raw, type)
            }.getOrNull().orEmpty()

            val now = System.currentTimeMillis()
            persistedCookies
                .mapNotNull { it.toCookie() }
                .filter { it.expiresAt > now }
                .forEach { cookie ->
                    cookies[cookie.key()] = cookie
                }
        }
    }

    private fun persistCookies() {
        pruneExpiredCookies()
        val payload = cookies.values
            .map { PersistedCookie.from(it) }

        preferences.edit()
            .putString(CookiesKey, gson.toJson(payload))
            .apply()
    }

    private fun pruneExpiredCookies() {
        val now = System.currentTimeMillis()
        cookies.entries.removeAll { (_, cookie) -> cookie.expiresAt <= now }
    }

    private fun Cookie.key(): String {
        return listOf(name, domain, path, secure, httpOnly, hostOnly).joinToString("|")
    }

    private data class PersistedCookie(
        val name: String,
        val value: String,
        val expiresAt: Long,
        val domain: String,
        val path: String,
        val secure: Boolean,
        val httpOnly: Boolean,
        val hostOnly: Boolean,
        val persistent: Boolean
    ) {
        fun toCookie(): Cookie? {
            return runCatching {
                Cookie.Builder()
                    .name(name)
                    .value(value)
                    .apply {
                        if (hostOnly) {
                            hostOnlyDomain(domain)
                        } else {
                            domain(domain)
                        }
                        path(path)
                        if (secure) secure()
                        if (httpOnly) httpOnly()
                        if (persistent) expiresAt(expiresAt)
                    }
                    .build()
            }.getOrNull()
        }

        companion object {
            fun from(cookie: Cookie): PersistedCookie {
                return PersistedCookie(
                    name = cookie.name,
                    value = cookie.value,
                    expiresAt = cookie.expiresAt,
                    domain = cookie.domain,
                    path = cookie.path,
                    secure = cookie.secure,
                    httpOnly = cookie.httpOnly,
                    hostOnly = cookie.hostOnly,
                    persistent = cookie.persistent
                )
            }
        }
    }

    private companion object {
        const val PrefsName = "qm_mobile_cookies"
        const val CookiesKey = "cookies"
    }
}
