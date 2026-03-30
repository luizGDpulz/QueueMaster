package br.dev.pulz.queuemaster.mobile.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import br.dev.pulz.queuemaster.mobile.core.design.AppGradients
import br.dev.pulz.queuemaster.mobile.core.design.AppSize
import br.dev.pulz.queuemaster.mobile.core.design.AppSpacing
import br.dev.pulz.queuemaster.mobile.ui.theme.Mist100
import br.dev.pulz.queuemaster.mobile.ui.theme.Mist200
import br.dev.pulz.queuemaster.mobile.ui.theme.Slate500

@Composable
fun QmPrimaryButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    loading: Boolean = false,
    leadingIcon: ImageVector? = null
) {
    val canClick = enabled && !loading

    Surface(
        modifier = modifier,
        onClick = onClick,
        enabled = canClick,
        color = Color.Transparent,
        shadowElevation = 6.dp,
        shape = MaterialTheme.shapes.large
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = AppSize.ButtonHeight)
                .background(
                    brush = AppGradients.primaryButton(canClick),
                    shape = MaterialTheme.shapes.large
                )
                .padding(horizontal = AppSpacing.Lg, vertical = AppSpacing.Md),
            contentAlignment = Alignment.Center
        ) {
            if (loading) {
                CircularProgressIndicator(
                    color = MaterialTheme.colorScheme.onPrimary,
                    strokeWidth = 2.dp
                )
            } else {
                Row(
                    horizontalArrangement = Arrangement.Center,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    leadingIcon?.let { icon ->
                        Icon(
                            imageVector = icon,
                            contentDescription = null,
                            tint = if (canClick) {
                                MaterialTheme.colorScheme.onPrimary
                            } else {
                                Slate500
                            }
                        )
                        Spacer(modifier = Modifier.width(AppSpacing.Sm))
                    }

                    Text(
                        text = text,
                        style = MaterialTheme.typography.titleSmall,
                        color = if (canClick) {
                            MaterialTheme.colorScheme.onPrimary
                        } else {
                            Slate500
                        }
                    )
                }
            }
        }
    }
}

@Composable
fun QmSecondaryButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true
) {
    Surface(
        modifier = modifier,
        onClick = onClick,
        enabled = enabled,
        color = if (enabled) MaterialTheme.colorScheme.surface else Mist100,
        tonalElevation = 0.dp,
        shadowElevation = 0.dp,
        shape = MaterialTheme.shapes.large,
        border = BorderStroke(
            width = 1.dp,
            color = if (enabled) MaterialTheme.colorScheme.outlineVariant else Mist200
        )
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = AppSize.ButtonHeight)
                .padding(horizontal = AppSpacing.Lg, vertical = AppSpacing.Md),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = text,
                style = MaterialTheme.typography.titleSmall,
                color = if (enabled) {
                    MaterialTheme.colorScheme.onSurface
                } else {
                    Slate500
                }
            )
        }
    }
}
