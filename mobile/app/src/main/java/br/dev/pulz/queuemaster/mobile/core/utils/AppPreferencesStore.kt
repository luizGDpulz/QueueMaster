package br.dev.pulz.queuemaster.mobile.core.utils

import android.content.Context
import br.dev.pulz.queuemaster.mobile.core.model.AppThemeMode
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

object AppPreferencesStore {
    private const val PrefsName = "qm_mobile_preferences"
    private const val ThemeModeKey = "theme_mode"
    private const val SystemNotificationsEnabledKey = "system_notifications_enabled"
    private const val NotificationsPromptHandledKey = "notifications_prompt_handled"

    private val preferences by lazy {
        AppRuntime.context().getSharedPreferences(PrefsName, Context.MODE_PRIVATE)
    }

    private val _themeMode = MutableStateFlow(
        AppThemeMode.fromStorage(preferences.getString(ThemeModeKey, AppThemeMode.System.name))
    )
    val themeMode: StateFlow<AppThemeMode> = _themeMode.asStateFlow()

    private val _systemNotificationsEnabled = MutableStateFlow(
        preferences.getBoolean(SystemNotificationsEnabledKey, true)
    )
    val systemNotificationsEnabled: StateFlow<Boolean> = _systemNotificationsEnabled.asStateFlow()

    private val _notificationsPromptHandled = MutableStateFlow(
        preferences.getBoolean(NotificationsPromptHandledKey, false)
    )
    val notificationsPromptHandled: StateFlow<Boolean> = _notificationsPromptHandled.asStateFlow()

    fun setThemeMode(mode: AppThemeMode) {
        preferences.edit()
            .putString(ThemeModeKey, mode.name)
            .apply()
        _themeMode.value = mode
    }

    fun setSystemNotificationsEnabled(enabled: Boolean) {
        preferences.edit()
            .putBoolean(SystemNotificationsEnabledKey, enabled)
            .apply()
        _systemNotificationsEnabled.value = enabled
    }

    fun setNotificationsPromptHandled(handled: Boolean) {
        preferences.edit()
            .putBoolean(NotificationsPromptHandledKey, handled)
            .apply()
        _notificationsPromptHandled.value = handled
    }
}
