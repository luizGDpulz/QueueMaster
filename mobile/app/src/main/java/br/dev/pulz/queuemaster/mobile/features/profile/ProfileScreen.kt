package br.dev.pulz.queuemaster.mobile.features.profile

import androidx.compose.foundation.background
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.automirrored.filled.NavigateNext
import androidx.compose.material.icons.filled.AlternateEmail
import androidx.compose.material.icons.filled.Language
import androidx.compose.material.icons.filled.NotificationsActive
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.PhoneIphone
import androidx.compose.material.icons.filled.Shield
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import br.dev.pulz.queuemaster.mobile.core.design.AppGradients
import br.dev.pulz.queuemaster.mobile.core.design.AppSpacing
import br.dev.pulz.queuemaster.mobile.ui.components.QmAvatar
import br.dev.pulz.queuemaster.mobile.ui.components.QmBrandTopBar
import br.dev.pulz.queuemaster.mobile.ui.components.QmSectionTitle
import br.dev.pulz.queuemaster.mobile.ui.theme.Cloud0
import br.dev.pulz.queuemaster.mobile.ui.theme.Error500

@Composable
fun ProfileScreen(
    avatarUrl: String?,
    uiState: ProfileUiState,
    onAvatarClick: (() -> Unit)?,
    onSignOutClick: () -> Unit,
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
        ProfileHeader(
            avatarUrl = avatarUrl,
            onAvatarClick = onAvatarClick
        )

        when (uiState) {
            ProfileUiState.Loading -> ProfileStateCard(
                title = "Carregando perfil",
                description = "Buscando seus dados e preferencias salvas."
            )
            is ProfileUiState.Error -> ProfileStateCard(
                title = "Não foi possível carregar o perfil",
                description = uiState.message
            )
            is ProfileUiState.Loaded -> {
                val profile = uiState.profile

                ProfileIdentity(
                    name = profile.fullName,
                    email = profile.email,
                    avatarUrl = profile.avatarUrl
                )

                QmSectionTitle(
                    text = "Informações pessoais"
                )

                Surface(
                    shape = MaterialTheme.shapes.large,
                    color = MaterialTheme.colorScheme.surface,
                    tonalElevation = 2.dp,
                    shadowElevation = 10.dp
                ) {
                    Column(
                        modifier = Modifier.padding(vertical = AppSpacing.Sm)
                    ) {
                        ProfileInfoRow(
                            icon = Icons.Filled.Person,
                            label = "Nome completo",
                            value = profile.fullName
                        )
                        ProfileInfoRow(
                            icon = Icons.Filled.AlternateEmail,
                            label = "Email",
                            value = profile.email
                        )
                        ProfileInfoRow(
                            icon = Icons.Filled.PhoneIphone,
                            label = "Telefone",
                            value = profile.phoneNumber ?: "Não informado",
                            showDivider = false
                        )
                    }
                }

                QmSectionTitle(
                    text = "Configurações da conta"
                )

                Surface(
                    shape = MaterialTheme.shapes.large,
                    color = MaterialTheme.colorScheme.surface,
                    tonalElevation = 2.dp,
                    shadowElevation = 10.dp
                ) {
                    Column(
                        modifier = Modifier.padding(vertical = AppSpacing.Sm)
                    ) {
                        ProfileSettingRow(
                            icon = Icons.Filled.Shield,
                            title = "Privacidade e seguranca"
                        )
                        ProfileSettingRow(
                            icon = Icons.Filled.NotificationsActive,
                            title = "Notificações"
                        )
                        ProfileSettingRow(
                            icon = Icons.Filled.Language,
                            title = "Idioma",
                            trailingValue = profile.preferredLanguage,
                            showDivider = false
                        )
                    }
                }

                SignOutButton(
                    onClick = onSignOutClick
                )
            }
        }
    }
}

@Composable
private fun ProfileHeader(
    avatarUrl: String?,
    onAvatarClick: (() -> Unit)?
) {
    QmBrandTopBar(
        avatarUrl = avatarUrl,
        onAvatarClick = onAvatarClick
    )
}

@Composable
private fun ProfileIdentity(
    name: String,
    email: String,
    avatarUrl: String?
) {
    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        QmAvatar(
            imageUrl = avatarUrl,
            contentDescription = "Foto de perfil",
            size = 116.dp
        )

        Text(
            text = name,
            style = MaterialTheme.typography.headlineSmall,
            color = MaterialTheme.colorScheme.onBackground,
            textAlign = TextAlign.Center,
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = AppSpacing.Xl)
        )
        Text(
            text = email,
            style = MaterialTheme.typography.bodyLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = AppSpacing.Xs)
        )
    }
}

@Composable
private fun ProfileInfoRow(
    icon: ImageVector,
    label: String,
    value: String,
    modifier: Modifier = Modifier,
    showDivider: Boolean = true
) {
    ProfileRowContainer(
        modifier = modifier,
        icon = icon,
        iconBrush = Brush.linearGradient(
            colors = listOf(
                MaterialTheme.colorScheme.surfaceVariant,
                MaterialTheme.colorScheme.primaryContainer
            )
        ),
        label = label,
        value = value,
        trailingValue = null,
        showDivider = showDivider
    )
}

@Composable
private fun ProfileSettingRow(
    icon: ImageVector,
    title: String,
    modifier: Modifier = Modifier,
    trailingValue: String? = null,
    showDivider: Boolean = true
) {
    ProfileRowContainer(
        modifier = modifier,
        icon = icon,
        iconBrush = Brush.linearGradient(
            colors = listOf(
                MaterialTheme.colorScheme.surfaceVariant,
                MaterialTheme.colorScheme.surface
            )
        ),
        label = null,
        value = title,
        trailingValue = trailingValue,
        showDivider = showDivider
    )
}

@Composable
private fun ProfileRowContainer(
    icon: ImageVector,
    iconBrush: Brush,
    value: String,
    modifier: Modifier = Modifier,
    label: String? = null,
    trailingValue: String? = null,
    showDivider: Boolean = true
) {
    Column(
        modifier = modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = AppSpacing.Lg, vertical = AppSpacing.Md),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Surface(
                shape = MaterialTheme.shapes.medium,
                color = Color.Transparent
            ) {
                Box(
                    modifier = Modifier
                        .size(42.dp)
                        .background(
                            brush = iconBrush,
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
            }

            Column(
                modifier = Modifier
                    .weight(1f)
                    .padding(start = AppSpacing.Md)
            ) {
                if (label != null) {
                    Text(
                        text = label.uppercase(),
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
                Text(
                    text = value,
                    style = MaterialTheme.typography.titleMedium,
                    color = MaterialTheme.colorScheme.onSurface,
                    modifier = Modifier.padding(top = if (label != null) AppSpacing.Xxs else 0.dp)
                )
            }

            if (trailingValue != null) {
                Text(
                    text = trailingValue,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(end = AppSpacing.Xs)
                )
            }

            Icon(
                imageVector = Icons.AutoMirrored.Filled.NavigateNext,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }

        if (showDivider) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = AppSpacing.Lg)
                    .height(1.dp)
                    .background(color = MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.45f))
            )
        }
    }
}

@Composable
private fun ProfileStateCard(
    title: String,
    description: String
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
private fun SignOutButton(
    onClick: () -> Unit
) {
    Surface(
        onClick = onClick,
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surfaceVariant
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = AppSpacing.Lg),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = Icons.AutoMirrored.Filled.Logout,
                contentDescription = null,
                tint = Error500
            )
            Text(
                text = "Sair da conta",
                style = MaterialTheme.typography.titleMedium,
                color = Error500,
                modifier = Modifier.padding(start = AppSpacing.Sm)
            )
        }
    }
}
