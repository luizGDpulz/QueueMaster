package br.dev.pulz.queuemaster.mobile.features.manualcode

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Password
import androidx.compose.material.icons.filled.QrCode2
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import br.dev.pulz.queuemaster.mobile.core.design.AppGradients
import br.dev.pulz.queuemaster.mobile.core.design.AppSpacing
import br.dev.pulz.queuemaster.mobile.core.utils.PreviewData
import br.dev.pulz.queuemaster.mobile.ui.components.QmBrandTopBar
import br.dev.pulz.queuemaster.mobile.ui.components.QmCard
import br.dev.pulz.queuemaster.mobile.ui.components.QmPrimaryButton
import br.dev.pulz.queuemaster.mobile.ui.components.QmSecondaryButton
import br.dev.pulz.queuemaster.mobile.ui.components.QmTextField

@Composable
fun ManualCodeEntryScreen(
    avatarUrl: String?,
    accessCode: String,
    onAccessCodeChange: (String) -> Unit,
    onAvatarClick: () -> Unit,
    onBackClick: () -> Unit,
    onContinue: () -> Unit,
    isLoading: Boolean,
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
                text = "Digite o código da fila",
                style = MaterialTheme.typography.headlineSmall,
                color = MaterialTheme.colorScheme.onBackground,
                modifier = Modifier.padding(start = AppSpacing.Xs)
            )
        }

        QmCard {
            Icon(
                imageVector = Icons.Filled.Password,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary
            )
            Text(
                text = "Você s? precisa do código",
                style = MaterialTheme.typography.titleMedium,
                color = MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.padding(top = AppSpacing.Md)
            )
            Text(
                text = "Digite o código de entrada e confirme para seguir direto para o acompanhamento da fila.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(top = AppSpacing.Xs)
            )
            QmTextField(
                value = accessCode,
                onValueChange = onAccessCodeChange,
                label = "Código de entrada",
                isError = errorMessage != null,
                supportingText = errorMessage,
                modifier = Modifier.padding(top = AppSpacing.Xl)
            )

            QmCard(
                modifier = Modifier.padding(top = AppSpacing.Lg)
            ) {
                Icon(
                    imageVector = Icons.Filled.QrCode2,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary
                )
                Text(
                    text = "Onde encontrar o código",
                    style = MaterialTheme.typography.titleSmall,
                    color = MaterialTheme.colorScheme.onSurface,
                    modifier = Modifier.padding(top = AppSpacing.Md)
                )
                Text(
                    text = "O código costuma aparecer no cartaz, ao lado do QR code ou na recepção. Exemplo: ${PreviewData.SampleAccessCode}.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(top = AppSpacing.Xs)
                )
            }

            QmPrimaryButton(
                text = "Confirmar código",
                onClick = onContinue,
                enabled = accessCode.isNotBlank(),
                loading = isLoading,
                modifier = Modifier.padding(top = AppSpacing.Xl)
            )
            QmSecondaryButton(
                text = "Voltar",
                onClick = onBackClick,
                modifier = Modifier.padding(top = AppSpacing.Sm)
            )
        }
    }
}
