package br.dev.pulz.queuemaster.mobile.features.joinqueue

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Handler
import android.os.Looper
import android.provider.Settings
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageProxy
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.CameraAlt
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import androidx.lifecycle.compose.LocalLifecycleOwner
import br.dev.pulz.queuemaster.mobile.core.design.AppGradients
import br.dev.pulz.queuemaster.mobile.core.design.AppSpacing
import br.dev.pulz.queuemaster.mobile.ui.components.QmBrandTopBar
import br.dev.pulz.queuemaster.mobile.ui.components.QmPlaceholderState
import com.google.mlkit.vision.barcode.BarcodeScannerOptions
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.common.InputImage
import java.util.concurrent.Executors
import java.util.concurrent.atomic.AtomicBoolean

@Composable
fun QrScannerScreen(
    avatarUrl: String?,
    isJoining: Boolean,
    errorMessage: String?,
    onAvatarClick: () -> Unit,
    onBackClick: () -> Unit,
    onPayloadScanned: (String) -> Unit,
    onError: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    var hasCameraPermission by remember {
        mutableStateOf(
            ContextCompat.checkSelfPermission(
                context,
                Manifest.permission.CAMERA
            ) == PackageManager.PERMISSION_GRANTED
        )
    }
    var permissionRequested by remember { mutableStateOf(hasCameraPermission) }
    val permissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission()
    ) { granted ->
        permissionRequested = true
        hasCameraPermission = granted
        if (!granted) {
            onError("Permita o acesso a camera para escanear o QR code da fila.")
        }
    }

    LaunchedEffect(hasCameraPermission, permissionRequested) {
        if (!hasCameraPermission && !permissionRequested) {
            permissionLauncher.launch(Manifest.permission.CAMERA)
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(brush = AppGradients.screenGlow())
            .statusBarsPadding()
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
                text = "Escanear QR code",
                style = MaterialTheme.typography.headlineSmall,
                color = MaterialTheme.colorScheme.onBackground,
                modifier = Modifier.padding(start = AppSpacing.Xs)
            )
        }

        if (hasCameraPermission) {
            QrScannerCameraCard(
                modifier = Modifier
                    .fillMaxWidth()
                    .weight(1f),
                isJoining = isJoining,
                errorMessage = errorMessage,
                onPayloadScanned = onPayloadScanned,
                onError = onError
            )
        } else {
            QrScannerPermissionState(
                showOpenSettings = permissionRequested,
                onGrantPermission = {
                    permissionLauncher.launch(Manifest.permission.CAMERA)
                },
                onOpenSettings = {
                    val intent = Intent(
                        Settings.ACTION_APPLICATION_DETAILS_SETTINGS,
                        Uri.fromParts("package", context.packageName, null)
                    )
                    context.startActivity(intent)
                },
                onBackClick = onBackClick,
                errorMessage = errorMessage
            )
        }
    }
}

@Composable
private fun QrScannerCameraCard(
    modifier: Modifier = Modifier,
    isJoining: Boolean,
    errorMessage: String?,
    onPayloadScanned: (String) -> Unit,
    onError: (String) -> Unit
) {
    Box(
        modifier = modifier
    ) {
        Surface(
            modifier = Modifier.fillMaxSize(),
            shape = MaterialTheme.shapes.extraLarge,
            tonalElevation = 0.dp,
            color = MaterialTheme.colorScheme.surface
        ) {
            Box(
                modifier = Modifier.fillMaxSize()
            ) {
                QrCameraPreview(
                    enabled = !isJoining,
                    onPayloadScanned = onPayloadScanned,
                    onError = onError,
                    modifier = Modifier.fillMaxSize()
                )

                QrScannerOverlay(
                    modifier = Modifier.fillMaxSize()
                )

                if (isJoining) {
                    Surface(
                        modifier = Modifier.fillMaxSize(),
                        color = MaterialTheme.colorScheme.scrim.copy(alpha = 0.58f)
                    ) {
                        Column(
                            modifier = Modifier.fillMaxSize(),
                            verticalArrangement = Arrangement.Center,
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            CircularProgressIndicator(
                                color = MaterialTheme.colorScheme.onPrimary
                            )
                            Text(
                                text = "Entrando na fila...",
                                style = MaterialTheme.typography.titleMedium,
                                color = MaterialTheme.colorScheme.onPrimary,
                                modifier = Modifier.padding(top = AppSpacing.Lg)
                            )
                        }
                    }
                }
            }
        }

        errorMessage?.let { message ->
            Surface(
                shape = MaterialTheme.shapes.large,
                color = MaterialTheme.colorScheme.error.copy(alpha = 0.92f),
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .padding(AppSpacing.Lg)
            ) {
                Text(
                    text = message,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onError,
                    modifier = Modifier.padding(AppSpacing.Md)
                )
            }
        }
    }
}

@Composable
private fun QrScannerOverlay(
    modifier: Modifier = Modifier
) {
    Box(modifier = modifier.padding(AppSpacing.Xl)) {
        Surface(
            shape = MaterialTheme.shapes.large,
            color = MaterialTheme.colorScheme.surface.copy(alpha = 0.88f),
            modifier = Modifier.align(Alignment.TopCenter)
        ) {
            Text(
                text = "Centralize o QR code dentro da moldura",
                style = MaterialTheme.typography.titleSmall,
                color = MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.padding(
                    horizontal = AppSpacing.Lg,
                    vertical = AppSpacing.Md
                )
            )
        }

        Box(
            modifier = Modifier
                .align(Alignment.Center)
                .fillMaxWidth()
                .aspectRatio(1f)
                .border(
                    width = 2.dp,
                    color = MaterialTheme.colorScheme.onPrimary,
                    shape = MaterialTheme.shapes.extraLarge
                )
        ) {
            Icon(
                imageVector = Icons.Filled.QrCodeScanner,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onPrimary.copy(alpha = 0.92f),
                modifier = Modifier
                    .align(Alignment.Center)
                    .size(44.dp)
            )
        }

        Surface(
            shape = MaterialTheme.shapes.large,
            color = MaterialTheme.colorScheme.surface.copy(alpha = 0.88f),
            modifier = Modifier.align(Alignment.BottomCenter)
        ) {
            Text(
                text = "O app reconhece automaticamente o link completo do QueueMaster.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(
                    horizontal = AppSpacing.Lg,
                    vertical = AppSpacing.Md
                )
            )
        }
    }
}

@Composable
private fun QrScannerPermissionState(
    showOpenSettings: Boolean,
    onGrantPermission: () -> Unit,
    onOpenSettings: () -> Unit,
    onBackClick: () -> Unit,
    errorMessage: String?
) {
    QmPlaceholderState(
        icon = Icons.Filled.CameraAlt,
        eyebrow = "Permissao",
        title = "Libere a camera para escanear",
        description = "A leitura do QR code precisa da camera do telefone para funcionar dentro do app.",
        primaryActionLabel = "Permitir camera",
        onPrimaryAction = onGrantPermission,
        secondaryActionLabel = if (showOpenSettings) "Abrir configuracoes" else "Voltar",
        onSecondaryAction = if (showOpenSettings) onOpenSettings else onBackClick
    ) {
        errorMessage?.let { message ->
            Surface(
                shape = MaterialTheme.shapes.large,
                color = MaterialTheme.colorScheme.error.copy(alpha = 0.08f),
                modifier = Modifier.padding(top = AppSpacing.Lg)
            ) {
                Text(
                    text = message,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.error,
                    modifier = Modifier.padding(AppSpacing.Md)
                )
            }
        }
    }
}

@Composable
private fun QrCameraPreview(
    enabled: Boolean,
    onPayloadScanned: (String) -> Unit,
    onError: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val previewView = remember {
        PreviewView(context).apply {
            scaleType = PreviewView.ScaleType.FILL_CENTER
        }
    }
    val cameraExecutor = remember { Executors.newSingleThreadExecutor() }
    val barcodeScanner = remember {
        BarcodeScanning.getClient(
            BarcodeScannerOptions.Builder()
                .setBarcodeFormats(Barcode.FORMAT_QR_CODE)
                .build()
        )
    }
    val didEmitResult = remember { AtomicBoolean(false) }

    DisposableEffect(lifecycleOwner, enabled, previewView) {
        val cameraProviderFuture = ProcessCameraProvider.getInstance(context)
        val mainExecutor = ContextCompat.getMainExecutor(context)

        val bindCamera = Runnable {
            runCatching {
                val cameraProvider = cameraProviderFuture.get()
                cameraProvider.unbindAll()

                if (!enabled) {
                    return@Runnable
                }

                val preview = Preview.Builder()
                    .build()
                    .also { it.surfaceProvider = previewView.surfaceProvider }

                val analysis = ImageAnalysis.Builder()
                    .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                    .build()
                    .also { useCase ->
                        useCase.setAnalyzer(
                            cameraExecutor,
                            QrCodeAnalyzer(
                                barcodeScanner = barcodeScanner,
                                didEmitResult = didEmitResult,
                                onPayloadScanned = onPayloadScanned
                            )
                        )
                    }

                cameraProvider.bindToLifecycle(
                    lifecycleOwner,
                    CameraSelector.DEFAULT_BACK_CAMERA,
                    preview,
                    analysis
                )
            }.onFailure {
                onError("Nao foi possivel iniciar a camera agora.")
            }
        }

        cameraProviderFuture.addListener(bindCamera, mainExecutor)

        onDispose {
            runCatching {
                cameraProviderFuture.get().unbindAll()
            }
        }
    }

    DisposableEffect(Unit) {
        onDispose {
            barcodeScanner.close()
            cameraExecutor.shutdown()
        }
    }

    AndroidView(
        factory = { previewView },
        modifier = modifier
    )
}

private class QrCodeAnalyzer(
    private val barcodeScanner: com.google.mlkit.vision.barcode.BarcodeScanner,
    private val didEmitResult: AtomicBoolean,
    private val onPayloadScanned: (String) -> Unit
) : ImageAnalysis.Analyzer {
    private val resetHandler = Handler(Looper.getMainLooper())

    override fun analyze(imageProxy: ImageProxy) {
        val mediaImage = imageProxy.image
        if (mediaImage == null || didEmitResult.get()) {
            imageProxy.close()
            return
        }

        val image = InputImage.fromMediaImage(
            mediaImage,
            imageProxy.imageInfo.rotationDegrees
        )

        barcodeScanner.process(image)
            .addOnSuccessListener { barcodes ->
                val payload = barcodes
                    .firstNotNullOfOrNull { barcode ->
                        barcode.rawValue
                            ?.trim()
                            ?.takeIf { it.isNotBlank() }
                    }

                if (payload != null && didEmitResult.compareAndSet(false, true)) {
                    onPayloadScanned(payload)
                    resetHandler.postDelayed(
                        { didEmitResult.set(false) },
                        1800L
                    )
                }
            }
            .addOnCompleteListener {
                imageProxy.close()
            }
    }
}
