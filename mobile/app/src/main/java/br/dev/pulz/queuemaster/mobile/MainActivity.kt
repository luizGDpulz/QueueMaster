package br.dev.pulz.queuemaster.mobile

import android.content.Intent
import android.graphics.Color as AndroidColor
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.tooling.preview.Preview
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.core.view.WindowCompat
import br.dev.pulz.queuemaster.mobile.core.utils.AppPreferencesStore
import br.dev.pulz.queuemaster.mobile.ui.theme.QueueMasterMobileTheme
import br.dev.pulz.queuemaster.mobile.ui.theme.shouldUseDarkTheme

class MainActivity : ComponentActivity() {
    companion object {
        const val ExtraDestinationRoute = "destination_route"
        const val NotificationsRoute = "notifications"
    }

    private var pendingJoinPayload by mutableStateOf<String?>(null)
    private var pendingAppRoute by mutableStateOf<String?>(null)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        pendingJoinPayload = extractJoinPayload(intent)
        pendingAppRoute = extractPendingAppRoute(intent)
        enableEdgeToEdge()
        setContent {
            val themeMode by AppPreferencesStore.themeMode.collectAsStateWithLifecycle()
            val useDarkTheme = themeMode.shouldUseDarkTheme(
                systemInDarkTheme = isSystemInDarkTheme()
            )

            ConfigureSystemBars(
                darkTheme = useDarkTheme
            )

            QueueMasterMobileTheme(
                darkTheme = useDarkTheme
            ) {
                QueueMasterApp(
                    pendingJoinPayload = pendingJoinPayload,
                    onJoinPayloadConsumed = {
                        pendingJoinPayload = null
                    },
                    pendingAppRoute = pendingAppRoute,
                    onPendingAppRouteConsumed = {
                        pendingAppRoute = null
                    }
                )
            }
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        pendingJoinPayload = extractJoinPayload(intent)
        pendingAppRoute = extractPendingAppRoute(intent)
    }

    private fun extractJoinPayload(intent: Intent?): String? {
        return intent?.dataString
            ?.trim()
            ?.takeIf { it.isNotBlank() }
    }

    private fun extractPendingAppRoute(intent: Intent?): String? {
        return intent?.getStringExtra(ExtraDestinationRoute)
            ?.trim()
            ?.takeIf { it.isNotBlank() }
    }
}

@Composable
private fun MainActivity.ConfigureSystemBars(
    darkTheme: Boolean
) {
    SideEffect {
        window.statusBarColor = AndroidColor.TRANSPARENT
        window.navigationBarColor = AndroidColor.TRANSPARENT

        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.Q) {
            window.isStatusBarContrastEnforced = false
            window.isNavigationBarContrastEnforced = false
        }

        WindowCompat.getInsetsController(window, window.decorView).apply {
            isAppearanceLightStatusBars = !darkTheme
            isAppearanceLightNavigationBars = !darkTheme
        }
    }
}

@Composable
fun QueueMasterPreviewRoot(modifier: Modifier = Modifier) {
    QueueMasterApp(
        pendingJoinPayload = null,
        pendingAppRoute = null,
        onJoinPayloadConsumed = {},
        onPendingAppRouteConsumed = {},
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
