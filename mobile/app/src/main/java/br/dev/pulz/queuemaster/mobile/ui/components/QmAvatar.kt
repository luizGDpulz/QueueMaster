package br.dev.pulz.queuemaster.mobile.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Person
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import coil3.compose.AsyncImage
import coil3.request.ImageRequest
import br.dev.pulz.queuemaster.mobile.core.design.AppGradients
import br.dev.pulz.queuemaster.mobile.core.network.QueueMasterImageLoader
import br.dev.pulz.queuemaster.mobile.ui.theme.Cloud0

@Composable
fun QmAvatar(
    imageUrl: String?,
    contentDescription: String?,
    modifier: Modifier = Modifier,
    size: Dp,
    shape: RoundedCornerShape = RoundedCornerShape(32)
) {
    val context = LocalContext.current
    val imageLoader = remember(context) {
        QueueMasterImageLoader.get(context)
    }

    Surface(
        modifier = modifier.size(size),
        shape = shape,
        shadowElevation = 14.dp
    ) {
        if (imageUrl.isNullOrBlank()) {
            AvatarFallback(
                modifier = Modifier.fillMaxSize()
            )
        } else {
            AsyncImage(
                model = ImageRequest.Builder(context)
                    .data(imageUrl)
                    .build(),
                imageLoader = imageLoader,
                contentDescription = contentDescription,
                contentScale = ContentScale.Crop,
                modifier = Modifier
                    .fillMaxSize()
                    .clip(shape),
                error = null,
                fallback = null
            )
        }
    }
}

@Composable
private fun AvatarFallback(
    modifier: Modifier = Modifier
) {
    Box(
        modifier = modifier
            .background(brush = AppGradients.darkOrb()),
        contentAlignment = Alignment.Center
    ) {
        Icon(
            imageVector = Icons.Filled.Person,
            contentDescription = null,
            tint = Cloud0,
            modifier = Modifier.size(52.dp)
        )
    }
}
