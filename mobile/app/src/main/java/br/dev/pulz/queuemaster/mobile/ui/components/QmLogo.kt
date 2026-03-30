package br.dev.pulz.queuemaster.mobile.ui.components

import androidx.compose.foundation.Image
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.res.painterResource
import br.dev.pulz.queuemaster.mobile.R

@Composable
fun QmLogo(
    modifier: Modifier = Modifier,
    contentDescription: String? = null
) {
    val logoRes = if (MaterialTheme.colorScheme.background.luminance() < 0.5f) {
        R.drawable.qm_logo_dark
    } else {
        R.drawable.qm_logo
    }

    Image(
        painter = painterResource(id = logoRes),
        contentDescription = contentDescription,
        modifier = modifier
    )
}
