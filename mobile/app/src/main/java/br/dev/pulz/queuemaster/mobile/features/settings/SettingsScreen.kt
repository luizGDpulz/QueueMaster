package br.dev.pulz.queuemaster.mobile.features.settings

import android.content.ActivityNotFoundException
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.automirrored.filled.NavigateNext
import androidx.compose.material.icons.filled.BatterySaver
import androidx.compose.material.icons.filled.DarkMode
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.NotificationsActive
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.SettingsBrightness
import androidx.compose.material.icons.filled.WbSunny
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.LocalLifecycleOwner
import br.dev.pulz.queuemaster.mobile.core.design.AppGradients
import br.dev.pulz.queuemaster.mobile.core.design.AppSpacing
import br.dev.pulz.queuemaster.mobile.core.model.AppThemeMode
import br.dev.pulz.queuemaster.mobile.core.utils.BatteryOptimizationHelper
import br.dev.pulz.queuemaster.mobile.ui.components.QmAvatar
import br.dev.pulz.queuemaster.mobile.ui.components.QmBrandTopBar
import br.dev.pulz.queuemaster.mobile.ui.components.QmPrimaryButton
import br.dev.pulz.queuemaster.mobile.ui.components.QmSectionTitle
import br.dev.pulz.queuemaster.mobile.ui.components.QmSecondaryButton

@Composable
fun SettingsScreen(
    avatarUrl: String?,
    userName: String?,
    userEmail: String?,
    themeMode: AppThemeMode,
    systemNotificationsEnabled: Boolean,
    onAvatarClick: () -> Unit,
    onProfileClick: () -> Unit,
    onThemeModeSelected: (AppThemeMode) -> Unit,
    onSystemNotificationsToggle: (Boolean) -> Unit,
    onSignOutClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    var batteryOptimizationIgnored by remember(context) {
        mutableStateOf(BatteryOptimizationHelper.isIgnoringBatteryOptimizations(context))
    }
    var showBatteryWhyDialog by remember { mutableStateOf(false) }

    DisposableEffect(lifecycleOwner, context) {
        val observer = LifecycleEventObserver { _, event ->
            if (event == Lifecycle.Event.ON_RESUME) {
                batteryOptimizationIgnored =
                    BatteryOptimizationHelper.isIgnoringBatteryOptimizations(context)
            }
        }

        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose {
            lifecycleOwner.lifecycle.removeObserver(observer)
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
        QmBrandTopBar(
            avatarUrl = avatarUrl,
            onAvatarClick = onAvatarClick
        )

        QmSectionTitle(text = "Conta")

        SettingsProfileCard(
            avatarUrl = avatarUrl,
            userName = userName ?: "Seu perfil",
            userEmail = userEmail ?: "Abra seu perfil para ver os dados",
            onClick = onProfileClick
        )

        QmSectionTitle(text = "Aparencia")

        Surface(
            shape = MaterialTheme.shapes.large,
            color = MaterialTheme.colorScheme.surface,
            tonalElevation = 2.dp,
            shadowElevation = 10.dp
        ) {
            Column(
                modifier = Modifier.padding(AppSpacing.Lg),
                verticalArrangement = Arrangement.spacedBy(AppSpacing.Md)
            ) {
                Text(
                    text = "Modo de exibicao",
                    style = MaterialTheme.typography.titleMedium,
                    color = MaterialTheme.colorScheme.onSurface
                )
                Text(
                    text = "Por padrao o app segue o tema do telefone, mas voce pode definir um modo manualmente.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )

                ThemeModeOption(
                    icon = Icons.Filled.SettingsBrightness,
                    title = "Seguir sistema",
                    description = "Usa o mesmo tema configurado no aparelho.",
                    isSelected = themeMode == AppThemeMode.System,
                    onClick = { onThemeModeSelected(AppThemeMode.System) }
                )
                ThemeModeOption(
                    icon = Icons.Filled.WbSunny,
                    title = "Modo claro",
                    description = "Superficies claras e alto contraste para ambientes iluminados.",
                    isSelected = themeMode == AppThemeMode.Light,
                    onClick = { onThemeModeSelected(AppThemeMode.Light) }
                )
                ThemeModeOption(
                    icon = Icons.Filled.DarkMode,
                    title = "Modo escuro",
                    description = "Visual escuro completo para reduzir brilho e manter foco.",
                    isSelected = themeMode == AppThemeMode.Dark,
                    onClick = { onThemeModeSelected(AppThemeMode.Dark) }
                )
            }
        }

        QmSectionTitle(text = "Notificacoes")

        Surface(
            shape = MaterialTheme.shapes.large,
            color = MaterialTheme.colorScheme.surface,
            tonalElevation = 2.dp,
            shadowElevation = 10.dp
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(AppSpacing.Lg),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Surface(
                    shape = MaterialTheme.shapes.medium,
                    color = MaterialTheme.colorScheme.surfaceVariant
                ) {
                    Box(
                        modifier = Modifier.size(42.dp),
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
                        text = "Notificacoes do telefone",
                        style = MaterialTheme.typography.titleMedium,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Text(
                        text = "Receba avisos do QueueMaster fora do app quando sua fila avancar.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(top = AppSpacing.Xs)
                    )
                }

                Switch(
                    checked = systemNotificationsEnabled,
                    onCheckedChange = onSystemNotificationsToggle
                )
            }
        }

        Surface(
            shape = MaterialTheme.shapes.large,
            color = MaterialTheme.colorScheme.surface,
            tonalElevation = 2.dp,
            shadowElevation = 10.dp,
            border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.8f))
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(AppSpacing.Lg),
                verticalArrangement = Arrangement.spacedBy(AppSpacing.Md)
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Surface(
                        shape = MaterialTheme.shapes.medium,
                        color = MaterialTheme.colorScheme.surfaceVariant
                    ) {
                        Box(
                            modifier = Modifier.size(42.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = Icons.Filled.BatterySaver,
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
                            text = "Atividade em segundo plano",
                            style = MaterialTheme.typography.titleMedium,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                        Text(
                            text = if (batteryOptimizationIgnored) {
                                "O QueueMaster ja pode ficar mais livre para continuar checando sua fila quando estiver minimizado."
                            } else {
                                "Se o Android economizar bateria demais, o app pode parar de consultar a fila quando estiver em segundo plano."
                            },
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.padding(top = AppSpacing.Xs)
                        )
                    }
                }

                if (batteryOptimizationIgnored) {
                    QmSecondaryButton(
                        text = "Economia ja desativada",
                        onClick = {
                            runCatching {
                                context.startActivity(
                                    BatteryOptimizationHelper.buildBatteryOptimizationSettingsIntent()
                                )
                            }
                        }
                    )
                } else {
                    QmPrimaryButton(
                        text = "Desativar economia de bateria",
                        onClick = {
                            runCatching {
                                context.startActivity(
                                    BatteryOptimizationHelper.buildDisableBatteryOptimizationIntent(context)
                                )
                            }.recoverCatching {
                                context.startActivity(
                                    BatteryOptimizationHelper.buildBatteryOptimizationSettingsIntent()
                                )
                            }
                        }
                    )
                }

                TextButton(
                    onClick = { showBatteryWhyDialog = true }
                ) {
                    Icon(
                        imageVector = Icons.Filled.Info,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary
                    )
                    Text(
                        text = "Por que desativar?",
                        color = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.padding(start = AppSpacing.Xs)
                    )
                }
            }
        }

        Surface(
            onClick = onSignOutClick,
            shape = MaterialTheme.shapes.large,
            color = MaterialTheme.colorScheme.surfaceVariant
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = AppSpacing.Lg, vertical = AppSpacing.Lg),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    imageVector = Icons.AutoMirrored.Filled.Logout,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.error
                )
                Text(
                    text = "Sair da conta",
                    style = MaterialTheme.typography.titleMedium,
                    color = MaterialTheme.colorScheme.error,
                    modifier = Modifier.padding(start = AppSpacing.Sm)
                )
            }
        }
    }

    if (showBatteryWhyDialog) {
        AlertDialog(
            onDismissRequest = { showBatteryWhyDialog = false },
            title = {
                Text(text = "Por que isso ajuda?")
            },
            text = {
                Text(
                    text = "Quando o QueueMaster fica minimizado, o Android pode limitar rede e processamento em segundo plano para economizar bateria. Desativar essa otimizacao ajuda o app a continuar consultando sua fila e mostrar notificacoes como popup no momento certo. Isso e opcional: se preferir economizar bateria, voce pode manter a configuracao atual."
                )
            },
            confirmButton = {
                TextButton(onClick = { showBatteryWhyDialog = false }) {
                    Text(text = "Entendi")
                }
            }
        )
    }
}

@Composable
private fun SettingsProfileCard(
    avatarUrl: String?,
    userName: String,
    userEmail: String,
    onClick: () -> Unit
) {
    Surface(
        onClick = onClick,
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surface,
        tonalElevation = 2.dp,
        shadowElevation = 10.dp
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(AppSpacing.Lg),
            verticalAlignment = Alignment.CenterVertically
        ) {
            QmAvatar(
                imageUrl = avatarUrl,
                contentDescription = "Abrir perfil",
                size = 56.dp
            )

            Column(
                modifier = Modifier
                    .weight(1f)
                    .padding(start = AppSpacing.Md)
            ) {
                Text(
                    text = userName,
                    style = MaterialTheme.typography.titleMedium,
                    color = MaterialTheme.colorScheme.onSurface
                )
                Text(
                    text = userEmail,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(top = AppSpacing.Xs)
                )
            }

            Icon(
                imageVector = Icons.AutoMirrored.Filled.NavigateNext,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

@Composable
private fun ThemeModeOption(
    icon: ImageVector,
    title: String,
    description: String,
    isSelected: Boolean,
    onClick: () -> Unit
) {
    Surface(
        onClick = onClick,
        shape = MaterialTheme.shapes.large,
        color = if (isSelected) {
            MaterialTheme.colorScheme.primary.copy(alpha = 0.08f)
        } else {
            MaterialTheme.colorScheme.surfaceVariant
        }
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(AppSpacing.Md),
            verticalAlignment = Alignment.CenterVertically
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

            Column(
                modifier = Modifier
                    .weight(1f)
                    .padding(start = AppSpacing.Md)
            ) {
                Text(
                    text = title,
                    style = MaterialTheme.typography.titleSmall,
                    color = MaterialTheme.colorScheme.onSurface
                )
                Text(
                    text = description,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(top = AppSpacing.Xs)
                )
            }

            if (isSelected) {
                Text(
                    text = "Ativo",
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.primary
                )
            }
        }
    }
}
