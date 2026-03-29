package br.dev.pulz.queuemaster.mobile.features.manualcode

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Password
import androidx.compose.material.icons.filled.QrCode2
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import br.dev.pulz.queuemaster.mobile.core.design.AppGradients
import br.dev.pulz.queuemaster.mobile.core.design.AppSpacing
import br.dev.pulz.queuemaster.mobile.core.utils.PreviewData
import br.dev.pulz.queuemaster.mobile.ui.components.QmCard
import br.dev.pulz.queuemaster.mobile.ui.components.QmPrimaryButton
import br.dev.pulz.queuemaster.mobile.ui.components.QmSecondaryButton
import br.dev.pulz.queuemaster.mobile.ui.components.QmTextField
import br.dev.pulz.queuemaster.mobile.ui.components.QmTopBar

@Composable
fun ManualCodeEntryScreen(
    accessCode: String,
    onAccessCodeChange: (String) -> Unit,
    onBackClick: () -> Unit,
    onContinue: () -> Unit,
    isLoading: Boolean,
    errorMessage: String?,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier
            .fillMaxSize()
            .background(AppGradients.ScreenGlow)
            .statusBarsPadding()
            .verticalScroll(rememberScrollState())
            .padding(AppSpacing.Xl),
        verticalArrangement = Arrangement.spacedBy(AppSpacing.Lg)
    ) {
        QmTopBar(
            eyebrow = "Codigo da fila",
            title = "Digite o codigo para entrar",
            subtitle = "Use o codigo exibido pelo estabelecimento ou abaixo do QR code para entrar na fila."
        )

        QmCard {
            Icon(
                imageVector = Icons.Filled.Password,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary
            )
            Text(
                text = "Voce so precisa do codigo",
                style = MaterialTheme.typography.titleMedium,
                color = MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.padding(top = AppSpacing.Md)
            )
            Text(
                text = "Digite o codigo de entrada e confirme para seguir direto para o acompanhamento da fila.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(top = AppSpacing.Xs)
            )
            QmTextField(
                value = accessCode,
                onValueChange = onAccessCodeChange,
                label = "Codigo de entrada",
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
                    text = "Onde encontrar o codigo",
                    style = MaterialTheme.typography.titleSmall,
                    color = MaterialTheme.colorScheme.onSurface,
                    modifier = Modifier.padding(top = AppSpacing.Md)
                )
                Text(
                    text = "O codigo costuma aparecer no cartaz, ao lado do QR code ou na recepcao. Exemplo: ${PreviewData.SampleAccessCode}.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(top = AppSpacing.Xs)
                )
            }

            QmPrimaryButton(
                text = "Confirmar codigo",
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
