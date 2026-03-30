package br.dev.pulz.queuemaster.mobile.features.settings

import androidx.lifecycle.ViewModel
import br.dev.pulz.queuemaster.mobile.core.model.AppThemeMode
import br.dev.pulz.queuemaster.mobile.core.utils.AppPreferencesStore
import kotlinx.coroutines.flow.StateFlow

class SettingsViewModel : ViewModel() {
    val themeMode: StateFlow<AppThemeMode> = AppPreferencesStore.themeMode
    val systemNotificationsEnabled: StateFlow<Boolean> = AppPreferencesStore.systemNotificationsEnabled

    fun setThemeMode(mode: AppThemeMode) {
        AppPreferencesStore.setThemeMode(mode)
    }

    fun setSystemNotificationsEnabled(enabled: Boolean) {
        AppPreferencesStore.setSystemNotificationsEnabled(enabled)
    }
}
