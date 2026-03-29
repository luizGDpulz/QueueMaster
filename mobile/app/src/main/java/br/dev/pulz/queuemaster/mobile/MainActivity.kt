package br.dev.pulz.queuemaster.mobile

import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.tooling.preview.Preview
import br.dev.pulz.queuemaster.mobile.ui.theme.QueueMasterMobileTheme

class MainActivity : ComponentActivity() {
    private var pendingJoinPayload by mutableStateOf<String?>(null)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        pendingJoinPayload = extractJoinPayload(intent)
        enableEdgeToEdge()
        setContent {
            QueueMasterMobileTheme {
                QueueMasterApp(
                    pendingJoinPayload = pendingJoinPayload,
                    onJoinPayloadConsumed = {
                        pendingJoinPayload = null
                    }
                )
            }
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        pendingJoinPayload = extractJoinPayload(intent)
    }

    private fun extractJoinPayload(intent: Intent?): String? {
        return intent?.dataString
            ?.trim()
            ?.takeIf { it.isNotBlank() }
    }
}

@Composable
fun QueueMasterPreviewRoot(modifier: Modifier = Modifier) {
    QueueMasterApp(
        pendingJoinPayload = null,
        onJoinPayloadConsumed = {},
        modifier = modifier
    )
}

@Preview(showBackground = true)
@Composable
fun QueueMasterPreview() {
    QueueMasterMobileTheme {
        QueueMasterPreviewRoot()
    }
}
