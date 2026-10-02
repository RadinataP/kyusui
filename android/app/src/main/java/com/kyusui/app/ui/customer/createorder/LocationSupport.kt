package com.kyusui.app.ui.customer.createorder

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.location.LocationManager
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.platform.LocalContext
import androidx.core.content.ContextCompat
import com.kyusui.app.domain.model.GeoPoint

const val LOCATION_PERMISSION = Manifest.permission.ACCESS_FINE_LOCATION
const val COARSE_LOCATION_PERMISSION = Manifest.permission.ACCESS_COARSE_LOCATION

/**
 * Pembungkus permintaan izin lokasi runtime.
 *
 * Android menggunakan `LocationManager` dari platform, tanpa dependency
 * lokasi pihak ketiga. Jika izin ditolak atau lokasi tidak tersedia, status
 * dikembalikan ke ViewModel supaya UI bisa menjelaskan kondisinya dengan
 * jujur dan customer tidak unknowingly mengirim koordinat yang salah.
 */
class LocationRequestState internal constructor(
    internal val launcher: () -> Unit
) {
    /** True bila izin lokasi sudah diberikan sebelumnya. */
    fun isGranted(context: Context): Boolean =
        ContextCompat.checkSelfPermission(context, LOCATION_PERMISSION) ==
            PackageManager.PERMISSION_GRANTED ||
            ContextCompat.checkSelfPermission(context, COARSE_LOCATION_PERMISSION) ==
            PackageManager.PERMISSION_GRANTED
}

@Composable
fun rememberLocationRequestState(
    onPermissionDenied: () -> Unit,
    onPermissionGranted: () -> Unit = {}
): LocationRequestState {
    val context = LocalContext.current
    val launcher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestMultiplePermissions()
    ) { result ->
        val granted = result[LOCATION_PERMISSION] == true ||
            result[COARSE_LOCATION_PERMISSION] == true
        // Callback granted wajib dipanggil: status "Locating" diset sebelum
        // permintaan, jadi tanpa ini UI akan menggantung di status tersebut.
        if (granted) onPermissionGranted() else onPermissionDenied()
    }

    return remember(context) {
        LocationRequestState(
            launcher = {
                launcher.launch(
                    arrayOf(LOCATION_PERMISSION, COARSE_LOCATION_PERMISSION)
                )
            }
        )
    }
}

/**
 * Membaca koordinat terakhir yang diketahui perangkat.
 *
 * Mengembalikan null bila izin belum diberikan atau tidak ada provider yang
 * dapat dibaca. Nilai yang dikembalikan adalah nilai perangkat, bukan
 * estimasi dari backend.
 */
fun readLastKnownLocation(context: Context): GeoPoint? {
    val hasFine = ContextCompat.checkSelfPermission(context, LOCATION_PERMISSION) ==
        PackageManager.PERMISSION_GRANTED
    val hasCoarse = ContextCompat.checkSelfPermission(context, COARSE_LOCATION_PERMISSION) ==
        PackageManager.PERMISSION_GRANTED
    if (!hasFine && !hasCoarse) return null

    val locationManager = context.getSystemService(Context.LOCATION_SERVICE)
        as? LocationManager
        ?: return null

    val providers = buildList {
        if (hasFine) add(LocationManager.GPS_PROVIDER)
        if (hasCoarse) add(LocationManager.NETWORK_PROVIDER)
    }

    return providers.mapNotNull { provider ->
        runCatching { locationManager.getLastKnownLocation(provider) }.getOrNull()
    }.maxByOrNull { it.time }
        ?.let { location -> GeoPoint(location.latitude, location.longitude) }
}