package br.dev.pulz.queuemaster.mobile.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.compose.material3.Icon
import br.dev.pulz.queuemaster.mobile.core.design.AppSpacing

@Composable
fun QmPill(
    text: String,
    modifier: Modifier = Modifier,
    containerColor: Color = MaterialTheme.colorScheme.primary.copy(alpha = 0.1f),
    contentColor: Color = MaterialTheme.colorScheme.primary,
    leadingIcon: ImageVector? = null,
    dotColor: Color? = null
) {
    Surface(
        modifier = modifier,
        shape = MaterialTheme.shapes.medium,
        color = containerColor
    ) {
        Row(
            modifier = Modifier.padding(horizontal = AppSpacing.Md, vertical = AppSpacing.Xs),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(AppSpacing.Xs)
        ) {
            dotColor?.let { color ->
                Box(
                    modifier = Modifier
                        .size(8.dp)
                        .background(
                            color = color,
                            shape = MaterialTheme.shapes.small
                        )
                )
            }

            leadingIcon?.let { icon ->
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = contentColor,
                    modifier = Modifier.size(16.dp)
                )
            }

            Text(
                text = text,
                style = MaterialTheme.typography.labelMedium,
                color = contentColor
            )
        }
    }
}
