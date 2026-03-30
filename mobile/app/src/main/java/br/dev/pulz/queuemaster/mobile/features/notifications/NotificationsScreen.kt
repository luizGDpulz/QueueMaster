package br.dev.pulz.queuemaster.mobile.features.notifications

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Cancel
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.NotificationsActive
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import br.dev.pulz.queuemaster.mobile.core.design.AppGradients
import br.dev.pulz.queuemaster.mobile.core.design.AppSpacing
import br.dev.pulz.queuemaster.mobile.core.model.AppNotificationGroup
import br.dev.pulz.queuemaster.mobile.core.model.AppNotificationItem
import br.dev.pulz.queuemaster.mobile.core.model.AppNotificationType
import br.dev.pulz.queuemaster.mobile.core.model.NotificationContextType
import br.dev.pulz.queuemaster.mobile.ui.components.QmBrandTopBar
import br.dev.pulz.queuemaster.mobile.ui.components.QmPill
import br.dev.pulz.queuemaster.mobile.ui.components.QmPlaceholderState
import br.dev.pulz.queuemaster.mobile.ui.components.QmPrimaryButton
import br.dev.pulz.queuemaster.mobile.ui.components.QmSectionTitle
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
fun NotificationsScreen(
    avatarUrl: String?,
    uiState: NotificationsUiState,
    onAvatarClick: () -> Unit,
    onRetryClick: () -> Unit,
    onGroupClick: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier
            .fillMaxSize()
            .background(brush = AppGradients.screenGlow())
            .statusBarsPadding()
            .verticalScroll(rememberScrollState())
            .padding(AppSpacing.Xl),
        verticalArrangement = Arrangement.spacedBy(AppSpacing.Lg)
    ) {
        QmBrandTopBar(
            avatarUrl = avatarUrl,
            onAvatarClick = onAvatarClick
        )

        when (uiState) {
            NotificationsUiState.Loading -> {
                NotificationsLoadingCard(
                    title = "Carregando histórico",
                    description = "Estamos organizando seus fluxos de fila."
                )
            }

            NotificationsUiState.Empty -> {
                NotificationsEmptyCard()
            }

            is NotificationsUiState.Error -> {
                NotificationsErrorCard(
                    message = uiState.message,
                    onRetryClick = onRetryClick
                )
            }

            is NotificationsUiState.Loaded -> {
                NotificationSummaryStrip(
                    totalFlows = uiState.groups.size,
                    activeFlows = uiState.groups.count { it.isActiveFlow }
                )

                QmSectionTitle(text = "Ultimos fluxos")

                uiState.groups.forEach { group ->
                    NotificationGroupCard(
                        group = group,
                        onClick = { onGroupClick(group.contextKey) }
                    )
                }
            }
        }
    }
}

@Composable
fun NotificationDetailsScreen(
    avatarUrl: String?,
    uiState: NotificationDetailsUiState,
    onAvatarClick: () -> Unit,
    onBackClick: () -> Unit,
    onRetryClick: () -> Unit,
    onOpenQueueClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier
            .fillMaxSize()
            .background(brush = AppGradients.screenGlow())
            .statusBarsPadding()
            .verticalScroll(rememberScrollState())
            .padding(AppSpacing.Xl),
        verticalArrangement = Arrangement.spacedBy(AppSpacing.Lg)
    ) {
        QmBrandTopBar(
            avatarUrl = avatarUrl,
            onAvatarClick = onAvatarClick
        )

        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically
        ) {
            IconButton(onClick = onBackClick) {
                Icon(
                    imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                    contentDescription = "Voltar"
                )
            }
            Text(
                text = detailScreenTitle(uiState),
                style = MaterialTheme.typography.headlineSmall,
                color = MaterialTheme.colorScheme.onBackground,
                modifier = Modifier.padding(start = AppSpacing.Xs)
            )
        }

        when (uiState) {
            NotificationDetailsUiState.Idle,
            NotificationDetailsUiState.Loading -> {
                NotificationsLoadingCard(
                    title = "Carregando fluxo",
                    description = "Estamos buscando a linha do tempo desta entrada."
                )
            }

            NotificationDetailsUiState.Empty -> {
                NotificationsEmptyCard(
                    title = "Fluxo não encontrado",
                    description = "Esse histórico não está mais disponivel para consulta."
                )
            }

            is NotificationDetailsUiState.Error -> {
                NotificationsErrorCard(
                    message = uiState.message,
                    onRetryClick = onRetryClick
                )
            }

            is NotificationDetailsUiState.Loaded -> {
                NotificationDetailsContent(
                    group = uiState.group,
                    onOpenQueueClick = onOpenQueueClick
                )
            }
        }
    }
}

@Composable
private fun NotificationDetailsContent(
    group: AppNotificationGroup,
    onOpenQueueClick: () -> Unit
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(AppSpacing.Sm),
        verticalAlignment = Alignment.CenterVertically
    ) {
        NotificationContextPill(contextType = group.contextType)
        NotificationFlowStatePill(group = group)
        QmPill(
            text = "${group.totalEvents} evento(s)",
            containerColor = MaterialTheme.colorScheme.surfaceVariant,
            contentColor = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }

    if (group.isActiveFlow) {
        QmPrimaryButton(
            text = "Abrir fila atual",
            onClick = onOpenQueueClick
        )
    }

    group.events
        .sortedBy { it.createdAt }
        .forEach { event ->
            NotificationTimelineCard(event = event)
        }
}

@Composable
private fun NotificationsLoadingCard(
    title: String,
    description: String
) {
    Surface(
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surface
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(AppSpacing.Xl),
            verticalArrangement = Arrangement.spacedBy(AppSpacing.Sm)
        ) {
            Text(
                text = title,
                style = MaterialTheme.typography.titleLarge,
                color = MaterialTheme.colorScheme.onSurface
            )
            Text(
                text = description,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

@Composable
private fun NotificationsEmptyCard(
    title: String = "Nenhum fluxo registrado",
    description: String = "Quando sua fila avancar, os eventos vao aparecer aqui agrupados por entrada."
) {
    QmPlaceholderState(
        icon = Icons.Filled.NotificationsActive,
        title = title,
        description = description,
        eyebrow = "Notificações"
    )
}

@Composable
private fun NotificationsErrorCard(
    message: String,
    onRetryClick: () -> Unit
) {
    QmPlaceholderState(
        icon = Icons.Filled.ErrorOutline,
        title = "Não foi possível carregar",
        description = message,
        eyebrow = "Notificações",
        primaryActionLabel = "Tentar novamente",
        onPrimaryAction = onRetryClick
    )
}

@Composable
private fun NotificationSummaryStrip(
    totalFlows: Int,
    activeFlows: Int
) {
    Surface(
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surfaceVariant
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = AppSpacing.Lg, vertical = AppSpacing.Md),
            verticalArrangement = Arrangement.spacedBy(AppSpacing.Sm)
        ) {
            Text(
                text = when {
                    activeFlows > 1 -> "$activeFlows fluxos ativos agora"
                    activeFlows == 1 -> "1 fluxo ativo agora"
                    totalFlows > 0 -> "Histórico atualizado"
                    else -> "Sem histórico ainda"
                },
                style = MaterialTheme.typography.titleSmall,
                color = MaterialTheme.colorScheme.onSurface
            )
            Text(
                text = if (totalFlows > 0) {
                    "$totalFlows fluxo(s) registrados. Cada entrada da fila gera uma linha do tempo separada."
                } else {
                    "Assim que você participar de uma fila, o histórico vai aparecer aqui."
                },
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

@Composable
private fun NotificationGroupCard(
    group: AppNotificationGroup,
    onClick: () -> Unit
) {
    val borderColor = when {
        group.isActiveFlow -> MaterialTheme.colorScheme.primary.copy(alpha = 0.16f)
        group.canJoinAgain -> MaterialTheme.colorScheme.secondary.copy(alpha = 0.16f)
        else -> MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.65f)
    }

    Surface(
        onClick = onClick,
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surface,
        tonalElevation = 2.dp,
        shadowElevation = 8.dp,
        border = BorderStroke(1.dp, borderColor)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(AppSpacing.Lg),
            verticalArrangement = Arrangement.spacedBy(AppSpacing.Md)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Top
            ) {
                Column(
                    modifier = Modifier.weight(1f)
                ) {
                    Text(
                        text = group.contextTitle,
                        style = MaterialTheme.typography.titleMedium,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    group.contextSubtitle?.let { subtitle ->
                        Text(
                            text = subtitle,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.padding(top = AppSpacing.Xs)
                        )
                    }
                }
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(AppSpacing.Sm),
                verticalAlignment = Alignment.CenterVertically
            ) {
                NotificationContextPill(contextType = group.contextType)
                NotificationFlowStatePill(group = group)
                NotificationEventPill(event = group.lastEvent)
            }

            Text(
                text = group.lastEvent.body,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "${group.totalEvents} evento(s) neste fluxo",
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Text(
                    text = formatNotificationTime(group.lastEvent.createdAt),
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
    }
}

@Composable
private fun NotificationTimelineCard(
    event: AppNotificationItem
) {
    Surface(
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surface
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(AppSpacing.Lg),
            verticalArrangement = Arrangement.spacedBy(AppSpacing.Sm)
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(AppSpacing.Sm)
            ) {
                NotificationEventPill(event = event)
                Text(
                    text = formatNotificationDateTime(event.createdAt),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }

            Text(
                text = event.title,
                style = MaterialTheme.typography.titleMedium,
                color = MaterialTheme.colorScheme.onSurface
            )
            Text(
                text = event.body,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

@Composable
private fun NotificationEventPill(
    event: AppNotificationItem
) {
    val tone = notificationTone(event.type)
    QmPill(
        text = tone.label,
        leadingIcon = tone.icon,
        containerColor = tone.accentColor.copy(alpha = 0.14f),
        contentColor = tone.accentColor
    )
}

@Composable
private fun NotificationContextPill(
    contextType: NotificationContextType
) {
    QmPill(
        text = contextLabel(contextType),
        containerColor = MaterialTheme.colorScheme.surfaceVariant,
        contentColor = MaterialTheme.colorScheme.onSurfaceVariant
    )
}

@Composable
private fun NotificationFlowStatePill(
    group: AppNotificationGroup
) {
    val text = when {
        group.isActiveFlow -> "Ativo"
        group.canJoinAgain -> "Encerrado"
        else -> "Finalizado"
    }
    val color = when {
        group.isActiveFlow -> MaterialTheme.colorScheme.primary
        group.canJoinAgain -> MaterialTheme.colorScheme.secondary
        else -> MaterialTheme.colorScheme.onSurfaceVariant
    }

    QmPill(
        text = text,
        containerColor = color.copy(alpha = 0.14f),
        contentColor = color
    )
}

@Composable
private fun notificationTone(type: AppNotificationType): NotificationTone {
    val isDarkTheme = MaterialTheme.colorScheme.background.luminance() < 0.5f
    val accentColor = when (type) {
        AppNotificationType.QueueJoined,
        AppNotificationType.QueueCompleted -> if (isDarkTheme) Success400 else Success500

        AppNotificationType.QueueNext -> if (isDarkTheme) Warning400 else Warning500

        AppNotificationType.QueueCalled,
        AppNotificationType.QueueServing,
        AppNotificationType.QueueRequeued -> if (isDarkTheme) Info400 else Info500

        AppNotificationType.QueueCancelled,
        AppNotificationType.QueueNoShow -> MaterialTheme.colorScheme.error

        AppNotificationType.QueueLeft,
        AppNotificationType.QueueUpdated -> MaterialTheme.colorScheme.onSurfaceVariant
    }

    return when (type) {
        AppNotificationType.QueueJoined -> NotificationTone(
            icon = Icons.Filled.CheckCircle,
            label = "Entrada",
            accentColor = accentColor
        )

        AppNotificationType.QueueNext -> NotificationTone(
            icon = Icons.Filled.Schedule,
            label = "Pr?ximo",
            accentColor = accentColor
        )

        AppNotificationType.QueueCalled -> NotificationTone(
            icon = Icons.Filled.NotificationsActive,
            label = "Chamado",
            accentColor = accentColor
        )

        AppNotificationType.QueueServing -> NotificationTone(
            icon = Icons.Filled.NotificationsActive,
            label = "Atendimento",
            accentColor = accentColor
        )

        AppNotificationType.QueueCompleted -> NotificationTone(
            icon = Icons.Filled.CheckCircle,
            label = "Concluído",
            accentColor = accentColor
        )

        AppNotificationType.QueueLeft -> NotificationTone(
            icon = Icons.Filled.CheckCircle,
            label = "Saida",
            accentColor = accentColor
        )

        AppNotificationType.QueueCancelled -> NotificationTone(
            icon = Icons.Filled.Cancel,
            label = "Cancelada",
            accentColor = accentColor
        )

        AppNotificationType.QueueNoShow -> NotificationTone(
            icon = Icons.Filled.ErrorOutline,
            label = "Ausencia",
            accentColor = accentColor
        )

        AppNotificationType.QueueRequeued -> NotificationTone(
            icon = Icons.Filled.Refresh,
            label = "Retorno",
            accentColor = accentColor
        )

        AppNotificationType.QueueUpdated -> NotificationTone(
            icon = Icons.Filled.NotificationsActive,
            label = "Atualizada",
            accentColor = accentColor
        )
    }
}

private fun contextLabel(type: NotificationContextType): String {
    return when (type) {
        NotificationContextType.QueueEntry -> "Fila"
        NotificationContextType.Appointment -> "Agendamento"
    }
}

private fun detailScreenTitle(uiState: NotificationDetailsUiState): String {
    return when (uiState) {
        NotificationDetailsUiState.Idle,
        NotificationDetailsUiState.Loading -> "Fluxo da fila"
        NotificationDetailsUiState.Empty -> "Fluxo da fila"
        is NotificationDetailsUiState.Error -> "Fluxo da fila"
        is NotificationDetailsUiState.Loaded -> uiState.group.contextTitle
    }
}

private fun formatNotificationTime(value: Long): String {
    return SimpleDateFormat("HH:mm", Locale.forLanguageTag("pt-BR")).format(Date(value))
}

private fun formatNotificationDateTime(value: Long): String {
    return SimpleDateFormat("dd/MM - HH:mm", Locale.forLanguageTag("pt-BR")).format(Date(value))
}

private data class NotificationTone(
    val icon: ImageVector,
    val label: String,
    val accentColor: Color
)
