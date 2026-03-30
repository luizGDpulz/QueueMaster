package br.dev.pulz.queuemaster.mobile.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import br.dev.pulz.queuemaster.mobile.core.design.AppGradients
import br.dev.pulz.queuemaster.mobile.core.design.AppSpacing

@Composable
fun QmPlaceholderState(
    icon: ImageVector,
    title: String,
    description: String,
    modifier: Modifier = Modifier,
    eyebrow: String? = null,
    primaryActionLabel: String? = null,
    onPrimaryAction: (() -> Unit)? = null,
    secondaryActionLabel: String? = null,
    onSecondaryAction: (() -> Unit)? = null,
    bottomContent: @Composable ColumnScope.() -> Unit = {}
) {
    QmCard(modifier = modifier) {
        Box(
            modifier = Modifier
                .size(72.dp)
                .background(
                    brush = AppGradients.neutralPanel(),
                    shape = MaterialTheme.shapes.medium
                ),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary
            )
        }

        eyebrow?.let {
            Text(
                text = it.uppercase(),
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(top = AppSpacing.Lg)
            )
        }

        Text(
            text = title,
            style = MaterialTheme.typography.headlineMedium,
            color = MaterialTheme.colorScheme.onSurface,
            modifier = Modifier.padding(top = AppSpacing.Xs)
        )

        Text(
            text = description,
            style = MaterialTheme.typography.bodyLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.padding(top = AppSpacing.Sm)
        )

        if (primaryActionLabel != null && onPrimaryAction != null) {
            QmPrimaryButton(
                text = primaryActionLabel,
                onClick = onPrimaryAction,
                modifier = Modifier.padding(top = AppSpacing.Xl)
            )
        }

        if (secondaryActionLabel != null && onSecondaryAction != null) {
            QmSecondaryButton(
                text = secondaryActionLabel,
                onClick = onSecondaryAction,
                modifier = Modifier.padding(top = AppSpacing.Sm)
            )
        }

        bottomContent()
    }
}
