package br.dev.pulz.queuemaster.mobile.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.size
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import br.dev.pulz.queuemaster.mobile.core.design.AppSpacing

@Composable
fun QmBrandTopBar(
    avatarUrl: String?,
    onAvatarClick: (() -> Unit)?,
    modifier: Modifier = Modifier,
    title: String = "QueueMaster"
) {
    Row(
        modifier = modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(AppSpacing.Md),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Row(
            modifier = Modifier.weight(1f),
            horizontalArrangement = Arrangement.spacedBy(AppSpacing.Md),
            verticalAlignment = Alignment.CenterVertically
        ) {
            QmLogo(
                modifier = Modifier.size(36.dp),
                contentDescription = "Logo QueueMaster"
            )
            Text(
                text = title,
                style = MaterialTheme.typography.headlineSmall.copy(fontWeight = FontWeight.Bold),
                color = MaterialTheme.colorScheme.onBackground
            )
        }

        if (avatarUrl != null || onAvatarClick != null) {
            Surface(
                onClick = { onAvatarClick?.invoke() },
                enabled = onAvatarClick != null,
                shape = MaterialTheme.shapes.medium,
                color = MaterialTheme.colorScheme.surface,
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)
            ) {
                QmAvatar(
                    imageUrl = avatarUrl,
                    contentDescription = "Abrir perfil",
                    size = 44.dp
                )
            }
        }
    }
}
