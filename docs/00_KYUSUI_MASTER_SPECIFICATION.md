# KYŪSUI — MASTER SPECIFICATION

**Nama Proyek:** KYŪSUI  
**Deskripsi:** Aplikasi Pemesanan Air Galon Online dengan Fitur Tracking Posisi Pengantar Galon  
**Studi Kasus:** Berkah Water  
**Dokumen:** `00_KYUSUI_MASTER_SPECIFICATION.md`  
**Status:** Baseline Business Requirements  
**Sumber Utama:** Dokumen Kelompok 7 — Proyek Sistem Informasi

> Dokumen ini merupakan sumber kebenaran kebutuhan bisnis KYŪSUI. Seluruh proses analisis, desain sistem, desain database, UI/UX, API, dan implementasi harus mengacu pada kebutuhan yang didefinisikan di sini.
>
> Requirement yang berasal langsung dari dokumen Kelompok 7 dibedakan dari hasil elaborasi analisis dan keputusan yang masih belum ditentukan. Detail teknologi tidak dianggap sebagai requirement bisnis.

---

## 1. Project Overview

KYŪSUI adalah sistem pemesanan dan pengantaran air galon untuk studi kasus **Berkah Water**.

Sistem berfokus pada integrasi proses:

```text
Pelanggan
    ↓
Pemesanan Air Galon
    ↓
Pembayaran
    ↓
Pengelolaan Pesanan oleh Pemilik Depot
    ↓
Penugasan Pengantar
    ↓
Pengantaran
    ↓
Tracking Posisi Pengantar
    ↓
Pesanan Diterima
    ↓
Pesanan Selesai
    ↓
Riwayat Pesanan
```

Sistem melibatkan tiga peran utama:

1. Pelanggan
2. Pemilik Depot
3. Pengantar Galon

Dokumen Kelompok 7 menetapkan bahwa sistem difokuskan pada proses pemesanan dan pengantaran air galon pada **satu depot, yaitu Berkah Water**.

---

## 2. Background

Berkah Water merupakan usaha yang bergerak dalam penyediaan dan penjualan air galon kepada masyarakat.

Dalam kondisi awal, proses pemesanan dan pengantaran masih menghadapi permasalahan dalam penyampaian informasi kepada pelanggan maupun pengelolaan proses pengantaran.

Pelanggan belum memiliki media khusus untuk melakukan pemesanan secara online. Pelanggan juga belum memperoleh informasi status pesanan secara terstruktur dan belum dapat mengetahui posisi pengantar ketika pesanan sedang dikirimkan.

Di sisi lain, pengantar membutuhkan informasi lokasi pelanggan yang jelas, sedangkan pemilik depot membutuhkan pengelolaan pesanan yang lebih terstruktur.

Permasalahan tersebut menjadi dasar kebutuhan terhadap sistem yang mengintegrasikan pemesanan, pengelolaan pesanan, pengantaran, dan tracking dalam satu sistem.

---

## 3. Problem Statement

Permasalahan utama yang menjadi dasar pengembangan KYŪSUI adalah:

1. Proses pemesanan air galon masih dilakukan secara konvensional.
2. Pelanggan belum memiliki media khusus untuk melakukan pemesanan secara online.
3. Pelanggan belum dapat memperoleh informasi status pesanan secara terstruktur.
4. Pelanggan belum dapat mengetahui posisi pengantar ketika pesanan sedang dalam proses pengiriman.
5. Pengantar membutuhkan informasi lokasi pelanggan yang jelas untuk membantu proses pengantaran.
6. Pemilik depot membutuhkan pengelolaan data pesanan yang lebih terstruktur.
7. Belum terdapat integrasi antara pemesanan, pengelolaan pesanan, pengantaran, dan tracking.
8. Riwayat pesanan perlu disimpan secara terstruktur.
9. Sistem perlu membedakan akses berdasarkan peran pelanggan, pemilik depot, dan pengantar.

---

## 4. Project Objectives

Tujuan KYŪSUI adalah membantu proses pemesanan dan pengantaran air galon agar lebih mudah dan terstruktur.

Tujuan khusus:

1. Membantu pelanggan melakukan pemesanan air galon secara online.
2. Memudahkan pelanggan menentukan jumlah galon yang ingin dipesan.
3. Memungkinkan pelanggan menentukan lokasi pengantaran.
4. Membantu pemilik depot menerima dan mengelola pesanan secara lebih terstruktur.
5. Membantu pengantar memperoleh informasi pesanan dan lokasi pelanggan.
6. Menyediakan tracking agar pelanggan dapat mengetahui posisi pengantar selama proses pengiriman.
7. Menyediakan informasi status pesanan.
8. Menyimpan riwayat pesanan pelanggan.
9. Mengintegrasikan pemesanan, pengelolaan pesanan, pengantaran, dan tracking.
10. Mengurangi ketergantungan terhadap proses pemesanan manual dan komunikasi berulang.

---

## 5. Scope

### 5.1 Business Scope

KYŪSUI mencakup:

- pemesanan air galon;
- pembayaran;
- pengelolaan pesanan;
- penugasan pengantar;
- pengantaran;
- penyampaian lokasi pelanggan;
- tracking posisi pengantar;
- pembaruan status pesanan;
- riwayat pesanan.

### 5.2 Organizational Scope

Sistem digunakan untuk studi kasus:

**Berkah Water**

Sistem pada tahap awal hanya menangani **satu depot**.

### 5.3 Process Boundary

#### Process Start

Proses dimulai ketika pelanggan membuka sistem dan melakukan pemesanan.

Pelanggan:

1. melakukan pemesanan;
2. menentukan jumlah galon;
3. memasukkan lokasi pengantaran;
4. memilih metode pembayaran;
5. melakukan pembayaran sesuai metode yang tersedia.

#### Process End

Proses berakhir ketika:

1. pesanan telah sampai;
2. pelanggan menerima pesanan;
3. status pesanan diperbarui menjadi selesai.

---

## 6. Out of Scope

Hal-hal berikut tidak termasuk dalam scope awal:

1. Pengelolaan banyak depot.
2. Marketplace atau agregator berbagai depot.
3. Pengelolaan pembelian atau pengadaan galon dari supplier.
4. Pengelolaan keuangan depot secara menyeluruh.
5. Sistem penggajian pengantar atau karyawan.
6. Sistem promosi.
7. Voucher.
8. Program loyalitas pelanggan.
9. Fitur lain yang tidak berhubungan langsung dengan proses pemesanan dan pengantaran air galon.

### Catatan Scope

Platform Android merupakan batas platform proyek, tetapi bukan kebutuhan bisnis seperti "pelanggan harus menggunakan teknologi X".

Detail implementasi teknologi tidak didefinisikan sebagai business requirement di dalam master specification ini.

---

## 7. Actors

### 7.1 Pelanggan

Pelanggan adalah pengguna yang melakukan pemesanan air galon.

Tanggung jawab pelanggan:

- melakukan registrasi;
- melakukan login;
- melakukan pemesanan;
- menentukan jumlah galon;
- menentukan lokasi pengantaran;
- memilih metode pembayaran;
- melakukan pembayaran;
- melihat status pesanan;
- melihat posisi pengantar selama proses pengiriman;
- melihat riwayat pesanan;
- melakukan logout.

### 7.2 Pemilik Depot

Pemilik Depot merupakan pihak yang mengelola pesanan pada Berkah Water.

Tanggung jawab:

- menerima pesanan;
- memeriksa pesanan;
- memeriksa status pembayaran;
- memproses pesanan;
- meneruskan/menugaskan pesanan kepada pengantar;
- mengelola status pesanan sesuai tanggung jawabnya.

### 7.3 Pengantar Galon

Pengantar Galon bertanggung jawab terhadap proses pengiriman.

Tanggung jawab:

- menerima pesanan yang ditugaskan;
- melihat informasi pesanan;
- melihat informasi pelanggan;
- memperoleh lokasi pengantaran;
- melakukan pengantaran;
- mengirimkan informasi posisi selama pengantaran;
- memperbarui status pesanan sesuai proses pengiriman.

---

## 8. Customer Requirements

### CR-C01 — Pemesanan Online

Pelanggan membutuhkan kemampuan melakukan pemesanan air galon tanpa harus melakukan proses pemesanan secara konvensional.

### CR-C02 — Menentukan Jumlah Galon

Pelanggan membutuhkan kemampuan menentukan jumlah galon yang ingin dipesan.

### CR-C03 — Menentukan Lokasi Pengantaran

Pelanggan membutuhkan kemampuan memberikan lokasi pengantaran kepada depot/pengantar.

### CR-C04 — Pembayaran

Pelanggan membutuhkan kemampuan memilih dan melakukan pembayaran menggunakan metode pembayaran yang tersedia dalam sistem.

### CR-C05 — Informasi Status Pesanan

Pelanggan membutuhkan informasi mengenai perkembangan pesanannya.

### CR-C06 — Tracking Pengantar

Pelanggan membutuhkan kemampuan mengetahui posisi pengantar ketika pesanan sedang dikirimkan.

### CR-C07 — Riwayat Pesanan

Pelanggan membutuhkan akses terhadap pesanan yang pernah dilakukan.

---

## 9. Owner Requirements

### OR-O01 — Pengelolaan Pesanan

Pemilik depot membutuhkan kemampuan melihat, menerima, dan mengelola pesanan pelanggan.

### OR-O02 — Informasi Pembayaran

Pemilik depot membutuhkan informasi status pembayaran untuk membantu proses pemeriksaan transaksi.

### OR-O03 — Pemrosesan Pesanan

Pemilik depot membutuhkan kemampuan memproses pesanan sebelum dikirim.

### OR-O04 — Penugasan Pengantar

Pemilik depot membutuhkan kemampuan memberikan pesanan kepada pengantar untuk dilakukan pengiriman.

### OR-O05 — Informasi Pesanan Terstruktur

Pemilik depot membutuhkan data pesanan yang lebih terstruktur sehingga proses pengelolaan dan penerusan pesanan kepada pengantar lebih mudah.

---

## 10. Courier Requirements

### CRR-D01 — Informasi Pesanan

Pengantar membutuhkan informasi pesanan yang diberikan kepadanya.

### CRR-D02 — Informasi Pelanggan

Pengantar membutuhkan informasi pelanggan yang diperlukan untuk melakukan pengantaran.

### CRR-D03 — Lokasi Pengantaran

Pengantar membutuhkan informasi lokasi pelanggan sebagai acuan menuju lokasi pengantaran.

### CRR-D04 — Pengiriman Posisi

Pengantar membutuhkan kemampuan mengirimkan informasi posisi selama proses pengantaran.

### CRR-D05 — Pembaruan Status

Pengantar membutuhkan kemampuan memperbarui status pesanan sesuai tahapan pengiriman yang menjadi tanggung jawabnya.

---

## 11. Functional Requirements

| ID | Functional Requirement | Aktor |
|---|---|---|
| KF-01 | Sistem menyediakan proses registrasi dan login sesuai peran pengguna. | Pelanggan, Pemilik Depot, Pengantar |
| KF-02 | Sistem memungkinkan pelanggan melakukan pemesanan air galon dengan menentukan jumlah galon dan lokasi pengantaran. | Pelanggan |
| KF-03 | Sistem memungkinkan pelanggan melakukan pembayaran melalui metode pembayaran yang tersedia. | Pelanggan |
| KF-04 | Sistem memproses informasi pembayaran dan menampilkan status pembayaran; pemilik depot dapat melakukan pengecekan apabila diperlukan. | Sistem, Pemilik Depot |
| KF-05 | Sistem memungkinkan pemilik depot melihat, menerima, dan mengelola pesanan pelanggan. | Pemilik Depot |
| KF-06 | Sistem memungkinkan pemilik depot memberikan pesanan kepada pengantar galon. | Pemilik Depot |
| KF-07 | Sistem memungkinkan pengantar melihat informasi pesanan yang diberikan kepadanya, termasuk informasi pelanggan dan lokasi pengantaran. | Pengantar |
| KF-08 | Sistem memungkinkan pelanggan melihat posisi pengantar selama proses pengantaran melalui peta. | Pelanggan |
| KF-09 | Sistem memungkinkan pengantar mengirimkan informasi posisi selama melakukan pengantaran. | Pengantar |
| KF-10 | Sistem memungkinkan pembaruan status pesanan sesuai tahapan proses pemesanan dan pengantaran. | Pemilik Depot, Pengantar |
| KF-11 | Sistem memungkinkan pelanggan melihat riwayat pesanan yang telah dilakukan. | Pelanggan |
| KF-12 | Sistem memungkinkan pengguna keluar dari akun. | Semua |

---

## 12. Non-Functional Requirements

| ID | Aspek | Requirement |
|---|---|---|
| KNF-01 | Platform | Sistem ditujukan untuk perangkat Android. |
| KNF-02 | Usability | Antarmuka harus mudah dipahami dan digunakan oleh pelanggan, pemilik depot, dan pengantar. |
| KNF-03 | Performance | Sistem harus mampu memproses pemesanan dan menampilkan informasi pesanan dengan waktu respons yang wajar. |
| KNF-04 | Availability | Sistem membutuhkan koneksi internet agar proses pemesanan, pembayaran, pengiriman data, dan tracking dapat berjalan. |
| KNF-05 | Security | Sistem harus membatasi akses berdasarkan akun dan peran pengguna serta menjaga informasi pengguna dan transaksi. |
| KNF-06 | Reliability | Sistem harus mampu menyimpan data pesanan dan status pembayaran secara konsisten. |
| KNF-07 | Location Service | Sistem membutuhkan layanan lokasi/GPS untuk mendukung pengiriman posisi pengantar dan proses tracking. |
| KNF-08 | Payment Service | Sistem membutuhkan layanan pembayaran yang digunakan agar pembayaran digital dapat dilakukan dan status transaksi dapat diproses. |

### Batasan Kuantitatif yang Belum Ditentukan

Dokumen utama belum menentukan secara eksplisit:

- waktu respons maksimum;
- interval pembaruan GPS;
- tingkat akurasi lokasi minimum;
- batas downtime;
- jumlah pengguna simultan;
- ukuran maksimum data;
- target konsumsi baterai;
- target penggunaan bandwidth.

Nilai numerik untuk parameter tersebut tidak boleh dianggap sebagai requirement final tanpa keputusan lanjutan atau hasil pengujian.

---

## 13. Business Rules

### BR-01 — Single Depot

KYŪSUI pada scope awal hanya menangani satu depot, yaitu Berkah Water.

### BR-02 — Role-Based Access

Sistem membedakan akses berdasarkan tiga peran:

- Pelanggan;
- Pemilik Depot;
- Pengantar Galon.

### BR-03 — Customer Ownership

Pelanggan hanya boleh mengakses informasi pesanan yang menjadi miliknya.

### BR-04 — Order Minimum Information

Pesanan harus memiliki minimal:

- pelanggan;
- jumlah galon;
- lokasi pengantaran.

### BR-05 — Depot Processing

Pesanan diterima dan dikelola oleh pemilik depot sebelum diteruskan kepada pengantar.

### BR-06 — Courier Assignment

Pesanan yang akan dikirim harus diberikan kepada pengantar.

### BR-07 — Courier Access

Pengantar hanya memperoleh informasi pesanan yang diberikan kepadanya.

### BR-08 — Tracking Context

Tracking posisi pengantar berhubungan dengan proses pengantaran pesanan.

### BR-09 — Location Transmission

Pengantar mengirimkan informasi posisi selama proses pengantaran.

### BR-10 — Order Completion

Pesanan dianggap mencapai akhir proses ketika pesanan telah sampai dan diterima pelanggan, kemudian status diperbarui menjadi selesai.

### BR-11 — Payment Verification

Status pembayaran harus dapat diketahui dan digunakan dalam proses pengelolaan pesanan.

### BR-12 — Payment Method Availability

Pelanggan memilih metode pembayaran yang memang tersedia pada sistem.

> Keputusan payment final untuk Berkah Water adalah QRIS dan CASH. Detail lifecycle dan authorization mengikuti `08_KYUSUI_PAYMENT_SPECIFICATION_REBUILT.md`.

### BR-13 — Tracking During Delivery

Tracking ditujukan untuk kondisi ketika pengantar sedang melakukan proses pengiriman.

---

## 14. Order Workflow

Workflow bisnis utama KYŪSUI:

```text
[START]
   ↓
Pelanggan Login
   ↓
Membuat Pesanan
   ↓
Menentukan Jumlah Galon
   ↓
Menentukan Lokasi Pengantaran
   ↓
Memilih Metode Pembayaran
   ↓
Melakukan Pembayaran
   ↓
Sistem Memproses Informasi Pembayaran
   ↓
Status Pembayaran Tersedia
   ↓
Pemilik Depot Menerima Pesanan
   ↓
Pemilik Depot Memeriksa Pesanan
   ↓
Pemilik Depot Memproses Pesanan
   ↓
Pesanan Siap Dikirim
   ↓
Pemilik Depot Menugaskan Pengantar
   ↓
Pengantar Menerima Informasi Pesanan
   ↓
Pengantar Menuju Lokasi Pelanggan
   ↓
Pengantar Mengirim Informasi Posisi
   ↓
Pelanggan Melihat Tracking
   ↓
Pesanan Sampai
   ↓
Pelanggan Menerima Pesanan
   ↓
Status Pesanan = Selesai
   ↓
Pesanan Dapat Dilihat Dalam Riwayat
   ↓
[END]
```

### Workflow Payment Final

Aturan payment yang berlaku:

- QRIS menggunakan Static QRIS Berkah Water yang aktif; customer membayar secara eksternal dan mengunggah QRIS Proof.
- Owner melakukan Manual Owner Verification atas QRIS Proof. Approval mengubah payment menjadi `PAID`; rejection mengembalikan payment ke `PENDING` agar customer dapat upload ulang tanpa membuat payment record baru.
- CASH adalah Cash on Delivery. Order CASH dapat diproses dan dikirim ketika payment masih `PENDING`.
- Hanya Assigned Courier yang dapat mengonfirmasi CASH melalui aksi `Uang Diterima`; customer dan Owner tidak dapat melakukan konfirmasi tersebut.

Cancellation workflow dan penerimaan/penolakan assignment tetap belum dikunci dan tidak diubah oleh keputusan payment ini.

---

## 15. Payment Requirements

### 15.1 Payment Requirement

Sistem harus menyediakan proses pembayaran sesuai metode yang tersedia.

Pelanggan dapat memilih metode pembayaran yang tersedia dan melakukan pembayaran melalui proses yang disediakan sistem.

Sistem kemudian memperoleh informasi mengenai status pembayaran sehingga dapat digunakan dalam proses pengelolaan pesanan oleh pemilik depot.

### 15.2 Payment Status

Sistem harus dapat merepresentasikan kondisi transaksi pembayaran sehingga:

- pelanggan dapat mengetahui kondisi pembayaran;
- pemilik depot dapat melakukan pengecekan;
- status transaksi dapat digunakan dalam pengelolaan pesanan.

### 15.3 Payment Method and Verification

Payment Method yang tersedia hanya:

```text
QRIS
CASH
```

QRIS adalah Static QRIS milik Berkah Water. Hanya satu Active QRIS digunakan sebagai konfigurasi bisnis. Customer melihat QRIS, membayar secara eksternal, lalu upload QRIS Proof. Owner melakukan Manual Owner Verification.

CASH adalah Cash on Delivery. Customer membayar tunai kepada Courier saat delivery; Assigned Courier mengonfirmasi `Uang Diterima` setelah backend memvalidasi assignment.

Payment Status yang digunakan hanya:

```text
PENDING
WAITING_VERIFICATION
PAID
```

Tidak ada payment gateway, payment provider integration, dynamic QRIS, provider webhook, atau automatic payment verification.

---

## 16. Delivery Requirements

### 16.1 Delivery Assignment

Pemilik depot memberikan pesanan kepada pengantar untuk dilakukan pengantaran.

### 16.2 Delivery Information

Pengantar harus memperoleh:

- informasi pesanan;
- informasi pelanggan yang diperlukan;
- lokasi pengantaran.

### 16.3 Delivery Process

Pengantar melakukan pengantaran menuju lokasi pelanggan.

### 16.4 Delivery Status

Status pesanan dapat diperbarui sesuai tahapan proses pengantaran.

### 16.5 Delivery Completion

Ketika pesanan telah sampai dan diterima pelanggan, proses pengantaran berakhir dan status pesanan diperbarui menjadi selesai.

---

## 17. Tracking Requirements

### 17.1 Customer Tracking

Pelanggan harus dapat melihat posisi pengantar selama proses pengantaran.

### 17.2 Courier Location

Pengantar harus dapat mengirimkan informasi posisi selama proses pengantaran.

### 17.3 Map Representation

Posisi pengantar ditampilkan kepada pelanggan melalui peta.

### 17.4 Tracking Context

Tracking berkaitan dengan pesanan yang sedang dalam proses pengantaran.

### 17.5 Location Relationship

```text
Pengantar
    │
    │ mengirim posisi
    ↓
Sistem
    │
    │ menyediakan posisi
    ↓
Pelanggan
    │
    ↓
Melihat posisi pengantar
```

### Tracking Details yang Belum Ditentukan

Belum ada keputusan final mengenai:

- interval pembaruan lokasi;
- akurasi minimum;
- penggunaan background location;
- ETA;
- rute perjalanan;
- histori perjalanan;
- geofencing;
- mekanisme ketika koneksi internet terputus.

Detail tersebut merupakan keputusan desain/teknis dan tidak boleh diperlakukan sebagai business requirement.

---

## 18. Order History

### 18.1 Requirement

Sistem harus menyimpan informasi pesanan sehingga pelanggan dapat melihat riwayat pesanan yang telah dilakukan.

### 18.2 Customer Access

Pelanggan hanya dapat melihat riwayat miliknya sendiri.

### 18.3 Minimum Information

Riwayat setidaknya harus dapat mengidentifikasi pesanan sebelumnya dan statusnya.

Detail tampilan seperti filter, sorting, pencarian, dan pagination belum merupakan requirement bisnis yang ditetapkan.

### 18.4 Data Retention

Durasi penyimpanan riwayat pesanan belum ditentukan.

### 18.5 Technical Representation

Belum diputuskan apakah riwayat:

1. berasal langsung dari data order; atau
2. menggunakan entitas/tabel history tersendiri.

Keputusan tersebut merupakan bagian dari desain data, bukan business requirement.

---

## 19. Authentication and Authorization Requirements

### 19.1 Authentication

Sistem harus menyediakan proses registrasi dan login bagi pengguna sesuai perannya.

Aktor yang membutuhkan autentikasi:

- Pelanggan;
- Pemilik Depot;
- Pengantar Galon.

### 19.2 Registration

Sistem menyediakan proses registrasi sesuai kebutuhan akun pengguna.

Detail field registrasi belum ditetapkan secara eksplisit dalam master requirement.

### 19.3 Login

Pengguna harus melakukan login untuk mengakses fungsi yang membutuhkan autentikasi.

### 19.4 Authorization

Setelah autentikasi, akses pengguna harus dibatasi berdasarkan role.

#### Pelanggan

Dapat mengakses fungsi yang berkaitan dengan:

- pemesanan;
- pembayaran;
- status pesanan;
- tracking;
- riwayat.

#### Pemilik Depot

Dapat mengakses fungsi yang berkaitan dengan:

- pesanan;
- status pembayaran;
- pemrosesan;
- penugasan pengantar.

#### Pengantar

Dapat mengakses fungsi yang berkaitan dengan:

- pesanan yang ditugaskan;
- informasi pelanggan yang diperlukan;
- lokasi pengantaran;
- pengiriman posisi;
- status pengantaran.

### 19.5 Logout

Semua pengguna dapat keluar dari akun.

### 19.6 Security Boundary

Sistem harus mencegah pengguna mengakses fungsi atau data yang tidak menjadi hak role-nya.

### Authentication Details yang Belum Ditentukan

Belum ditentukan:

- format identifier login;
- password policy;
- reset password;
- verifikasi email;
- OTP;
- social login;
- biometric authentication;
- session duration;
- device management.

Semua hal tersebut merupakan keputusan lanjutan.

---

## 20. Acceptance Criteria

### AC-01 — Registration & Login

**Given** pengguna belum memiliki sesi aktif  
**When** pengguna melakukan registrasi/login dengan data yang valid  
**Then** sistem harus memberikan akses sesuai role pengguna.

**And** pengguna dengan role berbeda tidak boleh memperoleh akses terhadap fungsi yang bukan haknya.

### AC-02 — Customer Ordering

**Given** pelanggan telah login  
**When** pelanggan membuat pesanan  
**Then** pelanggan dapat menentukan:

- jumlah galon;
- lokasi pengantaran;
- metode pembayaran.

**And** pesanan tersimpan dalam sistem.

### AC-03 — Payment

**Given** pelanggan memiliki pesanan  
**When** pelanggan memilih metode pembayaran yang tersedia  
**Then** sistem memproses transaksi dan menyediakan informasi status pembayaran.

**And** status pembayaran dapat digunakan oleh pemilik depot untuk melakukan pengecekan.

### AC-04 — Order Management

**Given** terdapat pesanan pelanggan  
**When** pemilik depot mengakses daftar pesanan  
**Then** pesanan tersebut dapat dilihat dan dikelola oleh pemilik depot.

### AC-05 — Courier Assignment

**Given** pesanan siap diberikan untuk pengantaran  
**When** pemilik depot menugaskan pesanan  
**Then** pengantar yang ditugaskan dapat memperoleh informasi pesanan tersebut.

### AC-06 — Courier Order Information

**Given** pengantar telah menerima penugasan  
**When** pengantar membuka pesanan  
**Then** sistem menampilkan informasi yang diperlukan untuk melakukan pengantaran, termasuk lokasi pelanggan.

### AC-07 — Tracking

**Given** pesanan sedang dalam proses pengantaran  
**When** pengantar mengirimkan informasi posisi  
**Then** pelanggan dapat melihat posisi pengantar melalui peta.

### AC-08 — Tracking Authorization

**Given** pelanggan memiliki pesanan yang sedang dikirim  
**When** pelanggan membuka tracking  
**Then** pelanggan dapat melihat posisi pengantar yang berkaitan dengan pesanannya.

**And** pelanggan tidak boleh memperoleh posisi pengantar untuk pesanan pelanggan lain.

### AC-09 — Order Status

**Given** pesanan berada pada suatu tahap proses  
**When** pemilik depot atau pengantar melakukan pembaruan yang menjadi tanggung jawabnya  
**Then** status pesanan berubah sesuai tahapan proses.

### AC-10 — Delivery Completion

**Given** pengantar telah sampai di lokasi pelanggan  
**When** pesanan diterima pelanggan  
**Then** pesanan dapat diperbarui menjadi selesai.

### AC-11 — Order History

**Given** pelanggan memiliki pesanan sebelumnya  
**When** pelanggan membuka riwayat  
**Then** sistem menampilkan riwayat pesanan milik pelanggan tersebut.

### AC-12 — Logout

**Given** pengguna memiliki sesi aktif  
**When** pengguna memilih logout  
**Then** sesi pengguna berakhir dan pengguna tidak lagi dapat mengakses fungsi yang membutuhkan autentikasi sampai login kembali.

---

## 21. Requirement Traceability

| Requirement Area | Sumber | Status |
|---|---|---|
| Project Background | Kelompok 7 | Confirmed |
| Problem Statement | Kelompok 7 | Confirmed |
| Objectives | Kelompok 7 | Confirmed |
| Single Depot | Kelompok 7 | Confirmed |
| Customer Role | Kelompok 7 | Confirmed |
| Owner Role | Kelompok 7 | Confirmed |
| Courier Role | Kelompok 7 | Confirmed |
| Registration/Login | Kelompok 7 | Confirmed |
| Ordering | Kelompok 7 | Confirmed |
| Payment | Kelompok 7 | Confirmed |
| Payment Status | Kelompok 7 | Confirmed |
| Order Management | Kelompok 7 | Confirmed |
| Courier Assignment | Kelompok 7 | Confirmed |
| Courier Order Information | Kelompok 7 | Confirmed |
| GPS/Tracking | Kelompok 7 | Confirmed |
| Order Status | Kelompok 7 | Confirmed |
| Order History | Kelompok 7 | Confirmed |
| Logout | Kelompok 7 | Confirmed |
| Payment Method Detail | Payment decision final | Confirmed: QRIS / CASH |
| QRIS Verification | Payment decision final | Confirmed: manual Owner verification |
| CASH Confirmation | Payment decision final | Confirmed: Assigned Courier |
| GPS Interval | Belum ditentukan | TBD |
| GPS Accuracy | Belum ditentukan | TBD |
| ETA | Belum ditentukan | TBD |
| Route Navigation | Belum ditentukan | TBD |
| Cancellation Workflow | Belum ditentukan | TBD |
| Refund Workflow | Belum ditentukan | TBD |
| Password Policy | Belum ditentukan | TBD |
| Registration Fields | Belum ditentukan secara lengkap | TBD |
| Order History Storage Model | Keputusan desain | TBD |

---

## 22. Business Requirement vs Technical Decision

Bagian ini bersifat normatif untuk mencegah scope creep dan keputusan implementasi dianggap sebagai requirement bisnis.

### 22.1 Business Requirements

Yang sudah dapat dianggap sebagai kebutuhan bisnis:

```text
Pelanggan dapat memesan air galon.
Pelanggan menentukan jumlah galon.
Pelanggan menentukan lokasi pengantaran.
Pelanggan memilih metode pembayaran yang tersedia.
Sistem memproses status pembayaran.
Pemilik depot mengelola pesanan.
Pemilik depot menugaskan pengantar.
Pengantar menerima informasi pesanan.
Pengantar memperoleh lokasi pelanggan.
Pengantar mengirim posisi.
Pelanggan melihat tracking.
Status pesanan dapat diperbarui.
Pelanggan melihat riwayat.
Pengguna dapat logout.
Akses dibedakan berdasarkan role.
```

### 22.2 Technical Decisions

Hal-hal berikut bukan requirement bisnis dan harus diputuskan pada tahap arsitektur/desain:

```text
Bahasa pemrograman
Framework
Database engine
REST API atau GraphQL
Authentication protocol
Token mechanism
Static QRIS configuration and payment verification workflow
Map provider
GPS implementation
Background location mechanism
Push notification mechanism
Cloud provider
Server architecture
Database schema
Caching strategy
API endpoint structure
```

Keputusan tersebut tidak boleh mengubah kebutuhan bisnis yang telah ditetapkan dalam dokumen ini.

---

## 23. Requirements Not Yet Defined

Sebelum implementasi final, beberapa kebutuhan masih memerlukan validasi dengan pihak Berkah Water.

### Order

- Apakah pelanggan dapat membatalkan pesanan? **Ya, hanya pada status MENUNGGU_PEMBAYARAN (Pilihan A)**
- Sampai tahap apa pembatalan diperbolehkan? **Hanya status MENUNGGU_PEMBAYARAN**
- Apakah jumlah minimum/maksimum galon berlaku?
- Apakah terdapat batas wilayah pengantaran?
- Apakah terdapat biaya pengantaran?
- Bagaimana perhitungan total harga?

### Delivery

- Apakah satu pengantar dapat membawa beberapa pesanan sekaligus?
- Bagaimana jika pengantar menolak tugas?
- Bagaimana jika pelanggan tidak berada di lokasi?
- Apakah pengantaran memiliki jadwal tertentu?
- Apakah pelanggan harus mengonfirmasi penerimaan?

### Tracking

- Kapan tracking mulai aktif?
- Kapan tracking berhenti?
- Seberapa sering posisi diperbarui?
- Apakah tracking tetap aktif ketika aplikasi pengantar berada di background?
- Apa yang terjadi jika koneksi internet terputus?
- Apakah pelanggan membutuhkan estimasi waktu kedatangan?

### Authentication

- Data apa yang digunakan untuk login?
- Data apa yang wajib ketika registrasi?
- Bagaimana reset password dilakukan?
- Apakah verifikasi nomor telepon diperlukan?
- Apakah verifikasi email diperlukan?

Pertanyaan tersebut sengaja dibiarkan sebagai **TBD**, bukan diisi dengan asumsi.

---

## 24. Final Baseline

Baseline kebutuhan KYŪSUI dapat diringkas sebagai berikut:

```text
                    KYŪSUI
                       │
          ┌────────────┴────────────┐
          │                         │
      CUSTOMER                 BERKAH WATER
          │                         │
          │                  ┌──────┴──────┐
          │                  │             │
      PEMESANAN           OWNER         COURIER
          │                  │             │
      PEMBAYARAN             │             │
          │                  │             │
      STATUS ORDER ──────────┤             │
          │                  │             │
          │              MANAGE ORDER      │
          │                  │             │
          │              ASSIGN ORDER ─────┘
          │                                │
          │                           DELIVERY
          │                                │
          └──────────── TRACKING ──────────┘
                       │
                 ORDER COMPLETED
                       │
                 ORDER HISTORY
```

Dengan demikian, **core business KYŪSUI bukan marketplace, bukan sistem keuangan depot, dan bukan sistem manajemen perusahaan secara menyeluruh**.

Core prosesnya adalah:

> **Pemesanan air galon → pembayaran → pengelolaan pesanan → penugasan pengantar → pengantaran → tracking → penyelesaian pesanan → riwayat.**

Seluruh desain dan implementasi berikutnya harus mempertahankan batas tersebut dan tidak menambahkan fitur di luar scope tanpa perubahan requirement yang tervalidasi.
