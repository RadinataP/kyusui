package com.kyusui.app.ui.customer.payment

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountBalanceWallet
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.HourglassTop
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.kyusui.app.core.Money
import com.kyusui.app.domain.model.PaymentMethod
import com.kyusui.app.domain.model.PaymentStatus

/**
 * Representasi visual payment status.
 *
 * Setiap status memakai tiga sinyal sekaligus: teks, icon, dan warna semantik.
 * Warna saja tidak pernah menjadi satu-satunya pembawa informasi, dan teksnya
 * ditulis dari nilai canonical backend, bukan dari tebakan client.
 */
data class PaymentStatusVisual(
    val label: String,
    val icon: ImageVector,
    val containerColor: Color,
    val contentColor: Color
)

@Composable
fun PaymentStatusVisual(
    status: PaymentStatus?,
    method: PaymentMethod?,
    unpaidMessage: String = "Belum ada pembayaran untuk order ini."
): PaymentStatusVisual {
    val colors = MaterialTheme.colorScheme
    return when (status) {
        PaymentStatus.PENDING -> if (method == PaymentMethod.CASH) {
            PaymentStatusVisual(
                label = "Belum dibayar (tunai)",
                icon = Icons.Filled.AccountBalanceWallet,
                containerColor = colors.tertiaryContainer,
                contentColor = colors.onTertiaryContainer
            )
        } else {
            PaymentStatusVisual(
                label = "Menunggu pembayaran",
                icon = Icons.Filled.HourglassTop,
                containerColor = colors.tertiaryContainer,
                contentColor = colors.onTertiaryContainer
            )
        }

        PaymentStatus.WAITING_VERIFICATION -> PaymentStatusVisual(
            label = "Menunggu verifikasi",
            icon = Icons.Filled.HourglassTop,
            containerColor = colors.secondaryContainer,
            contentColor = colors.onSecondaryContainer
        )

        PaymentStatus.PAID -> PaymentStatusVisual(
            label = "Sudah dibayar",
            icon = Icons.Filled.CheckCircle,
            containerColor = colors.primaryContainer,
            contentColor = colors.onPrimaryContainer
        )

        // Status tidak dikenal tidak ditebak menjadi status bisnis lain.
        null -> PaymentStatusVisual(
            label = unpaidMessage,
            icon = Icons.Filled.Payments,
            containerColor = colors.surfaceVariant,
            contentColor = colors.onSurfaceVariant
        )
    }
}

/**
 * Kartu status payment untuk sebuah order.
 *
 * `PAID` hanya pernah tampil bila backend melaporkan `PAID`, yaitu setelah Owner
 * memverifikasi QRIS atau Courier mengonfirmasi CASH.
 */
@Composable
fun PaymentStatusCard(
    status: PaymentStatus?,
    method: PaymentMethod?,
    modifier: Modifier = Modifier
) {
    val visual = PaymentStatusVisual(status = status, method = method)

    Card(
        modifier = modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = visual.containerColor)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.Start
        ) {
            Icon(
                imageVector = visual.icon,
                // Ikon ikut dibaca screen reader lewat deskripsi di bawah.
                contentDescription = null,
                tint = visual.contentColor,
                modifier = Modifier.size(28.dp)
            )
            Spacer(Modifier.width(12.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Status pembayaran",
                    style = MaterialTheme.typography.labelMedium,
                    color = visual.contentColor
                )
                Text(
                    text = visual.label,
                    style = MaterialTheme.typography.titleMedium,
                    color = visual.contentColor
                )
            }
            method?.let {
                Text(
                    text = it.displayName,
                    style = MaterialTheme.typography.labelLarge,
                    color = visual.contentColor
                )
            }
        }
    }
}

/**
 * Penjelasan singkat untuk status yang sedang aktif.
 *
 * Untuk `PENDING` QRIS, teksnya menyatakan secara eksplisit bahwa upload
 * bukti bukan berarti pembayaran sudah diverifikasi.
 */
@Composable
fun PaymentStatusExplanation(
    status: PaymentStatus?,
    method: PaymentMethod?,
    isRejectedProof: Boolean,
    modifier: Modifier = Modifier
) {
    val (title, body) = when (status) {
        PaymentStatus.PENDING -> when {
            isRejectedProof -> "Bukti sebelumnya ditolak" to
                "Pemilik toko menolak bukti pembayaran sebelumnya. Silakan " +
                "unggah ulang bukti yang lebih jelas. Bukti ini akan diperiksa " +
                "kembali oleh pemilik toko."

            method == PaymentMethod.QRIS -> "Selesaikan pembayaran QRIS" to
                "Scan QRIS di atas, lalu unggah bukti pembayaran. Bukti yang " +
                "diunggah akan diperiksa manual oleh pemilik toko."

            method == PaymentMethod.CASH -> "Bayar saat pesanan diterima" to
                "Siapkan uang tunai sesuai total pesanan. Pembayaran tunai " +
                "diterima langsung dari kurir saat barang diantar."

            else -> "Pilih metode pembayaran" to
                "Pilih QRIS atau tunai untuk melanjutkan pesanan."
        }

        PaymentStatus.WAITING_VERIFICATION -> "Bukti sedang diperiksa" to
            "Bukti pembayaran sudah diterima dan sedang diperiksa oleh pemilik " +
            "toko. Pembayaran baru dianggap selesai setelah disetujui."

        PaymentStatus.PAID -> "Pembayaran selesai" to
            "Pembayaran telah dikonfirmasi. Tidak ada tindakan lagi yang perlu " +
            "dilakukan."

        null -> "Belum ada pembayaran" to
            "Pesanan ini belum memiliki pembayaran. Pilih metode pembayaran " +
            "untuk melanjutkan."
    }

    Text(
        text = title,
        style = MaterialTheme.typography.titleMedium,
        modifier = modifier
    )
    Text(
        text = body,
        style = MaterialTheme.typography.bodyMedium,
        color = MaterialTheme.colorScheme.onSurfaceVariant
    )
}

/**
 * Ringkasan nominal payment.
 *
 * Nominal selalu berasal dari `payment.amount` yang diberikan backend, bukan
 * dari perhitungan ulang Android.
 */
@Composable
fun PaymentAmountRow(
    label: String,
    amount: String,
    modifier: Modifier = Modifier,
    isEmphasis: Boolean = false
) {
    Row(
        modifier = modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = label,
            style = if (isEmphasis) {
                MaterialTheme.typography.titleMedium
            } else {
                MaterialTheme.typography.bodyMedium
            }
        )
        Text(
            text = Money.format(amount),
            style = if (isEmphasis) {
                MaterialTheme.typography.titleMedium
            } else {
                MaterialTheme.typography.bodyMedium
            },
            color = if (isEmphasis) {
                MaterialTheme.colorScheme.primary
            } else {
                MaterialTheme.colorScheme.onSurface
            },
            textAlign = TextAlign.End
        )
    }
}

/**
 * Kotak informasi pembayaran tunai.
 *
 * Tidak ada aksi pembayaran di Android untuk CASH: konfirmasi "uang diterima"
 * dilakukan Courier di perangkatnya, dan `PAID` hanya muncul setelah backend
 * menerima konfirmasi tersebut.
 */
@Composable
fun CashPaymentInfoCard(modifier: Modifier = Modifier) {
    Card(
        modifier = modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.tertiaryContainer
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Filled.AccountBalanceWallet,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onTertiaryContainer
                )
                Spacer(Modifier.width(8.dp))
                Text(
                    text = "Pembayaran Tunai (Cash on Delivery)",
                    style = MaterialTheme.typography.titleSmall,
                    color = MaterialTheme.colorScheme.onTertiaryContainer
                )
            }

            CashInfoLine("Bayar kepada kurir saat barang diterima")
            CashInfoLine("Siapkan uang tunai sesuai total pesanan")
            CashInfoLine("Status \"sudah dibayar\" diisi setelah kurir mengonfirmasi")

            Text(
                text = "Anda tidak perlu mengunggah bukti pembayaran untuk " +
                    "metode tunai.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onTertiaryContainer,
                modifier = Modifier.padding(top = 4.dp)
            )
        }
    }
}

@Composable
private fun CashInfoLine(text: String) {
    Row(verticalAlignment = Alignment.Top) {
        Text(
            text = "•",
            color = MaterialTheme.colorScheme.onTertiaryContainer,
            style = MaterialTheme.typography.bodyMedium
        )
        Spacer(Modifier.width(8.dp))
        Text(
            text = text,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onTertiaryContainer
        )
    }
}

/**
 * Pemberi tahu bahwa bukti pembayaran bersifat privat.
 *
 * Ditampilkan di layar upload agar customer memahami bukti tidak dibagikan
 * ke publik dan hanya dibaca oleh pemilik toko.
 */
@Composable
fun ProofPrivacyNotice(modifier: Modifier = Modifier) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .background(
                color = MaterialTheme.colorScheme.surfaceVariant,
                shape = RoundedCornerShape(8.dp)
            )
            .padding(12.dp)
            .clearAndSetSemantics {
                contentDescription = "Bukti pembayaran bersifat privat dan hanya " +
                    "dibaca oleh pemilik toko."
            },
        verticalAlignment = Alignment.Top
    ) {
        Text(
            text = "Bukti pembayaran bersifat privat. Foto ini hanya dibaca " +
                "oleh pemilik toko untuk memverifikasi pembayaran.",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}