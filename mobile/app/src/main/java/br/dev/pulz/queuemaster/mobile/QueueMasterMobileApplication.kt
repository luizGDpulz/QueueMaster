package br.dev.pulz.queuemaster.mobile

import android.app.Application
import br.dev.pulz.queuemaster.mobile.core.utils.AppRuntime
import br.dev.pulz.queuemaster.mobile.core.utils.QueueBackgroundMonitor
import br.dev.pulz.queuemaster.mobile.core.utils.QueueMasterNotificationManager

class QueueMasterMobileApplication : Application() {
    override fun onCreate() {
        super.onCreate()
        AppRuntime.initialize(this)
        QueueMasterNotificationManager.initialize(this)
        QueueBackgroundMonitor.initialize(this)
    }
}
