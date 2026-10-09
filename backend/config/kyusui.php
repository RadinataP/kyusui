<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upload Limits
    |--------------------------------------------------------------------------
    |
    | Batas ukuran dan daftar format gambar untuk seluruh file upload yang
    | diterima backend.
    |
    | Nilai dikunci oleh spesifikasi dan tidak boleh diubah tanpa perubahan
    | spesifikasi:
    |
    |   - Bukti pembayaran QRIS maksimum 5 MB, format JPG/JPEG/PNG
    |     (spec 08 section 11.1, spec 08 section 40, spec 08 section 48
    |      keputusan 7, test spec 11 PAY-005).
    |   - Nilai yang sama berlaku untuk gambar QRIS yang diunggah owner
    |     (spec 06 section 13.2).
    |
    | Spec 06 section 11.2 mewajibkan nilai ini berada pada configuration
    | constant backend dan bukan literal hard-coded di controller maupun di
    | Android, supaya kedua sisi memakai sumber nilai yang sama.
    |
    */

    'max_kilobytes' => 5120,

    'image_mimes' => 'jpg,jpeg,png',

];
