package br.dev.pulz.queuemaster.mobile.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable

private val DarkColorScheme = darkColorScheme(
    primary = Cloud0,
    onPrimary = Ink900,
    primaryContainer = Night700,
    onPrimaryContainer = Cloud0,
    secondary = Night300,
    onSecondary = Night900,
    tertiary = Mist300,
    onTertiary = Night900,
    background = Night950,
    onBackground = Cloud0,
    surface = Night900,
    onSurface = Cloud0,
    surfaceVariant = Night800,
    onSurfaceVariant = Night300,
    outline = Night700,
    outlineVariant = Night800,
    error = Error500
)

private val LightColorScheme = lightColorScheme(
    primary = Ink900,
    onPrimary = Cloud0,
    primaryContainer = Mist100,
    onPrimaryContainer = Ink900,
    secondary = Slate600,
    onSecondary = Cloud0,
    tertiary = Mist300,
    onTertiary = Ink900,
    background = Mist50,
    onBackground = Ink900,
    surface = Cloud0,
    onSurface = Ink900,
    surfaceVariant = Mist100,
    onSurfaceVariant = Slate600,
    outline = Mist200,
    outlineVariant = Mist300,
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
