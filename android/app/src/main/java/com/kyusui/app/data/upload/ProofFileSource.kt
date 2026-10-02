package com.kyusui.app.data.upload

import android.content.ContentResolver
import android.net.Uri
import android.provider.OpenableColumns
import com.kyusui.app.core.ProofUploadPolicy
import com.kyusui.app.domain.model.ProofImageSelection
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Sumber file multipart untuk upload bukti QRIS.
 *
 * Antarmuka ini ada agar [com.kyusui.app.data.repository.CustomerRepositoryImpl]
 * tetap dapat diuji tanpa Android `ContentResolver`.
 */
interface ProofFileSource {
    /**
     * Membangun part multipart dengan nama field `proof_image` sesuai
     * specification section 11.5.
     *
     * @throws IllegalStateException bila file tidak dapat dibaca dari URI.
     */
    fun createProofPart(selection: ProofImageSelection): MultipartBody.Part

    /**
     * Membaca metadata file dari picker (MIME, ukuran, nama).
     *
     * @return null bila file tidak tersedia atau tidak dapat dibaca.
     */
    fun readSelection(uri: Uri): ProofImageSelection?
}

/**
 * Implementasi berbasis `ContentResolver` untuk file yang dipilih user.
 *
 * Nama field, nama file, dan lokasi penyimpanan ditentukan backend; Android
 * hanya meneruskan byte yang dipilih customer. Nama file asli tidak pernah
 * dipakai sebagai path server.
 */
@Singleton
class ContentResolverProofFileSource @Inject constructor(
    private val contentResolver: ContentResolver
) : ProofFileSource {

    override fun createProofPart(selection: ProofImageSelection): MultipartBody.Part {
        val uri = Uri.parse(selection.localUri)
        val mimeType = selection.mimeType.toMediaTypeOrNull()
            ?: ProofUploadPolicy.ALLOWED_MIME_TYPES.first().toMediaTypeOrNull()

        val body = contentResolver.openInputStream(uri)?.use { stream ->
            stream.readBytes().toRequestBody(mimeType)
        } ?: throw IllegalStateException("File bukti pembayaran tidak dapat dibaca.")

        return MultipartBody.Part.createFormData(
            PROOF_FIELD_NAME,
            // Nama asli dari perangkat tidak pernah masuk request: bisa saja
            // memuat path, nama akun, atau data pribadi. Backend yang menentukan
            // nama file dan lokasi penyimpanan.
            DEFAULT_DISPLAY_NAME,
            body
        )
    }

    override fun readSelection(uri: Uri): ProofImageSelection? {
        val mimeType = contentResolver.getType(uri)
            ?: uri.lastPathSegment?.substringAfterLast('.', "")?.let { extension ->
                when (extension.lowercase()) {
                    "jpg", "jpeg" -> "image/jpeg"
                    "png" -> "image/png"
                    else -> null
                }
            }
            ?: return null

        val (size, name) = queryMetadata(uri)
        if (size <= 0L) return null

        return ProofImageSelection(
            // Uri hanya dipakai untuk preview dan pembacaan byte di perangkat.
            localUri = uri.toString(),
            mimeType = mimeType,
            sizeBytes = size,
            displayName = name
        )
    }

    private fun queryMetadata(uri: Uri): Pair<Long, String> {
        var size = 0L
        var name = DEFAULT_DISPLAY_NAME

        contentResolver.query(uri, null, null, null, null)?.use { cursor ->
            val sizeIndex = cursor.getColumnIndex(OpenableColumns.SIZE)
            val nameIndex = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
            if (cursor.moveToFirst()) {
                if (sizeIndex >= 0 && !cursor.isNull(sizeIndex)) size = cursor.getLong(sizeIndex)
                if (nameIndex >= 0) {
                    cursor.getString(nameIndex)?.takeIf { it.isNotBlank() }?.let { name = it }
                }
            }
        }

        return size to name
    }

    companion object {
        /** Nama field multipart wajib sesuai specification section 11.5. */
        const val PROOF_FIELD_NAME = "proof_image"

        /**
         * Nama file generik. Nama asli dari perangkat bisa membocorkan path atau
         * data pribadi ke header request, jadi tidak diteruskan apa adanya.
         */
        const val DEFAULT_DISPLAY_NAME = "qris-proof.jpg"
    }
}