package br.dev.pulz.queuemaster.mobile.core.model

enum class AppThemeMode {
    System,
    Light,
    Dark;

    companion object {
        fun fromStorage(value: String?): AppThemeMode {
            return entries.firstOrNull { it.name.equals(value, ignoreCase = true) } ?: System
        }
    }
}
