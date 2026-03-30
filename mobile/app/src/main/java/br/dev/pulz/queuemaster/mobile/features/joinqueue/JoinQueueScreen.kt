package br.dev.pulz.queuemaster.mobile.features.joinqueue

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.QrCode2
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import br.dev.pulz.queuemaster.mobile.core.design.AppGradients
import br.dev.pulz.queuemaster.mobile.core.design.AppSpacing
import br.dev.pulz.queuemaster.mobile.ui.components.QmBrandTopBar
import br.dev.pulz.queuemaster.mobile.ui.components.QmCard
import br.dev.pulz.queuemaster.mobile.ui.components.QmPrimaryButton
import br.dev.pulz.queuemaster.mobile.ui.theme.Cloud0
import br.dev.pulz.queuemaster.mobile.ui.theme.Mist100
import br.dev.pulz.queuemaster.mobile.ui.theme.Night950

@Composable
fun JoinQueueScreen(
    avatarUrl: String?,
    onManualCodeClick: () -> Unit,
    onQueueStatusClick: () -> Unit,
    onProfileClick: () -> Unit,
    isJoining: Boolean,
    errorMessage: String?,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier
            .fillMaxSize()
            .background(brush = AppGradients.screenGlow())
            .statusBarsPadding()
            .verticalScroll(rememberScrollState())
            .padding(AppSpacing.Xl),
        verticalArrangement = Arrangement.spacedBy(AppSpacing.Xl)
    ) {
        JoinQueueHeader(
            avatarUrl = avatarUrl,
            onProfileClick = onProfileClick
        )

        JoinQueueHeroCard()

        QmPrimaryButton(
            text = "Escanear QR Code",
            onClick = onQueueStatusClick,
            loading = isJoining,
            leadingIcon = Icons.Filled.QrCodeScanner
        )

        errorMessage?.let { message ->
            Surface(
                shape = MaterialTheme.shapes.large,
                color = MaterialTheme.colorScheme.error.copy(alpha = 0.08f),
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.error.copy(alpha = 0.18f))
            ) {
                Text(
                    text = message,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.error,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(AppSpacing.Lg)
                )
            }
        }

        ManualCodeAction(
            onClick = onManualCodeClick
        )

        Spacer(modifier = Modifier.height(AppSpacing.Sm))

        JoinQueueRealtimeCard()
    }
}

@Composable
private fun JoinQueueHeader(
    avatarUrl: String?,
    onProfileClick: () -> Unit
) {
    QmBrandTopBar(
        avatarUrl = avatarUrl,
        onAvatarClick = onProfileClick
    )
}

@Composable
private fun JoinQueueHeroCard() {
    val isDarkTheme = MaterialTheme.colorScheme.background.luminance() < 0.5f
    val qrContainerColor = if (isDarkTheme) Cloud0.copy(alpha = 0.96f) else Mist100
    val qrIconColor = if (isDarkTheme) Night950 else MaterialTheme.colorScheme.primary

    QmCard {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = AppSpacing.Sm),
            contentAlignment = Alignment.Center
        ) {
            Surface(
                shape = MaterialTheme.shapes.large,
                color = qrContainerColor
            ) {
                Box(
                    modifier = Modifier.size(92.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Filled.QrCode2,
                        contentDescription = null,
                        tint = qrIconColor,
                        modifier = Modifier.size(42.dp)
                    )
                }
            }
        }

        Text(
            text = "Entre na fila",
            style = MaterialTheme.typography.displayMedium,
            color = MaterialTheme.colorScheme.onSurface,
            textAlign = TextAlign.Center,
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = AppSpacing.Xl)
        )

        Text(
            text = "Escaneie o QR code do estabelecimento ou digite o codigo manualmente para comecar.",
            style = MaterialTheme.typography.bodyLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = AppSpacing.Md)
        )
    }
}

@Composable
private fun ManualCodeAction(
    onClick: () -> Unit
) {
    Surface(
        onClick = onClick,
        color = Color.Transparent
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = AppSpacing.Sm),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = Icons.Filled.QrCode2,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary,
                modifier = Modifier.size(20.dp)
            )
            Text(
                text = "Inserir codigo manualmente",
                style = MaterialTheme.typography.titleSmall,
                color = MaterialTheme.colorScheme.primary,
                modifier = Modifier.padding(start = AppSpacing.Sm)
            )
            Icon(
                imageVector = Icons.AutoMirrored.Filled.KeyboardArrowRight,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary,
                modifier = Modifier
                    .padding(start = AppSpacing.Xs)
                    .size(18.dp)
            )
        }
    }
}

@Composable
private fun JoinQueueRealtimeCard() {
    Surface(
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surfaceVariant
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(AppSpacing.Xl),
            verticalAlignment = Alignment.Top
        ) {
            Surface(
                shape = MaterialTheme.shapes.medium,
                color = MaterialTheme.colorScheme.surface
            ) {
                Box(
                    modifier = Modifier.size(44.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Filled.Info,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary
                    )
                }
            }

            Column(
                modifier = Modifier
                    .weight(1f)
                    .padding(start = AppSpacing.Md)
            ) {
                Text(
                    text = "Atualizacoes em tempo real",
                    style = MaterialTheme.typography.titleSmall,
                    color = MaterialTheme.colorScheme.onSurface
                )
                Text(
                    text = "Acompanhe sua posicao e o tempo estimado sem precisar ficar parado na recepcao.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(top = AppSpacing.Xs)
                )
            }
        }
    }
}
