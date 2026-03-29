package br.dev.pulz.queuemaster.mobile.features.queuestatus

import androidx.compose.foundation.BorderStroke
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
import androidx.compose.material.icons.filled.Person
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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import br.dev.pulz.queuemaster.mobile.core.design.AppGradients
import br.dev.pulz.queuemaster.mobile.core.design.AppSpacing
import br.dev.pulz.queuemaster.mobile.ui.components.QmSecondaryButton
import br.dev.pulz.queuemaster.mobile.ui.theme.Cloud0
import br.dev.pulz.queuemaster.mobile.ui.theme.Ink900
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@Composable
fun QueueStatusScreen(
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
            .background(AppGradients.ScreenGlow)
            .statusBarsPadding()
            .verticalScroll(rememberScrollState())
            .padding(AppSpacing.Xl),
        verticalArrangement = Arrangement.spacedBy(AppSpacing.Lg)
    ) {
        QueueStatusHeader(
            isRefreshing = isRefreshing,
            onRefreshClick = onRefreshClick,
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

                QueueStatusTitle(
                    queueName = queueStatus.queue.name,
                    queuePlace = queueStatus.queue.establishmentName
                )

                QueuePositionCard(
                    statusLabel = userEntry?.status ?: "waiting",
                    position = userEntry?.position,
                    estimatedWaitMinutes = userEntry?.estimatedWaitMinutes,
                    peopleAhead = userEntry?.peopleAhead ?: 0
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
                        label = "Entrada",
                        value = userEntry?.joinedAt ?: "Agora"
                    )
                }

                QueueNotificationCard(
                    lastUpdatedLabel = lastUpdatedLabel
                )

                QmSecondaryButton(
                    text = "Sair da fila",
                    onClick = onLeaveQueueClick
                )

                Text(
                    text = "Ao sair da fila, sua posicao e sua estimativa de atendimento serao perdidas.",
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
    isRefreshing: Boolean,
    onRefreshClick: () -> Unit,
    onProfileClick: () -> Unit
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Surface(
            shape = MaterialTheme.shapes.medium,
            color = MaterialTheme.colorScheme.surface,
            border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)
        ) {
            Box(
                modifier = Modifier.size(44.dp),
                contentAlignment = Alignment.Center
            ) {
                if (isRefreshing) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(18.dp),
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
        }

        Text(
            text = "QueueMaster",
            style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold),
            color = MaterialTheme.colorScheme.onBackground,
            modifier = Modifier
                .weight(1f)
                .padding(start = AppSpacing.Md)
        )

        Surface(
            onClick = onProfileClick,
            shape = MaterialTheme.shapes.medium,
            color = Ink900
        ) {
            Box(
                modifier = Modifier.size(44.dp),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Filled.Person,
                    contentDescription = "Abrir perfil",
                    tint = Cloud0
                )
            }
        }
    }
}

@Composable
private fun QueueStatusTitle(
    queueName: String,
    queuePlace: String
) {
    Column(
        verticalArrangement = Arrangement.spacedBy(AppSpacing.Xs)
    ) {
        Text(
            text = queueName,
            style = MaterialTheme.typography.headlineLarge,
            color = MaterialTheme.colorScheme.onBackground
        )

        Row(
            verticalAlignment = Alignment.CenterVertically
        ) {
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
    statusLabel: String,
    position: Int?,
    estimatedWaitMinutes: Int?,
    peopleAhead: Int
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
                statusLabel = statusLabel
            )

            Text(
                text = "SUA POSICAO",
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(top = AppSpacing.Xl)
            )

            Text(
                text = position?.toString() ?: "--",
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
                    label = "TEMPO ESTIMADO",
                    value = estimatedWaitMinutes?.let { "$it min" } ?: "A definir"
                )
                QueueMetricColumn(
                    modifier = Modifier.weight(1f),
                    label = "A SUA FRENTE",
                    value = "$peopleAhead pessoas"
                )
            }
        }
    }
}

@Composable
private fun QueueStatusBadge(
    statusLabel: String
) {
    Surface(
        shape = MaterialTheme.shapes.medium,
        color = MaterialTheme.colorScheme.primary.copy(alpha = 0.08f)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = AppSpacing.Md, vertical = AppSpacing.Xs),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(8.dp)
                    .background(
                        color = MaterialTheme.colorScheme.primary,
                        shape = MaterialTheme.shapes.small
                    )
            )
            Text(
                text = statusLabel.uppercase(),
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.primary,
                modifier = Modifier.padding(start = AppSpacing.Xs)
            )
        }
    }
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
                    text = lastUpdatedLabel?.let {
                        "Sua posicao e o tempo estimado sao atualizados automaticamente. Ultima atualizacao as $it."
                    } ?: "Sua posicao e o tempo estimado sao atualizados automaticamente enquanto voce acompanha a fila.",
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
