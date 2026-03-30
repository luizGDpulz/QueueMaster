package br.dev.pulz.queuemaster.mobile.core.utils

import android.content.Context

object AppRuntime {
    private lateinit var appContext: Context

    fun initialize(context: Context) {
        if (!::appContext.isInitialized) {
            appContext = context.applicationContext
        }
    }

    fun context(): Context {
        check(::appContext.isInitialized) { "AppRuntime was not initialized." }
        return appContext
    }
}
