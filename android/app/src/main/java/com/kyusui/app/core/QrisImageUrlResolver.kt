package com.kyusui.app.core

/**
 * Menerjemahkan nilai `qris_image` dari backend menjadi URL absolut yang bisa
 * dimuat Coil.
 *
 * API specification section 11.1 memungkinkan backend mengembalikan QRIS image
 * dalam dua bentuk:
 *
 * - path relatif seperti `/storage/qris/berkah-water.png`
 * - URL lengkap dari signed/authorized temporary URL
 *
 * Android tidak boleh menebak-nebak bentuk tersebut, dan tidak boleh memuat
 * dari host bebas. Resolusi selalu diarahkan ke origin API yang sudah
 * ditentukan [Constants], sehingga `qris_image` tidak bisa dipakai backend (atau
 * apa pun yang menyamar sebagai backend) untuk mengarahkan perangkat ke host
 * lain.
 *
 * Catatan keamanan: gambar QRIS adalah aset konfigurasi toko, bukan bukti
 * pembayaran. Bukti pembayaran tidak pernah melewati kelas ini.
 */
object QrisImageUrlResolver {

    /**
     * @param raw nilai `qris_image` mentah dari backend.
     * @param apiBaseUrl base URL API, misal `http://10.0.2.2:8000/api/v1/`.
     * @return URL absolut, atau `null` bila nilainya kosong/tidak bisa dipakai.
     */
    fun resolve(raw: String?, apiBaseUrl: String = Constants.BASE_URL_DEV): String? {
        val value = raw?.trim()?.takeIf { it.isNotEmpty() } ?: return null

        // URL absolute dari backend (misal signed temporary URL) dipakai apa
        // adanya, selama tetap menunjuk ke origin API yang sah.
        //
        // Perbandingan scheme sengaja case-insensitive: scheme URL tidak
        // membedakan huruf besar/kecil. Kalau dicek secara case-sensitive,
        // `HTTP://evil.example.com/...` akan lolos ke cabang path relatif dan
        // sama sekali melewati allowlist host.
        val lower = value.lowercase()
        if (lower.startsWith("http://") || lower.startsWith("https://")) {
            val host = hostOf(value) ?: return null
            if (host != allowedHost(apiBaseUrl)) return null
            // Scheme dinormalisasi supaya hasilnya deterministik.
            return value.substring(0, value.indexOf("://")).lowercase() +
                value.substring(value.indexOf("://"))
        }

        // Protocol-relative URL tidak diterima karena bisa dialihkan ke scheme
        // lain oleh pihak yang mengendalikan nilai dari backend.
        if (value.startsWith("//")) return null

        // Sisanya diperlakukan sebagai path yang relatif terhadap origin API.
        val origin = originOf(apiBaseUrl) ?: return null
        val path = value.trimStart('/')
        return origin + path
    }

    /** Host yang diizinkan, diturunkan dari base URL API. */
    private fun allowedHost(apiBaseUrl: String): String? = hostOf(apiBaseUrl)

    /** Scheme + host + port, tanpa path API. */
    private fun originOf(apiBaseUrl: String): String? {
        val normalized = apiBaseUrl.trim()
        val schemeEnd = normalized.indexOf("://")
        if (schemeEnd <= 0) return null

        val afterScheme = normalized.substring(schemeEnd + 3)
        val hostEnd = afterScheme.indexOfFirst { it == '/' || it == '?' || it == '#' }
        val authority = if (hostEnd >= 0) afterScheme.substring(0, hostEnd) else afterScheme
        if (authority.isBlank()) return null

        return normalized.substring(0, schemeEnd + 3) + authority + "/"
    }

    private fun hostOf(url: String): String? {
        val schemeEnd = url.indexOf("://")
        if (schemeEnd <= 0) return null

        val afterScheme = url.substring(schemeEnd + 3)
        val hostEnd = afterScheme.indexOfFirst { it == '/' || it == '?' || it == '#' }
        val authority = if (hostEnd >= 0) afterScheme.substring(0, hostEnd) else afterScheme

        // Buang kredensial userinfo bila ada supaya tidak ikut terpotong diam-diam.
        return authority.substringAfterLast('@').lowercase().takeIf { it.isNotBlank() }
    }
}