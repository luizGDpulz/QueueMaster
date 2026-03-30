package br.dev.pulz.queuemaster.mobile.core.network

import android.content.Context
import coil3.ImageLoader
import coil3.disk.DiskCache
import coil3.network.okhttp.OkHttpNetworkFetcherFactory
import okhttp3.OkHttpClient
import okio.Path.Companion.toOkioPath

object QueueMasterImageLoader {
    @Volatile
    private var imageLoader: ImageLoader? = null

    fun get(context: Context): ImageLoader {
        return imageLoader ?: synchronized(this) {
            imageLoader ?: buildImageLoader(context.applicationContext).also {
                imageLoader = it
            }
        }
    }

    private fun buildImageLoader(
        context: Context
    ): ImageLoader {
        return ImageLoader.Builder(context)
            .components {
                add(
                    OkHttpNetworkFetcherFactory(
                        callFactory = {
                            QueueMasterNetwork.newImageClient()
                        }
                    )
                )
            }
            .diskCache(
                DiskCache.Builder()
                    .directory(context.cacheDir.resolve("qm_image_cache").toOkioPath())
                    .maxSizeBytes(50L * 1024L * 1024L)
                    .build()
            )
            .build()
    }
}
