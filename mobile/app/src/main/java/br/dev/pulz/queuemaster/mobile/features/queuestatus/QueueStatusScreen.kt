package br.dev.pulz.queuemaster.mobile.features.queuestatus

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.MedicalServices
import androidx.compose.material.icons.filled.NotificationsActive
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import br.dev.pulz.queuemaster.mobile.core.design.AppGradients
import br.dev.pulz.queuemaster.mobile.core.design.AppSpacing
import br.dev.pulz.queuemaster.mobile.core.model.QueueUserEntry
import br.dev.pulz.queuemaster.mobile.ui.components.QmBrandTopBar
import br.dev.pulz.queuemaster.mobile.ui.components.QmPill
import br.dev.pulz.queuemaster.mobile.ui.components.QmSecondaryButton
import br.dev.pulz.queuemaster.mobile.ui.theme.Info400
import br.dev.pulz.queuemaster.mobile.ui.theme.Info500
import br.dev.pulz.queuemaster.mobile.ui.theme.Success400
import br.dev.pulz.queuemaster.mobile.ui.theme.Success500
import br.dev.pulz.queuemaster.mobile.ui.theme.Warning400
import br.dev.pulz.queuemaster.mobile.ui.theme.Warning500
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@Composable
fun QueueStatusScreen(
    avatarUrl: String?,
    uiState: QueueStatusUiState,
    isRefreshing: Boolean,
    lastUpdatedAt: Long?,
    onRefreshClick: () -> Unit,
    onLeaveQueueClick: () -> Unit,
    onJoinQueueClick: () -> Unit,
    onProfileClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val lastUpdatedLabel = remember(lastUpdatedAt) {
        lastUpdatedAt?.let {
            SimpleDateFormat("HH:mm", Locale.forLanguageTag("pt-BR")).format(Date(it))
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(brush = AppGradients.screenGlow())
            .statusBarsPadding()
            .verticalScroll(rememberScrollState())
            .padding(AppSpacing.Xl),
        verticalArrangement = Arrangement.spacedBy(AppSpacing.Lg)
    ) {
        QueueStatusHeader(
            avatarUrl = avatarUrl,
            onProfileClick = onProfileClick
        )

        when (uiState) {
            QueueStatusUiState.Loading -> QueueStatusLoading()
            QueueStatusUiState.NoActiveQueue -> QueueStatusEmpty(
                onJoinQueueClick = onJoinQueueClick
            )
            is QueueStatusUiState.Error -> QueueStatusError(
                message = uiState.message,
                onJoinQueueClick = onJoinQueueClick
            )
            is QueueStatusUiState.Active -> {
                val queueStatus = uiState.queueStatus
                val userEntry = queueStatus.userEntry
                val statusPresentation = remember(userEntry) {
                    QueueStatusPresentation.from(userEntry)
                }

                QueueStatusTitle(
                    queueName = queueStatus.queue.name,
                    queuePlace = queueStatus.queue.establishmentName,
                    isRefreshing = isRefreshing,
                    onRefreshClick = onRefreshClick
                )

                QueuePositionCard(
                    presentation = statusPresentation
                )

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(AppSpacing.Md)
                ) {
                    QueueDetailCard(
                        modifier = Modifier.weight(1f),
                        icon = Icons.Filled.MedicalServices,
                        label = "Servico",
                        value = queueStatus.queue.serviceName ?: "Atendimento"
                    )
                    QueueDetailCard(
                        modifier = Modifier.weight(1f),
                        icon = Icons.Filled.Schedule,
                        label = statusPresentation.secondaryDetailLabel,
                        value = statusPresentation.secondaryDetailValue
                    )
                }

                QueueNotificationCard(
                    status = userEntry?.status.orEmpty(),
                    lastUpdatedLabel = lastUpdatedLabel
                )

                QmSecondaryButton(
                    text = "Sair da fila",
                    onClick = onLeaveQueueClick
                )

                Text(
                    text = statusPresentation.footerMessage,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.fillMaxWidth()
                )
            }
        }
    }
}

@Composable
private fun QueueStatusHeader(
    avatarUrl: String?,
    onProfileClick: () -> Unit
) {
    QmBrandTopBar(
        avatarUrl = avatarUrl,
        onAvatarClick = onProfileClick,
        modifier = Modifier.fillMaxWidth()
    )
}

@Composable
private fun QueueStatusTitle(
    queueName: String,
    queuePlace: String,
    isRefreshing: Boolean,
    onRefreshClick: () -> Unit
) {
    Column(verticalArrangement = Arrangement.spacedBy(AppSpacing.Xs)) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = queueName,
                style = MaterialTheme.typography.headlineLarge,
                color = MaterialTheme.colorScheme.onBackground,
                modifier = Modifier.weight(1f)
            )

            if (isRefreshing) {
                CircularProgressIndicator(
                    modifier = Modifier.size(22.dp),
                    strokeWidth = 2.dp,
                    color = MaterialTheme.colorScheme.primary
                )
            } else {
                IconButton(onClick = onRefreshClick) {
                    Icon(
                        imageVector = Icons.Filled.Refresh,
                        contentDescription = "Atualizar fila",
                        tint = MaterialTheme.colorScheme.onSurface
                    )
                }
            }
        }

        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(
                imageVector = Icons.Filled.LocationOn,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary,
                modifier = Modifier.size(16.dp)
            )
            Text(
                text = queuePlace,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(start = AppSpacing.Xs)
            )
        }
    }
}

@Composable
private fun QueuePositionCard(
    presentation: QueueStatusPresentation
) {
    Surface(
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surface,
        tonalElevation = 2.dp,
        shadowElevation = 10.dp
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(AppSpacing.Xxl),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            QueueStatusBadge(
                statusLabel = presentation.badgeLabel
            )

            Text(
                text = presentation.heroTitle,
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(top = AppSpacing.Xl)
            )

            Text(
                text = presentation.heroValue,
                style = MaterialTheme.typography.displayLarge,
                color = MaterialTheme.colorScheme.primary,
                modifier = Modifier.padding(top = AppSpacing.Sm)
            )

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(top = AppSpacing.Xl),
                horizontalArrangement = Arrangement.spacedBy(AppSpacing.Md)
            ) {
                QueueMetricColumn(
                    modifier = Modifier.weight(1f),
                    label = presentation.primaryMetricLabel,
                    value = presentation.primaryMetricValue
                )
                QueueMetricColumn(
                    modifier = Modifier.weight(1f),
                    label = presentation.secondaryMetricLabel,
                    value = presentation.secondaryMetricValue
                )
            }
        }
    }
}

@Composable
private fun QueueStatusBadge(
    statusLabel: String
) {
    val isDarkTheme = MaterialTheme.colorScheme.background.luminance() < 0.5f
    val accent = when (statusLabel.lowercase(Locale.ROOT)) {
        "chamado" -> if (isDarkTheme) Warning400 else Warning500
        "em atendimento" -> if (isDarkTheme) Info400 else Info500
        else -> if (isDarkTheme) Success400 else Success500
    }

    QmPill(
        text = statusLabel.uppercase(),
        containerColor = accent.copy(alpha = 0.14f),
        contentColor = accent,
        dotColor = accent
    )
}

@Composable
private fun QueueMetricColumn(
    modifier: Modifier = Modifier,
    label: String,
    value: String
) {
    Column(
        modifier = modifier
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
        Text(
            text = value,
            style = MaterialTheme.typography.titleLarge,
            color = MaterialTheme.colorScheme.onSurface,
            modifier = Modifier.padding(top = AppSpacing.Xs)
        )
    }
}

@Composable
private fun QueueDetailCard(
    modifier: Modifier = Modifier,
    icon: ImageVector,
    label: String,
    value: String
) {
    Surface(
        modifier = modifier,
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surfaceVariant
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(AppSpacing.Lg)
        ) {
            Surface(
                shape = MaterialTheme.shapes.medium,
                color = MaterialTheme.colorScheme.surface
            ) {
                Box(
                    modifier = Modifier.size(40.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = icon,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary
                    )
                }
            }

            Text(
                text = label.uppercase(),
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(top = AppSpacing.Lg)
            )
            Text(
                text = value,
                style = MaterialTheme.typography.titleMedium,
                color = MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.padding(top = AppSpacing.Xs)
            )
        }
    }
}

@Composable
private fun QueueNotificationCard(
    status: String,
    lastUpdatedLabel: String?
) {
    Surface(
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surfaceVariant
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(AppSpacing.Xl),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Surface(
                shape = MaterialTheme.shapes.medium,
                color = Color.White.copy(alpha = 0.72f)
            ) {
                Box(
                    modifier = Modifier.size(44.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Filled.NotificationsActive,
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
                    text = "ATUALIZACAO ATIVA",
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Text(
                    text = notificationCopy(status = status, lastUpdatedLabel = lastUpdatedLabel),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurface,
                    modifier = Modifier.padding(top = AppSpacing.Xs)
                )
            }
        }
    }
}

@Composable
private fun QueueStatusLoading() {
    Surface(
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surface
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(AppSpacing.Xxl),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(AppSpacing.Md)
        ) {
            CircularProgressIndicator(
                color = MaterialTheme.colorScheme.primary
            )
            Text(
                text = "Atualizando sua fila",
                style = MaterialTheme.typography.titleMedium,
                color = MaterialTheme.colorScheme.onSurface
            )
            Text(
                text = "Buscando sua posicao atual e a nova estimativa de atendimento.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

@Composable
private fun QueueStatusEmpty(
    onJoinQueueClick: () -> Unit
) {
    Surface(
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surface
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(AppSpacing.Xxl),
            verticalArrangement = Arrangement.spacedBy(AppSpacing.Lg)
        ) {
            Text(
                text = "Nenhuma fila ativa",
                style = MaterialTheme.typography.headlineSmall,
                color = MaterialTheme.colorScheme.onSurface
            )
            Text(
                text = "Quando voce entrar em uma fila, a posicao, o tempo estimado e os detalhes do atendimento aparecerao aqui.",
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            QmSecondaryButton(
                text = "Entrar em uma fila",
                onClick = onJoinQueueClick
            )
        }
    }
}

@Composable
private fun QueueStatusError(
    message: String,
    onJoinQueueClick: () -> Unit
) {
    Surface(
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surface
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(AppSpacing.Xxl),
            verticalArrangement = Arrangement.spacedBy(AppSpacing.Lg)
        ) {
            Text(
                text = "Nao foi possivel carregar sua fila",
                style = MaterialTheme.typography.headlineSmall,
                color = MaterialTheme.colorScheme.onSurface
            )
            Text(
                text = message,
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            QmSecondaryButton(
                text = "Voltar para entrada",
                onClick = onJoinQueueClick
            )
        }
    }
}

private data class QueueStatusPresentation(
    val badgeLabel: String,
    val heroTitle: String,
    val heroValue: String,
    val primaryMetricLabel: String,
    val primaryMetricValue: String,
    val secondaryMetricLabel: String,
    val secondaryMetricValue: String,
    val secondaryDetailLabel: String,
    val secondaryDetailValue: String,
    val footerMessage: String
) {
    companion object {
        fun from(userEntry: QueueUserEntry?): QueueStatusPresentation {
            val status = userEntry?.status?.lowercase(Locale.ROOT).orEmpty()
            return when (status) {
                "called" -> QueueStatusPresentation(
                    badgeLabel = "Chamado",
                    heroTitle = "SUA VEZ",
                    heroValue = "AGORA",
                    primaryMetricLabel = "CHAMADO EM",
                    primaryMetricValue = formatEventTime(userEntry?.calledAt) ?: "Agora",
                    secondaryMetricLabel = "A SUA FRENTE",
                    secondaryMetricValue = "0 pessoas",
                    secondaryDetailLabel = if (userEntry?.professionalName.isNullOrBlank()) {
                        "Atendimento"
                    } else {
                        "Profissional"
                    },
                    secondaryDetailValue = userEntry?.professionalName ?: "Dirija-se ao atendimento",
                    footerMessage = "Voce ja foi chamado. Ao sair agora, sua vez sera encerrada."
                )

                "serving" -> QueueStatusPresentation(
                    badgeLabel = "Em atendimento",
                    heroTitle = "ATENDIMENTO",
                    heroValue = "ATIVO",
                    primaryMetricLabel = "TEMPO EM ATENDIMENTO",
                    primaryMetricValue = userEntry?.servingSinceMinutes
                        ?.takeIf { it > 0 }
                        ?.let { "$it min" }
                        ?: "Agora",
                    secondaryMetricLabel = "PROFISSIONAL",
                    secondaryMetricValue = userEntry?.professionalName ?: "Equipe QueueMaster",
                    secondaryDetailLabel = "CHAMADO EM",
                    secondaryDetailValue = formatEventTime(userEntry?.calledAt)
                        ?: formatEventTime(userEntry?.joinedAt)
                        ?: "Agora",
                    footerMessage = "Seu atendimento esta em andamento. Se sair agora, sua entrada sera encerrada."
                )

                else -> QueueStatusPresentation(
                    badgeLabel = "Na fila",
                    heroTitle = "SUA POSICAO",
                    heroValue = userEntry?.position?.toString() ?: "--",
                    primaryMetricLabel = "TEMPO ESTIMADO",
                    primaryMetricValue = userEntry?.estimatedWaitMinutes
                        ?.let { "$it min" }
                        ?: "A definir",
                    secondaryMetricLabel = "A SUA FRENTE",
                    secondaryMetricValue = "${userEntry?.peopleAhead ?: 0} pessoas",
                    secondaryDetailLabel = "Entrada",
                    secondaryDetailValue = formatEventTime(userEntry?.joinedAt) ?: "Agora",
                    footerMessage = "Ao sair da fila, sua posicao e sua estimativa de atendimento serao perdidas."
                )
            }
        }
    }
}

private fun notificationCopy(status: String, lastUpdatedLabel: String?): String {
    return when (status.lowercase(Locale.ROOT)) {
        "called" -> lastUpdatedLabel?.let {
            "Voce foi chamado. Confira o atendimento o quanto antes. Ultima atualizacao as $it."
        } ?: "Voce foi chamado. Confira o atendimento o quanto antes."

        "serving" -> lastUpdatedLabel?.let {
            "Seu atendimento esta em andamento. Os detalhes continuam sendo atualizados. Ultima atualizacao as $it."
        } ?: "Seu atendimento esta em andamento. Os detalhes continuam sendo atualizados."

        else -> lastUpdatedLabel?.let {
            "Sua posicao e o tempo estimado sao atualizados automaticamente. Ultima atualizacao as $it."
        } ?: "Sua posicao e o tempo estimado sao atualizados automaticamente enquanto voce acompanha a fila."
    }
}

private fun formatEventTime(rawValue: String?): String? {
    if (rawValue.isNullOrBlank()) return null

    val inputPatterns = listOf(
        "yyyy-MM-dd HH:mm:ss",
        "yyyy-MM-dd'T'HH:mm:ssXXX",
        "yyyy-MM-dd'T'HH:mm:ss.SSSXXX"
    )

    inputPatterns.forEach { pattern ->
        runCatching {
            val parser = SimpleDateFormat(pattern, Locale.US).apply { isLenient = false }
            parser.parse(rawValue)
        }.getOrNull()?.let { parsedDate ->
            return SimpleDateFormat("HH:mm", Locale.forLanguageTag("pt-BR")).format(parsedDate)
        }
    }

    return rawValue
}
