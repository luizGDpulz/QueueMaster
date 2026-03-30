package br.dev.pulz.queuemaster.mobile.core.design

import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.luminance
import br.dev.pulz.queuemaster.mobile.ui.theme.Cloud0
import br.dev.pulz.queuemaster.mobile.ui.theme.Ink700
import br.dev.pulz.queuemaster.mobile.ui.theme.Ink800
import br.dev.pulz.queuemaster.mobile.ui.theme.Ink900
import br.dev.pulz.queuemaster.mobile.ui.theme.Mist50
import br.dev.pulz.queuemaster.mobile.ui.theme.Mist100
import br.dev.pulz.queuemaster.mobile.ui.theme.Mist200
import br.dev.pulz.queuemaster.mobile.ui.theme.Mist300
import br.dev.pulz.queuemaster.mobile.ui.theme.Night800
import br.dev.pulz.queuemaster.mobile.ui.theme.Night900
import br.dev.pulz.queuemaster.mobile.ui.theme.Night950

object AppGradients {
    @Composable
    fun primaryButton(enabled: Boolean): Brush {
        return if (!enabled) {
            neutralPanel()
        } else if (isDarkTheme()) {
            Brush.linearGradient(
                colors = listOf(Cloud0, Mist200),
                start = Offset.Zero,
                end = Offset.Infinite
            )
        } else {
            Brush.linearGradient(
                colors = listOf(Ink900, Ink700),
                start = Offset.Zero,
                end = Offset.Infinite
            )
        }
    }

    @Composable
    fun softSurface(): Brush {
        return if (isDarkTheme()) {
            Brush.linearGradient(
                colors = listOf(Night900, Night800),
                start = Offset.Zero,
                end = Offset.Infinite
            )
        } else {
            Brush.linearGradient(
                colors = listOf(Cloud0, Mist300),
                start = Offset.Zero,
                end = Offset.Infinite
            )
        }
    }

    @Composable
    fun screenGlow(): Brush {
        return if (isDarkTheme()) {
            Brush.radialGradient(
                colors = listOf(Night800, Night950),
                radius = 1100f
            )
        } else {
            Brush.radialGradient(
                colors = listOf(Mist100, Mist50),
                radius = 1100f
            )
        }
    }

    @Composable
    fun neutralPanel(): Brush {
        return if (isDarkTheme()) {
            Brush.linearGradient(
                colors = listOf(Night800, Night900),
                start = Offset.Zero,
                end = Offset.Infinite
            )
        } else {
            Brush.linearGradient(
                colors = listOf(Mist100, Cloud0),
                start = Offset.Zero,
                end = Offset.Infinite
            )
        }
    }

    @Composable
    fun darkOrb(): Brush {
        return Brush.radialGradient(
            colors = listOf(Ink800, Ink900),
            radius = 360f
        )
    }

    @Composable
    private fun isDarkTheme(): Boolean {
        return MaterialTheme.colorScheme.background.luminance() < 0.5f
    }
}
