package br.dev.pulz.queuemaster.mobile.core.design

import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import br.dev.pulz.queuemaster.mobile.ui.theme.Cloud0
import br.dev.pulz.queuemaster.mobile.ui.theme.Ink700
import br.dev.pulz.queuemaster.mobile.ui.theme.Ink800
import br.dev.pulz.queuemaster.mobile.ui.theme.Ink900
import br.dev.pulz.queuemaster.mobile.ui.theme.Mist50
import br.dev.pulz.queuemaster.mobile.ui.theme.Mist100

object AppGradients {
    val PrimaryButton = Brush.linearGradient(
        colors = listOf(Ink700, Ink900),
        start = Offset.Zero,
        end = Offset.Infinite
    )

    val SoftSurface = Brush.linearGradient(
        colors = listOf(Cloud0, Mist100),
        start = Offset.Zero,
        end = Offset.Infinite
    )

    val ScreenGlow = Brush.radialGradient(
        colors = listOf(Cloud0, Mist50),
        radius = 900f
    )

    val NeutralPanel = Brush.linearGradient(
        colors = listOf(Mist50, Cloud0),
        start = Offset.Zero,
        end = Offset.Infinite
    )

    val DarkOrb = Brush.radialGradient(
        colors = listOf(Ink800, Ink900),
        radius = 360f
    )
}
