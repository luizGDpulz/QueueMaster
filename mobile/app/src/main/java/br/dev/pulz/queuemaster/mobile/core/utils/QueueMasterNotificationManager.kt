package br.dev.pulz.queuemaster.mobile.core.utils

import android.Manifest
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.drawable.Drawable
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import br.dev.pulz.queuemaster.mobile.MainActivity
import br.dev.pulz.queuemaster.mobile.R
import br.dev.pulz.queuemaster.mobile.core.model.AppNotificationItem
import br.dev.pulz.queuemaster.mobile.core.model.AppNotificationType
import br.dev.pulz.queuemaster.mobile.core.model.NotificationContextType
import br.dev.pulz.queuemaster.mobile.core.model.QueueUserEntry

object QueueMasterNotificationManager {
    private const val ChannelId = "qm_queue_updates_realtime"
    private const val ChannelName = "Atualizações da fila"
    private const val ChannelDescription = "Avisos do QueueMaster sobre sua fila e atendimento."

    fun initialize(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return

        val notificationManager = context.getSystemService(NotificationManager::class.java) ?: return
        val channel = NotificationChannel(
            ChannelId,
            ChannelName,
            NotificationManager.IMPORTANCE_HIGH
        ).apply {
            description = ChannelDescription
            enableVibration(true)
            lockscreenVisibility = Notification.VISIBILITY_PUBLIC
        }
        notificationManager.createNotificationChannel(channel)
    }

    fun notifyQueueJoined(
        userId: Int,
        queueId: Int,
        entryPublicId: String?,
        queueName: String?,
        flowKey: String? = null
    ) {
        push(
            context = AppRuntime.context(),
            notification = buildEvent(
                userId = userId,
                queueId = queueId,
                entryPublicId = entryPublicId,
                queueName = queueName,
                flowKey = flowKey,
                type = AppNotificationType.QueueJoined,
                title = "Entrada confirmada",
                body = "Você entrou na fila${queueName?.let { " $it" } ?: ""}."
            )
        )
    }

    fun notifyQueueLeft(
        userId: Int,
        queueId: Int,
        entryPublicId: String?,
        queueName: String?,
        flowKey: String? = null
    ) {
        push(
            context = AppRuntime.context(),
            notification = buildEvent(
                userId = userId,
                queueId = queueId,
                entryPublicId = entryPublicId,
                queueName = queueName,
                flowKey = flowKey,
                type = AppNotificationType.QueueLeft,
                title = "Saida da fila",
                body = "Sua entrada foi encerrada${queueName?.let { " em $it" } ?: ""}."
            )
        )
    }

    fun notifyQueueProgress(
        userId: Int,
        queueId: Int,
        currentEntry: QueueUserEntry,
        previousEntry: QueueUserEntry?,
        queueName: String,
        flowKey: String? = null
    ) {
        if (previousEntry == null) return

        val currentStatus = currentEntry.status.lowercase()
        val previousStatus = previousEntry.status.lowercase()

        when {
            currentStatus == "called" && previousStatus != "called" -> {
                push(
                    context = AppRuntime.context(),
                    notification = buildEvent(
                        userId = userId,
                        queueId = queueId,
                        entryPublicId = currentEntry.entryPublicId,
                        queueName = queueName,
                        flowKey = flowKey,
                        type = AppNotificationType.QueueCalled,
                        title = "Você foi chamado",
                        body = "Dirija-se ao atendimento${queueName.let { " em $it" }}."
                    )
                )
            }

            currentStatus == "serving" && previousStatus != "serving" -> {
                push(
                    context = AppRuntime.context(),
                    notification = buildEvent(
                        userId = userId,
                        queueId = queueId,
                        entryPublicId = currentEntry.entryPublicId,
                        queueName = queueName,
                        flowKey = flowKey,
                        type = AppNotificationType.QueueServing,
                        title = "Atendimento iniciado",
                        body = "Seu atendimento${queueName.let { " em $it" }} já comecou."
                    )
                )
            }

            currentStatus == "waiting" &&
                currentEntry.peopleAhead <= 1 &&
                previousEntry.peopleAhead > 1 -> {
                push(
                    context = AppRuntime.context(),
                    notification = buildEvent(
                        userId = userId,
                        queueId = queueId,
                        entryPublicId = currentEntry.entryPublicId,
                        queueName = queueName,
                        flowKey = flowKey,
                        type = AppNotificationType.QueueNext,
                        title = "Você e o pr?ximo",
                        body = "Falta pouco para seu atendimento${queueName.let { " em $it" }}."
                    )
                )
            }
        }
    }

    fun notifyQueueCompleted(
        userId: Int,
        queueId: Int,
        entryPublicId: String?,
        queueName: String?,
        flowKey: String? = null
    ) {
        push(
            context = AppRuntime.context(),
            notification = buildEvent(
                userId = userId,
                queueId = queueId,
                entryPublicId = entryPublicId,
                queueName = queueName,
                flowKey = flowKey,
                type = AppNotificationType.QueueCompleted,
                title = "Atendimento concluído",
                body = "O fluxo${queueName?.let { " em $it" } ?: ""} foi finalizado."
            )
        )
    }

    private fun buildEvent(
        userId: Int,
        queueId: Int,
        entryPublicId: String?,
        queueName: String?,
        flowKey: String? = null,
        type: AppNotificationType,
        title: String,
        body: String
    ): AppNotificationItem {
        val contextKey = flowKey ?: buildQueueNotificationFlowKey(
            entryPublicId = entryPublicId,
            queueId = queueId
        )
        return AppNotificationItem(
            id = "${contextKey}_${type.name.lowercase()}_${System.currentTimeMillis()}",
            userId = userId,
            type = type,
            contextType = NotificationContextType.QueueEntry,
            contextKey = contextKey,
            contextTitle = queueName ?: "Fila #$queueId",
            contextSubtitle = "Histórico da participação na fila",
            queueId = queueId,
            title = title,
            body = body,
            createdAt = System.currentTimeMillis(),
            isRead = false
        )
    }

    private fun push(
        context: Context,
        notification: AppNotificationItem
    ) {
        AppNotificationStore.add(notification)

        if (!AppPreferencesStore.systemNotificationsEnabled.value) return
        if (!NotificationManagerCompat.from(context).areNotificationsEnabled()) return
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            return
        }

        val pendingIntent = PendingIntent.getActivity(
            context,
            notification.id.hashCode(),
            Intent(context, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
                putExtra(MainActivity.ExtraDestinationRoute, MainActivity.NotificationsRoute)
            },
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val systemNotification = NotificationCompat.Builder(context, ChannelId)
            .setSmallIcon(R.drawable.ic_notification_qm)
            .setLargeIcon(context.loadNotificationLargeIcon())
            .setContentTitle(notification.title)
            .setContentText(notification.body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(notification.body))
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_REMINDER)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setDefaults(NotificationCompat.DEFAULT_ALL)
            .build()

        NotificationManagerCompat.from(context).notify(notification.id.hashCode(), systemNotification)
    }
}

fun buildQueueNotificationFlowKey(
    entryPublicId: String?,
    queueId: Int,
    joinedAt: String? = null
): String {
    entryPublicId
        ?.trim()
        ?.takeIf { it.isNotBlank() }
        ?.let { resolvedEntryPublicId ->
            return "queue_entry_${resolvedEntryPublicId.lowercase()}"
        }

    val joinedAtKey = joinedAt
        ?.filter { it.isLetterOrDigit() }
        ?.takeIf { it.isNotBlank() }

    return if (joinedAtKey != null) {
        "queue_flow_${queueId}_$joinedAtKey"
    } else {
        "queue_flow_${queueId}_${System.currentTimeMillis()}"
    }
}

private fun Context.loadNotificationLargeIcon(): Bitmap? {
    val drawable = ContextCompat.getDrawable(this, R.drawable.qm_app_icon) ?: return null
    val sizeInPx = (64 * resources.displayMetrics.density).toInt().coerceAtLeast(1)
    return drawable.toBitmap(sizeInPx, sizeInPx)
}

private fun Drawable.toBitmap(width: Int, height: Int): Bitmap {
    val bitmap = Bitmap.createBitmap(
        width.coerceAtLeast(1),
        height.coerceAtLeast(1),
        Bitmap.Config.ARGB_8888
    )
    val canvas = Canvas(bitmap)
    setBounds(0, 0, canvas.width, canvas.height)
    draw(canvas)
    return bitmap
}
