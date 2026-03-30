package br.dev.pulz.queuemaster.mobile.navigation

import android.Manifest
import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import android.content.pm.PackageManager
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.core.content.ContextCompat
import androidx.core.app.NotificationManagerCompat
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import br.dev.pulz.queuemaster.mobile.core.utils.AppPreferencesStore
import br.dev.pulz.queuemaster.mobile.core.design.AppSpacing
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.NotificationsActive
import br.dev.pulz.queuemaster.mobile.features.joinqueue.JoinQueueScreen
import br.dev.pulz.queuemaster.mobile.features.joinqueue.JoinQueueUiState
import br.dev.pulz.queuemaster.mobile.features.joinqueue.JoinQueueViewModel
import br.dev.pulz.queuemaster.mobile.features.joinqueue.QrScannerScreen
import br.dev.pulz.queuemaster.mobile.features.login.GoogleSignInManager
import br.dev.pulz.queuemaster.mobile.features.login.GoogleSignInResult
import br.dev.pulz.queuemaster.mobile.features.login.LoginScreen
import br.dev.pulz.queuemaster.mobile.features.login.LoginUiState
import br.dev.pulz.queuemaster.mobile.features.login.LoginViewModel
import br.dev.pulz.queuemaster.mobile.features.manualcode.ManualCodeEntryScreen
import br.dev.pulz.queuemaster.mobile.features.manualcode.ManualCodeEntryViewModel
import br.dev.pulz.queuemaster.mobile.features.manualcode.ManualCodeUiState
import br.dev.pulz.queuemaster.mobile.features.notifications.NotificationDetailsScreen
import br.dev.pulz.queuemaster.mobile.features.notifications.NotificationsScreen
import br.dev.pulz.queuemaster.mobile.features.notifications.NotificationsUiState
import br.dev.pulz.queuemaster.mobile.features.notifications.NotificationsViewModel
import br.dev.pulz.queuemaster.mobile.features.profile.ProfileScreen
import br.dev.pulz.queuemaster.mobile.features.profile.ProfileUiState
import br.dev.pulz.queuemaster.mobile.features.profile.ProfileViewModel
import br.dev.pulz.queuemaster.mobile.features.queuestatus.QueueStatusScreen
import br.dev.pulz.queuemaster.mobile.features.queuestatus.QueueStatusUiState
import br.dev.pulz.queuemaster.mobile.features.queuestatus.QueueStatusViewModel
import br.dev.pulz.queuemaster.mobile.features.settings.SettingsScreen
import br.dev.pulz.queuemaster.mobile.features.settings.SettingsViewModel
import kotlinx.coroutines.currentCoroutineContext
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

@Composable
fun AppNavHost(
    navController: NavHostController,
    pendingJoinPayload: String?,
    onJoinPayloadConsumed: () -> Unit,
    pendingAppRoute: String?,
    onPendingAppRouteConsumed: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val activity = remember(context) { context.findActivity() }
    val coroutineScope = rememberCoroutineScope()
    val googleSignInManager = remember(context) {
        GoogleSignInManager(context = context.applicationContext)
    }

    val loginViewModel: LoginViewModel = viewModel()
    val joinQueueViewModel: JoinQueueViewModel = viewModel()
    val manualCodeEntryViewModel: ManualCodeEntryViewModel = viewModel()
    val queueStatusViewModel: QueueStatusViewModel = viewModel()
    val profileViewModel: ProfileViewModel = viewModel()
    val settingsViewModel: SettingsViewModel = viewModel()
    val notificationsViewModel: NotificationsViewModel = viewModel()

    val loginUiState by loginViewModel.uiState.collectAsStateWithLifecycle()
    val joinQueueUiState by joinQueueViewModel.uiState.collectAsStateWithLifecycle()
    val manualCodeUiState by manualCodeEntryViewModel.uiState.collectAsStateWithLifecycle()
    val queueStatusUiState by queueStatusViewModel.uiState.collectAsStateWithLifecycle()
    val queueStatusIsRefreshing by queueStatusViewModel.isRefreshing.collectAsStateWithLifecycle()
    val queueStatusLastUpdatedAt by queueStatusViewModel.lastUpdatedAt.collectAsStateWithLifecycle()
    val profileUiState by profileViewModel.uiState.collectAsStateWithLifecycle()
    val settingsThemeMode by settingsViewModel.themeMode.collectAsStateWithLifecycle()
    val settingsSystemNotificationsEnabled by settingsViewModel.systemNotificationsEnabled.collectAsStateWithLifecycle()
    val notificationsPromptHandled by AppPreferencesStore.notificationsPromptHandled.collectAsStateWithLifecycle()
    val notificationsUiState by notificationsViewModel.uiState.collectAsStateWithLifecycle()
    val currentBackStackEntry by navController.currentBackStackEntryAsState()
    val authenticatedUser = (loginUiState as? LoginUiState.Authenticated)?.user
    val currentRoute = currentBackStackEntry?.destination?.route
    val headerAvatarUrl = (profileUiState as? ProfileUiState.Loaded)?.profile?.avatarUrl ?: authenticatedUser?.avatarUrl
    val headerUserName = (profileUiState as? ProfileUiState.Loaded)?.profile?.fullName ?: authenticatedUser?.name
    val headerUserEmail = (profileUiState as? ProfileUiState.Loaded)?.profile?.email ?: authenticatedUser?.email
    var pendingLeaveNavigation by remember { mutableStateOf(false) }
    var pendingNotificationsToggle by remember { mutableStateOf(false) }
    var showNotificationsPrompt by remember { mutableStateOf(false) }

    val notificationPermissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission()
    ) { granted ->
        settingsViewModel.setSystemNotificationsEnabled(granted)
        AppPreferencesStore.setNotificationsPromptHandled(true)
        pendingNotificationsToggle = false
        showNotificationsPrompt = false
    }

    val handleProfileNavigation = remember(navController) {
        {
            navController.navigate(AppRoute.Profile.route) {
                launchSingleTop = true
            }
        }
    }

    val signOutAndReset = remember(
        coroutineScope,
        googleSignInManager,
        joinQueueViewModel,
        manualCodeEntryViewModel,
        queueStatusViewModel,
        profileViewModel,
        notificationsViewModel,
        navController,
        loginViewModel
    ) {
        {
            coroutineScope.launch {
                googleSignInManager.clearCredentialState()
            }
            loginViewModel.signOut()
            joinQueueViewModel.reset()
            manualCodeEntryViewModel.reset()
            queueStatusViewModel.clear()
            profileViewModel.clear()
            notificationsViewModel.showUser(null)
            navController.navigate(AppRoute.Login.route) {
                popUpTo(navController.graph.id) {
                    inclusive = true
                }
            }
        }
    }

    LaunchedEffect(authenticatedUser?.id) {
        val authenticatedState = loginUiState as? LoginUiState.Authenticated ?: return@LaunchedEffect
        val hasActiveQueue = runCatching {
            queueStatusViewModel.restoreOrFetchActiveSession(
                authenticatedUserId = authenticatedState.user.id
            )
        }.getOrElse { throwable ->
            if (throwable is br.dev.pulz.queuemaster.mobile.core.network.ApiException && throwable.statusCode == 401) {
                signOutAndReset()
                return@LaunchedEffect
            }
            queueStatusViewModel.hasActiveQueueSession()
        }
        profileViewModel.showAuthenticatedUser(
            user = authenticatedState.user
        )
        profileViewModel.refreshProfile()
        notificationsViewModel.showUser(authenticatedState.user.id)
        val destination = if (hasActiveQueue) {
            AppRoute.QueueStatus.route
        } else {
            AppRoute.JoinQueue.route
        }
        if (navController.currentBackStackEntry?.destination?.route != destination) {
            navController.navigate(destination) {
                popUpTo(AppRoute.Login.route) {
                    inclusive = true
                }
            }
        }
    }

    LaunchedEffect(authenticatedUser?.id, pendingAppRoute) {
        if (authenticatedUser == null || pendingAppRoute.isNullOrBlank()) return@LaunchedEffect
        navController.navigate(pendingAppRoute) {
            launchSingleTop = true
        }
        onPendingAppRouteConsumed()
    }

    LaunchedEffect(
        authenticatedUser?.id,
        currentRoute,
        notificationsPromptHandled,
        settingsSystemNotificationsEnabled
    ) {
        if (authenticatedUser == null) {
            showNotificationsPrompt = false
            return@LaunchedEffect
        }
        if (currentRoute == null || currentRoute == AppRoute.Login.route) {
            return@LaunchedEffect
        }

        val systemPermissionGranted = context.hasNotificationsPermission()
        if (settingsSystemNotificationsEnabled && systemPermissionGranted) {
            AppPreferencesStore.setNotificationsPromptHandled(true)
            showNotificationsPrompt = false
            return@LaunchedEffect
        }

        showNotificationsPrompt = !notificationsPromptHandled
    }

    LaunchedEffect(authenticatedUser?.id, pendingJoinPayload) {
        if (authenticatedUser == null || pendingJoinPayload.isNullOrBlank()) return@LaunchedEffect

        navController.navigate(AppRoute.JoinQueue.route) {
            launchSingleTop = true
        }
        joinQueueViewModel.joinFromQrPayload(pendingJoinPayload)
        onJoinPayloadConsumed()
    }

    LaunchedEffect(joinQueueUiState) {
        val success = joinQueueUiState as? JoinQueueUiState.Success ?: return@LaunchedEffect
        val authenticatedUserId = authenticatedUser?.id ?: return@LaunchedEffect
        queueStatusViewModel.showJoinedQueue(
            result = success.result,
            authenticatedUserId = authenticatedUserId
        )
        joinQueueViewModel.reset()
        navController.navigate(AppRoute.QueueStatus.route) {
            popUpTo(AppRoute.JoinQueue.route)
        }
    }

    LaunchedEffect(manualCodeUiState) {
        val success = manualCodeUiState as? ManualCodeUiState.Success ?: return@LaunchedEffect
        val authenticatedUserId = authenticatedUser?.id ?: return@LaunchedEffect
        queueStatusViewModel.showJoinedQueue(
            result = success.result,
            authenticatedUserId = authenticatedUserId
        )
        manualCodeEntryViewModel.reset()
        navController.navigate(AppRoute.QueueStatus.route) {
            popUpTo(AppRoute.JoinQueue.route)
        }
    }

    LaunchedEffect(queueStatusUiState, pendingLeaveNavigation) {
        if (!pendingLeaveNavigation) return@LaunchedEffect

        when (queueStatusUiState) {
            QueueStatusUiState.NoActiveQueue -> {
                pendingLeaveNavigation = false
                navController.navigate(AppRoute.JoinQueue.route) {
                    popUpTo(AppRoute.JoinQueue.route) {
                        inclusive = true
                    }
                }
            }

            is QueueStatusUiState.Error -> {
                pendingLeaveNavigation = false
            }

            else -> Unit
        }
    }

    NavHost(
        navController = navController,
        startDestination = AppRoute.Login.route,
        modifier = modifier
    ) {
        composable(AppRoute.Login.route) {
            LoginScreen(
                onContinue = {
                    if (activity == null) {
                        loginViewModel.onGoogleSignInError(
                            message = "Nao foi possivel iniciar o login neste contexto."
                        )
                    } else {
                        coroutineScope.launch {
                            when (val result = googleSignInManager.requestIdToken(activity)) {
                                is GoogleSignInResult.Success -> {
                                    loginViewModel.submitGoogleIdToken(
                                        idToken = result.idToken
                                    )
                                }

                                is GoogleSignInResult.Error -> {
                                    loginViewModel.onGoogleSignInError(
                                        message = result.message
                                    )
                                }

                                GoogleSignInResult.Cancelled -> Unit
                            }
                        }
                    }
                },
                isLoading = loginUiState is LoginUiState.Loading,
                errorMessage = (loginUiState as? LoginUiState.Error)?.message
            )
        }

        composable(AppRoute.JoinQueue.route) {
            JoinQueueScreen(
                avatarUrl = headerAvatarUrl,
                onManualCodeClick = {
                    if (queueStatusViewModel.hasActiveQueueSession()) {
                        navController.navigate(AppRoute.QueueStatus.route) {
                            launchSingleTop = true
                        }
                    } else {
                        navController.navigate(AppRoute.ManualCodeEntry.route)
                    }
                },
                onQueueStatusClick = {
                    joinQueueViewModel.reset()
                    if (queueStatusViewModel.hasActiveQueueSession()) {
                        navController.navigate(AppRoute.QueueStatus.route) {
                            launchSingleTop = true
                        }
                    } else {
                        navController.navigate(AppRoute.QrScanner.route)
                    }
                },
                onProfileClick = handleProfileNavigation,
                isJoining = joinQueueUiState is JoinQueueUiState.Loading,
                errorMessage = (joinQueueUiState as? JoinQueueUiState.Error)?.message
            )
        }

        composable(AppRoute.QrScanner.route) {
            QrScannerScreen(
                avatarUrl = headerAvatarUrl,
                isJoining = joinQueueUiState is JoinQueueUiState.Loading,
                errorMessage = (joinQueueUiState as? JoinQueueUiState.Error)?.message,
                onAvatarClick = handleProfileNavigation,
                onBackClick = {
                    joinQueueViewModel.reset()
                    navController.popBackStack()
                },
                onPayloadScanned = joinQueueViewModel::joinFromQrPayload,
                onError = joinQueueViewModel::showError
            )
        }

        composable(AppRoute.ManualCodeEntry.route) {
            ManualCodeEntryScreen(
                avatarUrl = headerAvatarUrl,
                accessCode = manualCodeEntryViewModel.currentAccessCode(),
                onAccessCodeChange = manualCodeEntryViewModel::updateAccessCode,
                onAvatarClick = handleProfileNavigation,
                onBackClick = { navController.popBackStack() },
                onContinue = manualCodeEntryViewModel::submit,
                isLoading = manualCodeUiState is ManualCodeUiState.Loading,
                errorMessage = (manualCodeUiState as? ManualCodeUiState.Error)?.message
            )
        }

        composable(AppRoute.QueueStatus.route) {
            LaunchedEffect(Unit) {
                if (queueStatusViewModel.hasActiveQueueSession()) {
                    queueStatusViewModel.refresh(
                        showLoading = queueStatusUiState !is QueueStatusUiState.Active
                    )
                }

                while (currentCoroutineContext().isActive && queueStatusViewModel.hasActiveQueueSession()) {
                    delay(20_000)
                    if (queueStatusViewModel.hasActiveQueueSession()) {
                        queueStatusViewModel.refresh(showLoading = false)
                    }
                }
            }

            QueueStatusScreen(
                avatarUrl = headerAvatarUrl,
                uiState = queueStatusUiState,
                isRefreshing = queueStatusIsRefreshing,
                lastUpdatedAt = queueStatusLastUpdatedAt,
                onRefreshClick = {
                    queueStatusViewModel.refresh(showLoading = false)
                },
                onLeaveQueueClick = {
                    pendingLeaveNavigation = true
                    queueStatusViewModel.leaveQueue()
                },
                onJoinQueueClick = {
                    navController.navigate(AppRoute.JoinQueue.route)
                },
                onProfileClick = handleProfileNavigation
            )
        }

        composable(AppRoute.Notifications.route) {
            NotificationsScreen(
                avatarUrl = headerAvatarUrl,
                uiState = notificationsUiState,
                onAvatarClick = handleProfileNavigation,
                onMarkAllReadClick = notificationsViewModel::markAllRead,
                onGroupClick = { contextKey ->
                    notificationsViewModel.markGroupRead(contextKey)
                    navController.navigate(AppRoute.NotificationDetails.createRoute(contextKey))
                }
            )
        }

        composable(
            route = AppRoute.NotificationDetails.route,
            arguments = listOf(
                navArgument("contextKey") {
                    type = NavType.StringType
                }
            )
        ) { backStackEntry ->
            val contextKey = backStackEntry.arguments?.getString("contextKey").orEmpty()
            val group = remember(contextKey, notificationsUiState) {
                notificationsViewModel.groupForContext(contextKey)
            }

            NotificationDetailsScreen(
                avatarUrl = headerAvatarUrl,
                group = group,
                onAvatarClick = handleProfileNavigation,
                onBackClick = navController::popBackStack,
                onOpenQueueClick = {
                    navController.navigate(AppRoute.QueueStatus.route) {
                        launchSingleTop = true
                    }
                }
            )
        }

        composable(AppRoute.Settings.route) {
            SettingsScreen(
                avatarUrl = headerAvatarUrl,
                userName = headerUserName,
                userEmail = headerUserEmail,
                themeMode = settingsThemeMode,
                systemNotificationsEnabled = settingsSystemNotificationsEnabled,
                onAvatarClick = handleProfileNavigation,
                onProfileClick = handleProfileNavigation,
                onThemeModeSelected = settingsViewModel::setThemeMode,
                onSystemNotificationsToggle = { enabled ->
                    if (!enabled) {
                        settingsViewModel.setSystemNotificationsEnabled(false)
                        AppPreferencesStore.setNotificationsPromptHandled(true)
                    } else if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
                        settingsViewModel.setSystemNotificationsEnabled(true)
                        AppPreferencesStore.setNotificationsPromptHandled(true)
                    } else if (
                        ContextCompat.checkSelfPermission(
                            context,
                            Manifest.permission.POST_NOTIFICATIONS
                        ) == PackageManager.PERMISSION_GRANTED
                    ) {
                        settingsViewModel.setSystemNotificationsEnabled(true)
                        AppPreferencesStore.setNotificationsPromptHandled(true)
                    } else if (!pendingNotificationsToggle) {
                        pendingNotificationsToggle = true
                        notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
                    }
                },
                onSignOutClick = signOutAndReset
            )
        }

        composable(AppRoute.Profile.route) {
            ProfileScreen(
                avatarUrl = headerAvatarUrl,
                uiState = profileUiState,
                onAvatarClick = null,
                onSignOutClick = signOutAndReset
            )
        }
    }

    if (showNotificationsPrompt && authenticatedUser != null && currentRoute != AppRoute.Login.route) {
        NotificationsPromptDialog(
            onDismiss = {
                settingsViewModel.setSystemNotificationsEnabled(false)
                AppPreferencesStore.setNotificationsPromptHandled(true)
                showNotificationsPrompt = false
            },
            onEnable = {
                if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
                    settingsViewModel.setSystemNotificationsEnabled(true)
                    AppPreferencesStore.setNotificationsPromptHandled(true)
                    showNotificationsPrompt = false
                } else if (context.hasNotificationsPermission()) {
                    settingsViewModel.setSystemNotificationsEnabled(true)
                    AppPreferencesStore.setNotificationsPromptHandled(true)
                    showNotificationsPrompt = false
                } else if (!pendingNotificationsToggle) {
                    pendingNotificationsToggle = true
                    notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
                }
            }
        )
    }
}

private tailrec fun Context.findActivity(): Activity? {
    return when (this) {
        is Activity -> this
        is ContextWrapper -> baseContext.findActivity()
        else -> null
    }
}

private fun Context.hasNotificationsPermission(): Boolean {
    return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
        ContextCompat.checkSelfPermission(
            this,
            Manifest.permission.POST_NOTIFICATIONS
        ) == PackageManager.PERMISSION_GRANTED
    } else {
        NotificationManagerCompat.from(this).areNotificationsEnabled()
    }
}

@Composable
private fun NotificationsPromptDialog(
    onDismiss: () -> Unit,
    onEnable: () -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        icon = {
            Icon(
                imageVector = Icons.Filled.NotificationsActive,
                contentDescription = null
            )
        },
        title = {
            Text(text = "Ativar notificacoes?")
        },
        text = {
            Text(
                text = "O QueueMaster pode avisar quando sua vez estiver chegando, quando voce for chamado e quando o atendimento for concluido. Se preferir, voce pode deixar para ativar depois em Ajustes."
            )
        },
        confirmButton = {
            TextButton(onClick = onEnable) {
                Text(text = "Ativar agora")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text(text = "Agora nao")
            }
        }
    )
}
