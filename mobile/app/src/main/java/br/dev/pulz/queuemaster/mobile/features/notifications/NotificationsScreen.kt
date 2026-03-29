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
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.NotificationsActive
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
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
    onMarkAllReadClick: () -> Unit,
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
            NotificationsUiState.Loading -> NotificationsLoadingCard()
            NotificationsUiState.Empty -> NotificationsEmptyCard()
            is NotificationsUiState.Loaded -> {
                val unreadCount = uiState.groups.sumOf { it.unreadCount }

                NotificationSummaryStrip(
                    unreadCount = unreadCount,
                    onMarkAllReadClick = onMarkAllReadClick
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
    group: AppNotificationGroup?,
    onAvatarClick: () -> Unit,
    onBackClick: () -> Unit,
    onOpenQueueClick: (Int) -> Unit,
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
                text = group?.contextTitle ?: "Fluxo de notificacoes",
                style = MaterialTheme.typography.headlineSmall,
                color = MaterialTheme.colorScheme.onBackground,
                modifier = Modifier.padding(start = AppSpacing.Xs)
            )
        }

        if (group == null) {
            NotificationsEmptyCard(
                title = "Nao encontramos esse fluxo",
                description = "Volte para a lista de notificacoes para escolher outro grupo."
            )
            return@Column
        }

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(AppSpacing.Sm),
            verticalAlignment = Alignment.CenterVertically
        ) {
            NotificationContextPill(contextType = group.contextType)
            QmPill(
                text = "${group.events.size} evento(s)",
                containerColor = MaterialTheme.colorScheme.surfaceVariant,
                contentColor = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }

        group.queueId?.let { queueId ->
            QmPrimaryButton(
                text = "Abrir fila relacionada",
                onClick = { onOpenQueueClick(queueId) }
            )
        }

        group.events.sortedBy { it.createdAt }.forEachIndexed { index, event ->
            NotificationTimelineCard(
                event = event,
                isLast = index == group.events.lastIndex
            )
        }
    }
}

@Composable
private fun NotificationsLoadingCard() {
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
                text = "Carregando alertas",
                style = MaterialTheme.typography.titleLarge,
                color = MaterialTheme.colorScheme.onSurface
            )
            Text(
                text = "Organizando os grupos e a linha do tempo das suas notificacoes.",
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
        eyebrow = "Notificacoes"
    )
}

@Composable
private fun NotificationSummaryStrip(
    unreadCount: Int,
    onMarkAllReadClick: () -> Unit
) {
    Surface(
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surfaceVariant
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = AppSpacing.Lg, vertical = AppSpacing.Md),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(
                modifier = Modifier.weight(1f)
            ) {
                Text(
                    text = if (unreadCount > 0) {
                        if (unreadCount == 1) "1 atualizacao nova" else "$unreadCount atualizacoes novas"
                    } else {
                        "Tudo em dia"
                    },
                    style = MaterialTheme.typography.titleSmall,
                    color = MaterialTheme.colorScheme.onSurface
                )
                Text(
                    text = if (unreadCount > 0) {
                        "Ao abrir um fluxo, os destaques dele deixam de ficar pendentes."
                    } else {
                        "Novos eventos vao aparecer aqui automaticamente conforme sua fila avancar."
                    },
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(top = AppSpacing.Xxs)
                )
            }

            if (unreadCount > 0) {
                TextButton(onClick = onMarkAllReadClick) {
                    Text(
                        text = "Ler tudo",
                        color = MaterialTheme.colorScheme.primary
                    )
                }
            }
        }
    }
}

@Composable
private fun NotificationGroupCard(
    group: AppNotificationGroup,
    onClick: () -> Unit
) {
    val borderColor = if (group.unreadCount > 0) {
        MaterialTheme.colorScheme.primary.copy(alpha = 0.12f)
    } else {
        MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.65f)
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

                if (group.unreadCount > 0) {
                    QmPill(
                        text = "${group.unreadCount} novas",
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                        contentColor = MaterialTheme.colorScheme.primary
                    )
                }
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(AppSpacing.Sm)
            ) {
                NotificationContextPill(contextType = group.contextType)
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
                    text = "${group.events.size} evento(s) neste fluxo",
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
    event: AppNotificationItem,
    isLast: Boolean
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
            if (!isLast) {
                Text(
                    text = "Aguardando o proximo evento deste fluxo...",
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.primary
                )
            }
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
private fun notificationTone(type: AppNotificationType): NotificationTone {
    val isDarkTheme = MaterialTheme.colorScheme.background.luminance() < 0.5f
    val accentColor = when (type) {
        AppNotificationType.QueueJoined,
        AppNotificationType.QueueCompleted -> if (isDarkTheme) Success400 else Success500

        AppNotificationType.QueueNext -> if (isDarkTheme) Warning400 else Warning500
        AppNotificationType.QueueCalled,
        AppNotificationType.QueueServing -> if (isDarkTheme) Info400 else Info500

        AppNotificationType.QueueLeft -> MaterialTheme.colorScheme.onSurfaceVariant
    }

    return when (type) {
        AppNotificationType.QueueJoined -> NotificationTone(
            icon = Icons.Filled.CheckCircle,
            label = "Entrada",
            accentColor = accentColor
        )

        AppNotificationType.QueueNext -> NotificationTone(
            icon = Icons.Filled.Schedule,
            label = "Proximo",
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
            label = "Concluido",
            accentColor = accentColor
        )

        AppNotificationType.QueueLeft -> NotificationTone(
            icon = Icons.Filled.CheckCircle,
            label = "Saida",
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
