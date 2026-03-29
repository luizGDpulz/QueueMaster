package br.dev.pulz.queuemaster.mobile

import android.app.Application
import br.dev.pulz.queuemaster.mobile.core.utils.AppRuntime

class QueueMasterMobileApplication : Application() {
    override fun onCreate() {
        super.onCreate()
        AppRuntime.initialize(this)
    }
}
