package br.dev.pulz.queuemaster.mobile.navigation

import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.Modifier
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
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
import br.dev.pulz.queuemaster.mobile.features.profile.ProfileScreen
import br.dev.pulz.queuemaster.mobile.features.profile.ProfileUiState
import br.dev.pulz.queuemaster.mobile.features.profile.ProfileViewModel
import br.dev.pulz.queuemaster.mobile.features.queuestatus.QueueStatusScreen
import br.dev.pulz.queuemaster.mobile.features.queuestatus.QueueStatusUiState
import br.dev.pulz.queuemaster.mobile.features.queuestatus.QueueStatusViewModel
import kotlinx.coroutines.currentCoroutineContext
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

@Composable
fun AppNavHost(
    navController: NavHostController,
    pendingJoinPayload: String?,
    onJoinPayloadConsumed: () -> Unit,
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

    val loginUiState by loginViewModel.uiState.collectAsStateWithLifecycle()
    val joinQueueUiState by joinQueueViewModel.uiState.collectAsStateWithLifecycle()
    val manualCodeUiState by manualCodeEntryViewModel.uiState.collectAsStateWithLifecycle()
    val queueStatusUiState by queueStatusViewModel.uiState.collectAsStateWithLifecycle()
    val queueStatusIsRefreshing by queueStatusViewModel.isRefreshing.collectAsStateWithLifecycle()
    val queueStatusLastUpdatedAt by queueStatusViewModel.lastUpdatedAt.collectAsStateWithLifecycle()
    val profileUiState by profileViewModel.uiState.collectAsStateWithLifecycle()
    val authenticatedUser = (loginUiState as? LoginUiState.Authenticated)?.user
    var pendingLeaveNavigation by remember { mutableStateOf(false) }

    LaunchedEffect(authenticatedUser?.id) {
        val authenticatedState = loginUiState as? LoginUiState.Authenticated ?: return@LaunchedEffect
        queueStatusViewModel.restorePersistedSession(
            authenticatedUserId = authenticatedState.user.id
        )
        profileViewModel.showAuthenticatedUser(
            user = authenticatedState.user
        )
        profileViewModel.refreshProfile()
        val destination = if (queueStatusViewModel.hasActiveQueueSession()) {
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
            LaunchedEffect(authenticatedUser?.id) {
                if (authenticatedUser != null) {
                    navController.navigate(AppRoute.JoinQueue.route) {
                        popUpTo(AppRoute.Login.route) {
                            inclusive = true
                        }
                    }
                }
            }

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
                onManualCodeClick = { navController.navigate(AppRoute.ManualCodeEntry.route) },
                onQueueStatusClick = {
                    joinQueueViewModel.reset()
                    navController.navigate(AppRoute.QrScanner.route)
                },
                onProfileClick = { navController.navigate(AppRoute.Profile.route) },
                isJoining = joinQueueUiState is JoinQueueUiState.Loading,
                errorMessage = (joinQueueUiState as? JoinQueueUiState.Error)?.message
            )
        }

        composable(AppRoute.QrScanner.route) {
            QrScannerScreen(
                isJoining = joinQueueUiState is JoinQueueUiState.Loading,
                errorMessage = (joinQueueUiState as? JoinQueueUiState.Error)?.message,
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
                accessCode = manualCodeEntryViewModel.currentAccessCode(),
                onAccessCodeChange = manualCodeEntryViewModel::updateAccessCode,
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
                onProfileClick = { navController.navigate(AppRoute.Profile.route) }
            )
        }

        composable(AppRoute.Profile.route) {
            ProfileScreen(
                uiState = profileUiState,
                onSignOutClick = {
                    coroutineScope.launch {
                        googleSignInManager.clearCredentialState()
                    }
                    loginViewModel.signOut()
                    joinQueueViewModel.reset()
                    manualCodeEntryViewModel.reset()
                    queueStatusViewModel.clear()
                    profileViewModel.clear()
                    navController.navigate(AppRoute.Login.route) {
                        popUpTo(navController.graph.id) {
                            inclusive = true
                        }
                    }
                }
            )
        }
    }
}

private tailrec fun Context.findActivity(): Activity? {
    return when (this) {
        is Activity -> this
        is ContextWrapper -> baseContext.findActivity()
        else -> null
    }
}
