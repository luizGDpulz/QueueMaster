package br.dev.pulz.queuemaster.mobile.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import br.dev.pulz.queuemaster.mobile.core.model.AppThemeMode

private val DarkColorScheme = darkColorScheme(
    primary = Cloud0,
    onPrimary = Ink900,
    primaryContainer = Cloud0.copy(alpha = 0.1f),
    onPrimaryContainer = Cloud0,
    secondary = Night300,
    onSecondary = Night900,
    tertiary = Info400,
    onTertiary = Night900,
    background = Night950,
    onBackground = Cloud0,
    surface = Night900,
    onSurface = Cloud0,
    surfaceVariant = Night800,
    onSurfaceVariant = Night300,
    outline = Night700,
    outlineVariant = Night800,
    error = Error400
)

private val LightColorScheme = lightColorScheme(
    primary = Ink800,
    onPrimary = Cloud0,
    primaryContainer = Ink800.copy(alpha = 0.08f),
    onPrimaryContainer = Ink900,
    secondary = Slate600,
    onSecondary = Cloud0,
    tertiary = Info500,
    onTertiary = Cloud0,
    background = Mist50,
    onBackground = Ink900,
    surface = Cloud0,
    onSurface = Ink900,
    surfaceVariant = Mist300,
    onSurfaceVariant = Slate600,
    outline = Mist200,
    outlineVariant = Mist200,
    error = Error500
)

@Composable
fun QueueMasterMobileTheme(
    darkTheme: Boolean = false,
    content: @Composable () -> Unit
) {
    val colorScheme = if (darkTheme) DarkColorScheme else LightColorScheme

    MaterialTheme(
        colorScheme = colorScheme,
        typography = Typography,
        shapes = QueueMasterShapes,
        content = content
    )
}

fun AppThemeMode.shouldUseDarkTheme(systemInDarkTheme: Boolean): Boolean {
    return when (this) {
        AppThemeMode.System -> systemInDarkTheme
        AppThemeMode.Light -> false
        AppThemeMode.Dark -> true
    }
}
