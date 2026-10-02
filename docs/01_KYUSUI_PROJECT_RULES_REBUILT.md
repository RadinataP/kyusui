# 01_KYUSUI_PROJECT_RULES

**Project:** KYŪSUI --- Aplikasi Pemesanan Air Galon Berbasis Android\
**Study Case:** Berkah Water\
**Document:** `01_KYUSUI_PROJECT_RULES.md`\
**Status:** Permanent Development Rules — REBUILT
**Payment Baseline:** QRIS + CASH (Static QRIS, Manual Owner Verification, Cash on Delivery)\
**Authority:** `00_KYUSUI_MASTER_SPECIFICATION.md`\
**Purpose:** Menjadi aturan teknis dan manajemen pengembangan yang wajib
dipatuhi oleh seluruh proses pengembangan KYŪSUI.

------------------------------------------------------------------------

## 1. Document Authority

Dokumen ini merupakan aturan permanen pengembangan KYŪSUI.

Hierarki sumber keputusan:

1.  `00_KYUSUI_MASTER_SPECIFICATION.md` --- sumber kebenaran kebutuhan
    bisnis.
2.  `01_KYUSUI_PROJECT_RULES.md` --- aturan permanen proses dan
    implementasi.
3.  Dokumen analisis, desain, UI/UX, database, API, testing, dan
    dokumentasi lain --- harus konsisten dengan dua dokumen di atas.
4.  Jurnal, artikel, dokumentasi framework, dan referensi eksternal ---
    hanya sebagai referensi teknis atau akademik, bukan sumber untuk
    mengubah requirement bisnis.
5.  Asumsi developer --- tidak boleh mengalahkan requirement yang sudah
    ditetapkan.

Jika terjadi konflik:

``` text
MASTER SPECIFICATION
        ↓
PROJECT RULES
        ↓
DESIGN / IMPLEMENTATION DOCUMENTS
        ↓
EXTERNAL REFERENCES
        ↓
ASSUMPTIONS
```

Requirement bisnis tidak boleh diubah hanya karena implementasi teknis
lebih mudah dengan cara lain.

## 1.1 Payment Decision and Master Conflict Register

Revisi payment pada Project Rules ini merupakan keputusan implementasi
yang memperjelas bagian payment yang pada Master Specification masih
bersifat umum. `00_KYUSUI_MASTER_SPECIFICATION.md` tidak menetapkan
provider payment tertentu dan tidak mengunci metode payment tertentu.

Karena itu, keputusan QRIS + CASH tidak dianggap sebagai perubahan diam-diam
terhadap Master Specification. Namun, dokumen terkait payment harus
diperbarui agar seluruh artefak proyek konsisten.

| ID | Area | Status | Penjelasan |
|---|---|---|---|
| MR-001 | Payment provider | NO DIRECT CONFLICT | Master tidak mengunci provider; Project Rules menetapkan tidak menggunakan payment gateway. |
| MR-002 | Payment method | NO DIRECT CONFLICT | Master memberi ruang untuk metode payment yang tersedia; Project Rules mengunci QRIS dan CASH. |
| MR-003 | QRIS manual verification | ELABORATION | Manual verification oleh Owner memperjelas proses payment digital. |
| MR-004 | CASH confirmation | ELABORATION | Courier confirmation memperjelas siapa yang mengonfirmasi penerimaan uang tunai. |
| MR-005 | Midtrans dependency | LEGACY RULE TO REMOVE | Aturan lama yang menjadikan Midtrans mandatory tidak lagi berlaku. |

Jika dokumen lain masih menyebut Midtrans sebagai dependency wajib, tandai
sebagai **LEGACY PAYMENT CONFLICT** dan sinkronkan dokumen tersebut secara
terkendali. Jangan mengubah Master Specification secara diam-diam.

------------------------------------------------------------------------

# 2. FINAL TECHNOLOGY STACK

Stack berikut dikunci dan tidak boleh diganti tanpa persetujuan
eksplisit.

## 2.1 Android Application

``` text
Platform        : Android only
Language        : Kotlin
UI              : Jetpack Compose
Architecture    : MVVM
State           : ViewModel + StateFlow
Navigation      : Navigation Compose
HTTP Client     : Retrofit
HTTP Transport  : OkHttp
Local Storage   : DataStore
Maps            : Google Maps SDK
Location        : Fused Location Provider
```

## 2.2 Backend

``` text
Framework       : Laravel 13
Language        : PHP 8.3+
Architecture    : REST API
Authentication  : Laravel Sanctum
```

## 2.3 Database

``` text
Database        : MySQL 8.x
```

## 2.4 Payment

``` text
Digital Payment : QRIS
Cash Payment    : CASH
QRIS Type       : Static QRIS
Verification    : Manual Owner Verification
Cash Flow       : Cash on Delivery
```

Keputusan payment final KYŪSUI adalah **QRIS dan CASH** tanpa payment
gateway. QRIS menggunakan QRIS statis yang ditampilkan/dikelola melalui
aplikasi dan pembayaran diverifikasi secara manual oleh Pemilik Depot.
Pembayaran CASH dilakukan secara Cash on Delivery dan dikonfirmasi oleh
Pengantar Galon setelah uang diterima.

Tidak ada ketergantungan wajib terhadap Midtrans, payment gateway,
provider dynamic QRIS, server key provider, atau webhook payment provider.

## 2.5 Notification

``` text
Push Notification : Firebase Cloud Messaging (FCM)
```

## 2.6 Architecture

Arsitektur utama:

``` text
Android Kotlin + Jetpack Compose
            │
            │ HTTPS / REST API
            ▼
      Laravel 13 API
            │
     ┌──────┴──────┐
     │             │
     ▼             ▼
  MySQL          External Services
                 ├─ Firebase FCM
                 └─ Google Maps / Location
```

Android tidak boleh berkomunikasi langsung dengan database.

Android harus berkomunikasi melalui REST API Laravel.

------------------------------------------------------------------------

# 3. PERMANENT PROJECT RULES

## Rule 01 --- Android Only

KYŪSUI hanya dikembangkan untuk platform Android.

Jangan membuat:

-   iOS application;
-   Flutter application;
-   React Native application;
-   web application sebagai pengganti aplikasi Android;
-   desktop application;

kecuali terdapat keputusan proyek baru yang secara eksplisit mengubah
scope.

------------------------------------------------------------------------

## Rule 02 --- Kotlin Mandatory

Source code aplikasi Android wajib menggunakan Kotlin.

Jangan memperkenalkan Java sebagai bahasa utama untuk feature baru.

Kode Java lama hanya boleh dipertahankan jika memang sudah ada dan masih
diperlukan, tetapi tidak boleh menjadi dasar pengembangan feature baru
tanpa alasan teknis yang terdokumentasi.

------------------------------------------------------------------------

## Rule 03 --- Jetpack Compose Mandatory

UI Android wajib menggunakan Jetpack Compose.

Jangan membuat UI baru menggunakan XML Views kecuali ada alasan teknis
yang benar-benar diperlukan dan telah disetujui.

Prioritas:

``` text
Composable
    ↓
State
    ↓
ViewModel
    ↓
Repository
    ↓
Retrofit API
```

------------------------------------------------------------------------

## Rule 04 --- Laravel 13 Mandatory

Backend wajib menggunakan Laravel 13 dengan PHP 8.3+.

Jangan mengganti backend menjadi:

-   Node.js;
-   Express;
-   CodeIgniter;
-   Spring Boot;
-   Django;
-   native PHP tanpa Laravel;

untuk menggantikan backend utama KYŪSUI.

------------------------------------------------------------------------

## Rule 05 --- MySQL Mandatory

Database utama wajib menggunakan MySQL 8.x.

Jangan mengganti database utama menjadi:

-   PostgreSQL;
-   SQLite sebagai server database;
-   MongoDB;
-   Firebase Database;
-   database lain;

tanpa persetujuan eksplisit.

SQLite/Room hanya boleh digunakan di Android apabila memang dibutuhkan
untuk local/offline persistence dan tidak menggantikan MySQL sebagai
source of truth server.

------------------------------------------------------------------------

## Rule 06 --- REST API Mandatory

Komunikasi Android ↔ Backend wajib melalui REST API Laravel.

Prinsip:

``` text
Android
   ↓ HTTP/HTTPS
REST API
   ↓
Laravel
   ↓
MySQL
```

Android tidak boleh:

-   mengakses MySQL secara langsung;
-   menyimpan kredensial database;
-   menjalankan query database server secara langsung;
-   melewati authorization API.

------------------------------------------------------------------------

## Rule 07 --- Laravel Sanctum Mandatory

Authentication API wajib menggunakan Laravel Sanctum.

Token harus dikelola dengan benar dan tidak boleh:

-   di-hardcode;
-   dimasukkan ke source code;
-   dimasukkan ke repository Git;
-   dicetak ke log production;
-   dikirim ke endpoint yang tidak membutuhkan authentication.

Authorization tetap harus diterapkan di server berdasarkan role dan
ownership data.

------------------------------------------------------------------------

## Rule 08 --- No Stack Change Without Approval

Stack yang telah dikunci tidak boleh diganti hanya karena:

-   developer lebih familiar dengan teknologi lain;
-   library tertentu terasa lebih mudah;
-   tutorial menggunakan framework lain;
-   AI menyarankan teknologi lain;
-   implementasi sementara mengalami error.

Perubahan stack harus:

1.  diidentifikasi alasannya;
2.  menjelaskan dampaknya;
3.  menjelaskan alternatif;
4.  mendapat persetujuan eksplisit;
5.  didokumentasikan.

------------------------------------------------------------------------

## Rule 09 --- One Codebase

KYŪSUI hanya memiliki satu codebase utama.

Jangan membuat:

-   project KYŪSUI kedua;
-   folder project duplikat;
-   backend kedua;
-   database kedua untuk feature yang sama;
-   aplikasi Android kedua untuk feature yang sama.

Jika implementasi perlu diperbaiki, perbaiki codebase yang sama.

------------------------------------------------------------------------

## Rule 10 --- Continue Existing Codebase

Setiap tahap pengembangan harus melanjutkan codebase yang sama.

Sebelum membuat implementasi:

1.  periksa struktur project;
2.  periksa feature yang sudah tersedia;
3.  periksa route;
4.  periksa model;
5.  periksa migration;
6.  periksa API;
7.  periksa UI;
8.  periksa dependency;
9.  gunakan implementasi yang sudah benar.

Jangan membuat ulang sesuatu yang sudah tersedia dan benar.

------------------------------------------------------------------------

## Rule 11 --- Preserve Correct Implementation

Implementasi yang sudah benar tidak boleh dihapus hanya untuk mengganti
gaya coding.

Sebelum mengubah code:

``` text
Understand
   ↓
Validate
   ↓
Modify
   ↓
Test
```

Jangan melakukan:

``` text
Delete everything
      ↓
Rewrite from zero
```

kecuali memang diperlukan dan telah dibuktikan bahwa implementasi lama
tidak dapat dipertahankan.

------------------------------------------------------------------------

## Rule 12 --- No Dummy Production API

Dummy API, mock response, fake database, hardcoded JSON, atau simulated
production response tidak boleh digunakan sebagai implementasi
production.

Mock hanya diperbolehkan untuk:

-   unit testing;
-   UI testing;
-   development sementara;
-   prototyping yang memang dinyatakan sebagai prototype.

Jika sebuah feature production membutuhkan API, implementasikan API
Laravel yang sebenarnya.

------------------------------------------------------------------------

## Rule 13 --- No Hardcoded Business Data

Business data tidak boleh ditanam langsung ke source code.

Contoh yang dilarang:

``` kotlin
val price = 5000
val courierName = "Budi"
val depotName = "Berkah Water"
val orderStatus = "Selesai"
```

jika nilai tersebut merupakan data bisnis dinamis.

Data bisnis harus berasal dari source of truth yang sesuai:

``` text
Database
   ↓
Laravel API
   ↓
Repository
   ↓
ViewModel
   ↓
Compose UI
```

Konstanta teknis boleh hardcoded jika memang bukan business data.

------------------------------------------------------------------------

## Rule 14 --- No Duplicate Modules

Jangan mengulang module yang sudah selesai.

Sebelum membuat module:

1.  cari implementasi existing;
2.  cek route;
3.  cek API;
4.  cek screen;
5.  cek model;
6.  cek migration;
7.  cek service/repository;
8.  tentukan apakah feature benar-benar belum ada.

Jika module sudah ada tetapi memiliki bug:

> Fix existing module.

Bukan:

> Create second module.

------------------------------------------------------------------------

## Rule 15 --- Every Change Must Be Tested

Setiap perubahan code wajib diuji.

Minimum:

``` text
Implement
   ↓
Run formatter/linter jika tersedia
   ↓
Build / compile
   ↓
Run relevant test
   ↓
Check API jika backend berubah
   ↓
Check affected UI jika Android berubah
```

Perubahan yang tidak diuji tidak dianggap selesai.

------------------------------------------------------------------------

## Rule 16 --- One Module, One Commit

Satu module/feature yang telah selesai harus diselesaikan dalam satu
commit utama, bukan satu commit untuk setiap file.

Contoh yang benar:

``` text
feat: implement order management module
```

Commit tersebut dapat mencakup:

``` text
Controller
Model
Migration
API Route
Repository
ViewModel
Composable
Tests
Documentation
```

Contoh yang tidak diinginkan:

``` text
commit 1: add model
commit 2: add controller
commit 3: add route
commit 4: add screen
commit 5: add repository
commit 6: fix screen
```

Commit granular hanya digunakan jika memang diperlukan untuk alasan
teknis, recovery, atau kolaborasi tertentu.

------------------------------------------------------------------------

## Rule 17 --- Fix Errors Before Continuing

Jika ditemukan error blocking:

``` text
STOP
 ↓
Identify
 ↓
Reproduce
 ↓
Fix
 ↓
Test
 ↓
Continue
```

Jangan menumpuk feature baru di atas error yang belum dipahami.

Khusus error compile/build:

> Build harus kembali berhasil sebelum pekerjaan feature berikutnya
> dilanjutkan, kecuali error tersebut telah diisolasi dan terdokumentasi
> sebagai unrelated existing issue.

------------------------------------------------------------------------

## Rule 18 --- Unresolved Decision

Jika requirement belum jelas, jangan membuat keputusan bisnis secara
sepihak.

Gunakan status:

``` text
UNRESOLVED DECISION
```

Format:

``` markdown
### UD-XXX — Judul Keputusan

**Question:**  
Apa yang belum ditentukan?

**Context:**  
Mengapa keputusan diperlukan?

**Options:**  
- Option A
- Option B

**Impact:**  
Apa dampaknya terhadap sistem?

**Decision:**  
UNRESOLVED
```

AI/developer tidak boleh mengubah unresolved decision menjadi
requirement final tanpa persetujuan.

------------------------------------------------------------------------

## Rule 19 --- No Invented Business Requirements

Jangan mengarang:

-   harga;
-   biaya pengantaran;
-   minimum order;
-   jam operasional;
-   status pesanan baru;
-   kebijakan pembatalan;
-   aturan refund;
-   aturan pembayaran;
-   role baru;
-   diskon;
-   voucher;
-   promo;
-   komisi;
-   kebijakan wilayah pengantaran;

jika belum ditentukan oleh requirement resmi.

Jika dibutuhkan untuk implementasi, tandai sebagai unresolved decision.

------------------------------------------------------------------------

## Rule 20 --- Master Specification Is the Source of Truth

Semua implementasi harus mengacu kepada:

``` text
00_KYUSUI_MASTER_SPECIFICATION.md
```

Sebelum mengimplementasikan feature:

1.  identifikasi requirement terkait;
2.  cek business rule;
3.  cek scope;
4.  cek acceptance criteria;
5.  cek apakah requirement masih unresolved;
6.  implementasikan hanya bagian yang sudah valid.

Jika implementasi bertentangan dengan master specification:

> Master specification harus menang.

------------------------------------------------------------------------

# 4. CODING RULES

## 4.1 General Coding Principles

Gunakan:

-   readable code;
-   single responsibility;
-   separation of concerns;
-   low coupling;
-   high cohesion;
-   explicit data flow;
-   predictable state management;
-   reusable components;
-   meaningful naming.

Hindari:

-   giant class;
-   giant composable;
-   duplicated business logic;
-   magic values;
-   hidden side effects;
-   unnecessary abstraction;
-   premature optimization.

------------------------------------------------------------------------

## 4.2 Android Architecture

Gunakan pola:

``` text
Compose UI
    ↓
ViewModel
    ↓
Use Case / Repository bila diperlukan
    ↓
Retrofit API
    ↓
Laravel REST API
```

Business logic tidak boleh diletakkan secara berlebihan di Composable.

Composable bertanggung jawab terutama terhadap:

-   rendering;
-   UI state;
-   user interaction callback.

ViewModel bertanggung jawab terhadap:

-   UI state;
-   event handling;
-   orchestration;
-   lifecycle-aware state.

Repository bertanggung jawab terhadap akses data.

------------------------------------------------------------------------

## 4.3 State Management

Gunakan `StateFlow` sebagai mekanisme utama observable UI state.

State harus memiliki kondisi yang jelas, misalnya:

``` text
Loading
Success
Error
Empty
```

Jangan membuat state tersebar tanpa alasan di banyak tempat.

------------------------------------------------------------------------

## 4.4 Navigation

Navigation Compose harus digunakan untuk navigasi antar-screen.

Route harus:

-   konsisten;
-   unik;
-   memiliki naming convention;
-   tidak menggunakan string acak;
-   tidak menyimpan business logic di route.

------------------------------------------------------------------------

## 4.5 Networking

Retrofit digunakan sebagai HTTP client abstraction.

OkHttp digunakan sebagai HTTP transport/interceptor layer bila
diperlukan.

Network layer harus menangani:

-   authentication;
-   timeout;
-   error response;
-   HTTP status;
-   serialization;
-   logging development secara aman.

Sensitive data tidak boleh muncul pada production logs.

------------------------------------------------------------------------

## 4.6 Local Storage

DataStore digunakan untuk preference/token/session-related local state
yang sesuai.

Jangan menggunakan DataStore sebagai pengganti database server.

Jangan menyimpan:

-   password plaintext;
-   secret key;
-   payment provider secret;
-   database credentials;

di DataStore.

Jika local relational persistence diperlukan, Room dapat digunakan
sesuai kebutuhan teknis, tetapi MySQL tetap menjadi server source of
truth.

------------------------------------------------------------------------

## 4.7 Maps and Location

Google Maps SDK dan Fused Location Provider digunakan untuk fungsi
lokasi.

Location permission harus:

-   diminta pada waktu yang tepat;
-   dijelaskan melalui UX yang sesuai;
-   ditangani ketika ditolak;
-   tidak diasumsikan selalu tersedia.

Location data harus diperlakukan sebagai data sensitif secara
operasional.

Tracking hanya berjalan sesuai business context pengantaran.

------------------------------------------------------------------------

# 5. BACKEND CODING RULES

## 5.1 Laravel Structure

Gunakan struktur Laravel yang idiomatis.

Komponen dapat mencakup:

``` text
Routes
Controllers
Requests
Models
Policies
Services
Resources
Jobs
Events
Notifications
```

Gunakan komponen hanya jika memang memberikan tanggung jawab yang jelas.

------------------------------------------------------------------------

## 5.2 Controller Rules

Controller harus tetap tipis.

Controller terutama bertanggung jawab untuk:

1.  menerima request;
2.  memvalidasi/menggunakan Form Request;
3.  memanggil service/repository/domain logic bila diperlukan;
4.  menghasilkan response.

Business logic kompleks jangan ditumpuk di controller.

------------------------------------------------------------------------

## 5.3 Validation

Validasi harus dilakukan di server.

Jangan hanya mengandalkan validasi Android.

Client-side validation:

``` text
UX protection
```

Server-side validation:

``` text
Security + business integrity
```

Keduanya bukan pengganti satu sama lain.

------------------------------------------------------------------------

## 5.4 Authorization

Authorization harus diterapkan di backend.

Jangan percaya:

``` text
role
user_id
courier_id
order_id
```

yang dikirim client tanpa verifikasi.

Server harus memeriksa:

-   authenticated user;
-   role;
-   ownership;
-   relationship;
-   permission.

------------------------------------------------------------------------

## 5.5 API Response

Response API harus konsisten.

Gunakan struktur response yang terdokumentasi dan konsisten untuk:

-   success;
-   validation error;
-   unauthorized;
-   forbidden;
-   not found;
-   server error.

Jangan mengubah format response secara sembarangan karena dapat merusak
Android client.

------------------------------------------------------------------------

## 5.6 API Versioning

Jika versioning API diperlukan, gunakan convention yang konsisten,
misalnya:

``` text
/api/v1/...
```

Jangan membuat endpoint versi baru hanya untuk menghindari memperbaiki
endpoint existing.

------------------------------------------------------------------------

# 6. DATABASE RULES

## 6.1 MySQL Source of Truth

Data bisnis utama harus disimpan pada MySQL.

Contoh:

-   users;
-   roles;
-   orders;
-   order items jika diperlukan;
-   payments;
-   couriers;
-   locations/tracking jika diperlukan;
-   related business entities.

Nama dan struktur tabel harus mengikuti desain database yang telah
disetujui.

------------------------------------------------------------------------

## 6.2 Migration First

Perubahan schema harus dilakukan melalui Laravel migration.

Jangan mengubah production schema secara manual tanpa migration yang
sesuai.

Setiap migration harus dapat:

-   dipahami;
-   dijalankan;
-   direproduksi;
-   direview.

------------------------------------------------------------------------

## 6.3 Foreign Keys

Gunakan foreign key ketika hubungan antar entity memang membutuhkan
referential integrity.

Jangan menggunakan foreign key secara asal hanya karena dua tabel
terlihat berkaitan.

------------------------------------------------------------------------

## 6.4 Data Integrity

Database harus menjaga:

-   uniqueness;
-   required fields;
-   valid references;
-   valid status;
-   consistency.

Business validation tetap dilakukan pada application layer.

------------------------------------------------------------------------

## 6.5 Money

Nilai uang tidak boleh menggunakan floating point untuk perhitungan
finansial.

Gunakan representasi yang aman, misalnya integer dalam satuan terkecil
atau decimal sesuai kebutuhan database dan payment integration.

------------------------------------------------------------------------

# 7. PAYMENT RULES

## 7.1 Payment Architecture

Payment KYŪSUI menggunakan pendekatan sederhana tanpa payment gateway:

``` text
QRIS : Static QRIS + Customer Proof Upload + Manual Owner Verification
CASH : Cash on Delivery + Courier Confirmation
```

Backend tetap menjadi satu-satunya authority untuk payment amount, payment
record, payment status, dan actor authorization. Android hanya mengirim
request yang diperbolehkan dan menampilkan state dari backend.

------------------------------------------------------------------------

## 7.2 Supported Payment Methods

Hanya dua metode payment yang diperbolehkan:

``` text
QRIS
CASH
```

Tidak boleh menambahkan payment method lain tanpa perubahan requirement
yang terdokumentasi dan disetujui.

------------------------------------------------------------------------

## 7.3 QRIS Rules

QRIS menggunakan QRIS statis. Sistem tidak melakukan integrasi payment
gateway dan tidak menerima callback/webhook dari provider payment.

Alur canonical:

``` text
PENDING
   ↓
Customer pays via static QRIS
   ↓
Customer uploads payment proof
   ↓
WAITING_VERIFICATION
   ↓
Owner verifies proof
   ├── APPROVED → PAID
   └── REJECTED → PENDING
```

Aturan wajib:

1. Customer tidak boleh menetapkan payment menjadi `PAID`.
2. Customer hanya dapat mengirim atau mengunggah bukti pembayaran sesuai
   endpoint yang disediakan backend.
3. Owner adalah actor yang berwenang memverifikasi bukti QRIS.
4. Backend memvalidasi order, payment record, ownership/role, dan state
   transition sebelum menerima keputusan verifikasi.
5. Re-upload bukti pembayaran tidak membuat payment record baru untuk order
   yang sama.
6. Bukti pembayaran harus disimpan melalui mekanisme storage yang telah
   ditetapkan dan tidak boleh dijadikan sumber kebenaran status tanpa
   verifikasi Owner.

------------------------------------------------------------------------

## 7.4 CASH Rules

CASH menggunakan Cash on Delivery. Customer membayar uang tunai kepada
Courier saat proses pengantaran sesuai workflow order.

Alur canonical:

``` text
PENDING
   ↓
Courier receives cash
   ↓
Courier confirms "Uang Diterima"
   ↓
Backend validates courier assignment
   ↓
PAID
```

Aturan wajib:

1. Memilih metode CASH tidak otomatis membuat payment `PAID`.
2. Hanya Courier yang ditugaskan pada order yang boleh mengonfirmasi
   penerimaan uang tunai.
3. Owner tidak digunakan sebagai actor konfirmasi payment CASH.
4. Backend wajib memverifikasi assignment Courier sebelum mengubah status
   payment menjadi `PAID`.
5. Customer tidak dapat mengubah payment CASH menjadi `PAID` dari aplikasi.

------------------------------------------------------------------------

## 7.5 Canonical Payment Status

Status payment yang digunakan sebagai state canonical adalah:

``` text
PENDING
WAITING_VERIFICATION
PAID
```

Makna:

- `PENDING` — payment belum dinyatakan lunas oleh backend.
- `WAITING_VERIFICATION` — bukti QRIS sudah dikirim dan menunggu verifikasi
  Owner.
- `PAID` — backend telah menerima dan mengesahkan payment melalui actor
  dan workflow yang sah.

Untuk QRIS:

``` text
PENDING → WAITING_VERIFICATION → PAID
                         └──────→ PENDING (rejected)
```

Untuk CASH:

``` text
PENDING → PAID
```

Tidak boleh membuat status payment tambahan hanya untuk menggantikan state
yang sudah didefinisikan tanpa perubahan specification.

------------------------------------------------------------------------

## 7.6 Payment and Order State Separation

Payment status dan order status adalah dua state machine yang berbeda.

``` text
Payment State ≠ Order State
```

Payment `PAID` tidak otomatis berarti order `COMPLETED`. Demikian juga
payment `PENDING` tidak boleh secara otomatis mengubah seluruh order state
tanpa aturan workflow yang secara eksplisit ditentukan.

Implementasi harus mengikuti workflow dan business rule yang ditetapkan
oleh dokumen terkait.

------------------------------------------------------------------------

## 7.7 Payment Amount Authority

Amount payment harus berasal dari backend/database sebagai source of truth.

Client tidak boleh menentukan ulang amount yang menjadi dasar payment
verification tanpa validasi server.

Backend harus memastikan amount yang diverifikasi berkaitan dengan order
yang benar.

------------------------------------------------------------------------

## 7.8 Payment Authorization

Authorization payment wajib berbasis authenticated user, role, ownership,
dan relationship terhadap order/payment.

``` text
Customer  → upload QRIS proof untuk order miliknya
Owner     → verify/reject QRIS proof sesuai kewenangannya
Courier   → confirm CASH hanya untuk order yang ditugaskan kepadanya
Backend   → authority untuk validasi dan perubahan state
```

Jangan mempercayai `user_id`, `order_id`, `payment_id`, atau `courier_id`
hanya karena dikirim oleh client.

------------------------------------------------------------------------

## 7.9 Payment Security

Tidak ada payment secret provider yang boleh ditempatkan di Android.

Karena KYŪSUI tidak menggunakan payment gateway, maka artefak berikut
tidak boleh menjadi dependency project:

``` text
MIDTRANS_SERVER_KEY
MIDTRANS_CLIENT_KEY
Payment Gateway SDK
Payment Gateway Webhook
Provider Dynamic QRIS API
```

Jika implementasi lama masih memiliki artefak tersebut, artefak harus
dihapus atau dinonaktifkan setelah impact analysis dan pengujian.

------------------------------------------------------------------------

## 7.10 Idempotency and Duplicate Actions

Backend harus mencegah perubahan payment yang tidak sah akibat request
berulang, double tap, retry, atau duplicate submission.

Contoh:

``` text
QRIS verification APPROVED yang diulang
CASH confirmation yang diulang
Upload proof berulang
```

Request berulang tidak boleh menghasilkan payment record ganda atau
state transition ilegal.

------------------------------------------------------------------------

## 7.11 Payment Auditability

Perubahan payment penting harus dapat ditelusuri minimal melalui data
transaksi dan actor yang melakukan action.

Contoh action yang perlu dapat ditelusuri:

- QRIS proof uploaded;
- QRIS verification approved;
- QRIS verification rejected;
- CASH received confirmation;
- payment status changed.

Detail audit mengikuti schema dan specification database yang berlaku.

------------------------------------------------------------------------

## 7.12 Legacy Payment Rule

Aturan lama yang menyatakan:

``` text
Digital Payment = Midtrans
Payment Gateway = mandatory
Cash = Owner confirmation
```

tidak berlaku pada baseline Project Rules ini.

Jika ditemukan pada dokumen, code, environment variable, dependency, test,
atau API contract, tandai sebagai **LEGACY PAYMENT CONFLICT** dan lakukan
sinkronisasi.

------------------------------------------------------------------------

# 8. SECURITY RULES

## 8.1 General

Security adalah tanggung jawab seluruh layer.

``` text
Android
  +
API
  +
Backend
  +
Database
  +
External Services
```

------------------------------------------------------------------------

## 8.2 Secrets

Jangan commit:

-   API keys;
-   private keys;
-   tokens;
-   passwords;
-   database credentials;
-   payment secrets;
-   Firebase service credentials.

Gunakan environment configuration yang sesuai.

------------------------------------------------------------------------

## 8.3 Environment Files

File seperti `.env` yang berisi secret tidak boleh di-commit.

Gunakan:

``` text
.env.example
```

untuk template configuration tanpa secret.

------------------------------------------------------------------------

## 8.4 Input Validation

Semua input dari client dianggap tidak terpercaya.

Validasi:

-   type;
-   format;
-   length;
-   range;
-   ownership;
-   relationship;
-   authorization.

------------------------------------------------------------------------

## 8.5 Mass Assignment

Laravel model harus menggunakan mekanisme mass-assignment protection
secara tepat.

Jangan menerima field sensitif dari client hanya karena field tersebut
tersedia pada request.

------------------------------------------------------------------------

## 8.6 SQL Injection

Gunakan Laravel Eloquent/query builder atau parameterized query.

Jangan membuat raw SQL dengan concatenation input user.

------------------------------------------------------------------------

## 8.7 IDOR / Broken Access Control

Setiap endpoint yang menerima ID resource harus memverifikasi
authorization.

Contoh:

``` text
GET /orders/123
```

tidak berarti user boleh melihat order 123.

Server harus memastikan order tersebut memang boleh diakses user
tersebut.

------------------------------------------------------------------------

## 8.8 Location Privacy

Data lokasi pengantar dan pelanggan harus dibatasi sesuai kebutuhan
feature.

Jangan memberikan location data kepada user yang tidak berhak.

------------------------------------------------------------------------

## 8.9 Logging

Log harus membantu debugging tanpa membocorkan:

-   password;
-   token;
-   secret;
-   payment credential;
-   data sensitif yang tidak diperlukan.

------------------------------------------------------------------------

## 8.10 HTTPS

Komunikasi production harus menggunakan HTTPS.

HTTP hanya boleh digunakan untuk development lokal apabila diperlukan.

------------------------------------------------------------------------

# 9. GIT RULES

## 9.1 Repository

Gunakan satu repository/codebase utama untuk KYŪSUI.

------------------------------------------------------------------------

## 9.2 Branching

Gunakan branch berdasarkan feature/fix yang jelas.

Contoh:

``` text
main
feature/authentication
feature/order-management
feature/payment
feature/tracking
feature/notification
fix/order-validation
```

Nama branch harus menggambarkan pekerjaan.

------------------------------------------------------------------------

## 9.3 Main Branch

`main` harus selalu dijaga agar berada dalam kondisi yang dapat dibangun
dan diuji.

Jangan melakukan commit feature setengah jadi ke `main` jika hal
tersebut membuat project tidak dapat dibuild.

------------------------------------------------------------------------

## 9.4 Commit Convention

Gunakan Conventional Commits.

Format:

``` text
type(scope): description
```

Contoh:

``` text
feat(order): implement order management
feat(payment): implement QRIS and CASH payment flow
feat(tracking): implement courier location tracking
fix(auth): fix Sanctum token handling
test(order): add order workflow tests
docs(api): update order API documentation
refactor(location): simplify location repository
```

------------------------------------------------------------------------

## 9.5 Commit Boundary

Satu module selesai → satu commit utama.

Contoh:

``` text
feature/order-management
    ↓
implementation
    ↓
testing
    ↓
documentation
    ↓
single module commit
```

Jangan memecah satu module menjadi commit per file.

------------------------------------------------------------------------

## 9.6 Before Commit

Sebelum commit:

``` text
git status
git diff
run tests
run build
check changed files
check secrets
```

Pastikan tidak ada:

-   `.env`;
-   generated secrets;
-   debug code;
-   temporary files;
-   test credentials;
-   unrelated modifications.

------------------------------------------------------------------------

## 9.7 Commit Message Accuracy

Commit message harus menggambarkan perubahan nyata.

Jangan menulis:

``` text
feat: complete everything
```

untuk perubahan kecil.

Jangan mengklaim feature selesai jika acceptance criteria belum
terpenuhi.

------------------------------------------------------------------------

# 10. TESTING RULES

## 10.1 Testing Is Mandatory

Feature dianggap selesai hanya jika telah diuji.

------------------------------------------------------------------------

## 10.2 Testing Levels

Gunakan level pengujian sesuai kebutuhan:

``` text
Unit Test
Integration Test
API Test
UI Test
End-to-End / Workflow Test
Manual Test
```

Tidak semua feature harus memiliki semua jenis test, tetapi feature
kritis harus memiliki coverage pengujian yang memadai.

------------------------------------------------------------------------

## 10.3 Backend Testing

Minimal test untuk business-critical API:

-   authentication;
-   authorization;
-   order creation;
-   order ownership;
-   payment status;
-   courier assignment;
-   order status;
-   tracking access.

------------------------------------------------------------------------

## 10.4 Android Testing

Minimal pengujian untuk feature Android:

-   screen rendering;
-   loading state;
-   success state;
-   error state;
-   navigation;
-   API failure;
-   permission denial jika berkaitan dengan location;
-   authentication state.

------------------------------------------------------------------------

## 10.5 Payment Testing

Payment wajib diuji berdasarkan dua flow final: QRIS dan CASH. Tidak ada
ketergantungan sandbox payment gateway.

Minimal test QRIS:

``` text
Create payment record
PENDING state
Upload valid proof
WAITING_VERIFICATION state
Owner approves proof
PAID state
Owner rejects proof
Return to PENDING
Unauthorized verification attempt
Invalid proof submission
Duplicate upload / retry
Duplicate verification
```

Minimal test CASH:

``` text
Create payment record
PENDING state
Unassigned courier cannot confirm
Assigned courier confirms cash received
PAID state
Unauthorized courier confirmation
Duplicate cash confirmation
```

Test juga harus memastikan payment state tidak secara otomatis menyelesaikan
order kecuali workflow order memang mendefinisikannya.

## 10.6 Location Testing

Tracking harus diuji untuk kondisi:

-   GPS aktif;
-   GPS mati;
-   permission granted;
-   permission denied;
-   internet aktif;
-   internet terputus;
-   lokasi berubah;
-   aplikasi berpindah state;
-   courier belum ditugaskan;
-   order bukan delivery-active.

------------------------------------------------------------------------

## 10.7 Regression Testing

Setelah feature baru selesai, pastikan feature yang sudah selesai
sebelumnya tidak rusak.

Prioritas regression:

``` text
Authentication
↓
Order
↓
Payment
↓
Delivery
↓
Tracking
↓
History
```

------------------------------------------------------------------------

# 11. NAMING CONVENTION

## 11.1 General

Nama harus:

-   deskriptif;
-   konsisten;
-   menggunakan terminology domain;
-   tidak ambigu.

Hindari:

``` text
data1
dataBaru
temp
foo
test2
newData
```

------------------------------------------------------------------------

## 11.2 Kotlin

Gunakan Kotlin convention:

``` text
Class / Object       : PascalCase
Function             : camelCase
Variable             : camelCase
Constant             : UPPER_SNAKE_CASE
Package              : lowercase
```

Contoh:

``` kotlin
class OrderViewModel

fun createOrder()

val selectedOrder

const val DEFAULT_PAGE_SIZE = 20
```

------------------------------------------------------------------------

## 11.3 Compose

Composable menggunakan PascalCase:

``` kotlin
@Composable
fun OrderScreen()

@Composable
fun OrderCard()
```

Jangan memberi nama Composable seperti variable.

------------------------------------------------------------------------

## 11.4 Laravel

Gunakan Laravel/PHP convention:

``` text
Class        : PascalCase
Method       : camelCase
Variable     : camelCase
Migration    : Laravel convention
Table        : snake_case
Column       : snake_case
Route        : kebab-case
```

Contoh:

``` text
OrderController
PaymentService
CourierAssignmentRequest
order_items
payment_status
/api/orders
/api/courier-assignments
```

------------------------------------------------------------------------

## 11.5 Database

Gunakan:

``` text
snake_case
```

Contoh:

``` text
users
orders
order_items
payments
courier_locations
created_at
updated_at
```

Gunakan nama singular/plural secara konsisten dengan convention Laravel.

------------------------------------------------------------------------

## 11.6 API JSON

Gunakan format yang konsisten.

Contoh:

``` json
{
  "data": {
    "id": 1,
    "order_status": "processing"
  }
}
```

Jangan mencampur:

``` text
order_status
orderStatus
OrderStatus
```

dalam API yang sama tanpa alasan yang terdokumentasi.

------------------------------------------------------------------------

# 12. API DESIGN RULES

## 12.1 Resource-Oriented Endpoints

Gunakan endpoint berbasis resource.

Contoh:

``` text
GET    /api/orders
POST   /api/orders
GET    /api/orders/{order}
PUT    /api/orders/{order}
DELETE /api/orders/{order}
```

Gunakan action endpoint hanya jika operasi memang merupakan action yang
jelas.

------------------------------------------------------------------------

## 12.2 HTTP Methods

Gunakan:

``` text
GET     Read
POST    Create / action
PUT     Full update
PATCH   Partial update
DELETE  Delete
```

------------------------------------------------------------------------

## 12.3 HTTP Status Codes

Gunakan HTTP status code sesuai makna response.

Contoh:

``` text
200 OK
201 Created
204 No Content
400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
422 Unprocessable Entity
429 Too Many Requests
500 Internal Server Error
```

Jangan mengembalikan `200 OK` untuk semua kondisi error.

------------------------------------------------------------------------

# 13. UI/UX RULES

## 13.1 UI Must Follow Approved Design

Implementasi UI harus mengacu pada hasil UI/UX yang telah disetujui.

Jangan membuat redesign besar secara sepihak.

------------------------------------------------------------------------

## 13.2 State Must Be Visible

UI harus memiliki state yang sesuai:

``` text
Loading
Success
Empty
Error
Disabled
```

Jangan membiarkan screen terlihat blank ketika API gagal atau data
kosong.

------------------------------------------------------------------------

## 13.3 Error Message

Error harus:

-   jelas;
-   relevan;
-   tidak membocorkan detail internal;
-   membantu user memahami tindakan berikutnya.

Jangan menampilkan:

``` text
SQLSTATE[HY000]...
Stack trace...
Undefined index...
```

kepada user.

------------------------------------------------------------------------

## 13.4 Loading

Gunakan loading indicator ketika operasi membutuhkan waktu.

Hindari UI yang terlihat tidak responsif.

------------------------------------------------------------------------

## 13.5 Accessibility

Perhatikan:

-   readable text;
-   sufficient touch target;
-   content descriptions;
-   contrast;
-   keyboard/input behavior;
-   error feedback.

------------------------------------------------------------------------

# 14. NOTIFICATION RULES

Firebase Cloud Messaging digunakan untuk push notification.

Notification harus memiliki business purpose yang jelas.

Contoh event yang dapat memerlukan notification sesuai requirement:

-   perubahan status pesanan;
-   informasi proses pengantaran;
-   informasi pembayaran;
-   pesanan selesai.

Jangan membuat notification baru hanya karena secara teknis FCM
tersedia.

Setiap notification harus:

1.  memiliki trigger yang jelas;
2.  memiliki recipient yang jelas;
3.  memiliki permission/authorization context;
4.  tidak membocorkan data sensitif;
5.  dapat ditelusuri ke business event.

------------------------------------------------------------------------

# 15. LOCATION AND TRACKING RULES

## 15.1 Location Ownership

Location courier adalah data milik proses delivery dan tidak boleh
diperlakukan sebagai data publik.

------------------------------------------------------------------------

## 15.2 Active Tracking

Tracking hanya boleh aktif sesuai konteks pengantaran yang sah.

------------------------------------------------------------------------

## 15.3 Location Permission

Aplikasi tidak boleh mengasumsikan permission lokasi selalu diberikan.

Jika permission ditolak:

``` text
Permission denied
    ↓
Show understandable explanation
    ↓
Provide valid recovery action
```

------------------------------------------------------------------------

## 15.4 Location Accuracy

Jangan membuat klaim akurasi tertentu jika belum ada
requirement/pengujian yang mendukung.

------------------------------------------------------------------------

## 15.5 Offline

Perilaku ketika courier kehilangan koneksi atau GPS belum ditentukan
sepenuhnya oleh master specification.

Jika implementasi membutuhkan keputusan:

``` text
UNRESOLVED DECISION
```

harus dibuat sebelum mengunci perilaku bisnis.

------------------------------------------------------------------------

# 16. DOCUMENTATION RULES

Dokumentasi wajib diperbarui ketika perubahan berdampak pada:

-   architecture;
-   database;
-   API;
-   authentication;
-   payment;
-   tracking;
-   notification;
-   workflow;
-   business rule.

Dokumentasi tidak boleh dibiarkan menggambarkan sistem lama ketika code
sudah berubah.

------------------------------------------------------------------------

## 16.1 Documentation Must Be Traceable

Setiap feature penting harus dapat ditelusuri:

``` text
Requirement
   ↓
Design
   ↓
Implementation
   ↓
Test
   ↓
Commit
```

------------------------------------------------------------------------

## 16.2 No Fake Documentation

Jangan mendokumentasikan feature sebagai:

``` text
Implemented
Completed
Production Ready
```

jika feature belum memenuhi acceptance criteria.

------------------------------------------------------------------------

## 16.3 Technical Decision Record

Keputusan teknis penting harus didokumentasikan jika berdampak terhadap
architecture atau future development.

Contoh:

``` markdown
### ADR-001 — Authentication Strategy

Decision:
Laravel Sanctum digunakan sebagai authentication mechanism.

Reason:
...
Impact:
...
Status:
Accepted
```

------------------------------------------------------------------------

# 17. ENVIRONMENT RULES

Environment harus dibedakan:

``` text
local
testing
production
```

Configuration yang berbeda antar environment tidak boleh dicampur.

Contoh:

``` text
APP_ENV
APP_DEBUG
DATABASE_*
FCM_*
API_BASE_URL
QRIS_*
```

Production secret tidak boleh digunakan di local repository.

------------------------------------------------------------------------

# 18. DEPENDENCY RULES

Dependency baru hanya boleh ditambahkan jika:

1.  ada kebutuhan nyata;
2.  tidak dapat diselesaikan dengan existing stack secara wajar;
3.  kompatibel dengan project;
4.  memiliki maintenance yang layak;
5.  tidak bertentangan dengan stack;
6.  tidak menambah kompleksitas tanpa manfaat yang sebanding.

Jangan menambahkan library hanya karena sedang populer.

Setelah dependency ditambahkan:

-   lockfile harus diperbarui;
-   project harus dapat dibuild;
-   dependency harus terdokumentasi bila penting;
-   security risk harus dipertimbangkan.

------------------------------------------------------------------------

# 19. AI / ASSISTED DEVELOPMENT RULES

AI boleh digunakan untuk membantu:

-   analisis;
-   desain;
-   coding;
-   debugging;
-   testing;
-   dokumentasi.

Namun AI tidak menjadi sumber kebenaran requirement.

AI wajib:

1.  membaca `00_KYUSUI_MASTER_SPECIFICATION.md`;
2.  membaca `01_KYUSUI_PROJECT_RULES.md`;
3.  memeriksa existing implementation;
4.  tidak mengarang requirement;
5.  tidak mengganti stack;
6.  tidak membuat project kedua;
7.  tidak membuat duplicate module;
8.  tidak menghapus implementation yang masih benar;
9.  menguji perubahan;
10. menandai unresolved decision.

Jika AI menemukan requirement yang tidak jelas:

``` text
DO NOT GUESS
→ Mark UNRESOLVED DECISION
```

------------------------------------------------------------------------

# 20. DEVELOPMENT WORKFLOW

Workflow standar:

``` text
1. Read Master Specification
        ↓
2. Read Project Rules
        ↓
3. Inspect Existing Codebase
        ↓
4. Identify Requirement
        ↓
5. Check Scope
        ↓
6. Check Existing Implementation
        ↓
7. Identify Dependencies
        ↓
8. Design
        ↓
9. Implement
        ↓
10. Test
        ↓
11. Regression Check
        ↓
12. Update Documentation
        ↓
13. Review Diff
        ↓
14. Commit One Completed Module
```

Tidak boleh langsung coding tanpa memahami existing codebase untuk
feature yang sudah memiliki dependency.

------------------------------------------------------------------------

# 21. MODULE COMPLETION DEFINITION

Sebuah module hanya boleh dinyatakan **DONE** apabila:

-   requirement sudah jelas;
-   scope sudah jelas;
-   implementation selesai;
-   API bekerja jika diperlukan;
-   database bekerja jika diperlukan;
-   UI bekerja jika diperlukan;
-   authorization bekerja;
-   validation bekerja;
-   error handling tersedia;
-   relevant tests berhasil;
-   regression check dilakukan;
-   dokumentasi diperbarui;
-   tidak ada known blocking error;
-   commit module sudah dibuat.

Format status:

``` text
PLANNED
   ↓
IN PROGRESS
   ↓
TESTING
   ↓
DONE
```

Jangan menggunakan:

``` text
DONE
```

hanya karena source code sudah dibuat.

------------------------------------------------------------------------

# 22. DEFINITION OF DONE

Feature KYŪSUI dianggap selesai jika seluruh kondisi berikut terpenuhi:

``` text
[ ] Requirement valid
[ ] Tidak conflict dengan Master Specification
[ ] Tidak conflict dengan Project Rules
[ ] Existing implementation sudah diperiksa
[ ] Tidak membuat duplicate module
[ ] Backend implemented jika diperlukan
[ ] Database implemented jika diperlukan
[ ] Android implemented jika diperlukan
[ ] Authentication/authorization diuji
[ ] Validation diuji
[ ] Error state diuji
[ ] Integration diuji
[ ] Regression check dilakukan
[ ] Documentation diperbarui
[ ] Secrets aman
[ ] Build berhasil
[ ] Test relevan berhasil
[ ] Git diff direview
[ ] Satu module menghasilkan satu commit utama
```

------------------------------------------------------------------------

# 23. CHANGE MANAGEMENT

Perubahan requirement harus dikontrol.

Jika ada perubahan:

``` text
Requirement Change
       ↓
Impact Analysis
       ↓
Update Master Specification
       ↓
Update Related Design
       ↓
Update Implementation
       ↓
Update Test
       ↓
Document Change
```

Jangan mengubah implementation lebih dahulu kemudian menyesuaikan
dokumentasi untuk membenarkan perubahan tersebut.

------------------------------------------------------------------------

# 24. FORBIDDEN PRACTICES

Praktik berikut dilarang:

1.  Mengganti Kotlin dengan Flutter tanpa approval.
2.  Mengganti Laravel dengan backend lain tanpa approval.
3.  Mengganti MySQL dengan database lain sebagai primary database tanpa
    approval.
4.  Membuat project kedua.
5.  Membuat duplicate module.
6.  Membuat dummy API untuk production.
7.  Hardcode business data.
8.  Hardcode secret.
9.  Menaruh payment secret di Android.
10. Mengakses database langsung dari Android.
11. Bypass authorization.
12. Menghapus code yang masih benar tanpa alasan.
13. Membuat feature yang belum ada requirement-nya.
14. Menyatakan feature selesai tanpa testing.
15. Commit satu file demi satu file untuk satu module tanpa alasan.
16. Membiarkan blocking error lalu melanjutkan feature lain.
17. Mengarang business rule.
18. Mengubah Master Specification hanya untuk menyesuaikan implementasi
    yang salah.
19. Mengabaikan regression.
20. Menyimpan password plaintext.
21. Menampilkan sensitive error kepada user.
22. Menggunakan production credentials pada source control.
23. Menjadikan AI output sebagai pengganti keputusan bisnis.
24. Menganggap jurnal sebagai sumber requirement bisnis.
25. Menjadikan Midtrans atau payment gateway sebagai dependency wajib.
26. Menggunakan payment gateway/webhook provider untuk flow payment final KYŪSUI.
27. Mengizinkan Customer menetapkan payment `PAID`.
28. Mengizinkan Owner mengonfirmasi payment CASH.
29. Mengizinkan Courier yang tidak ditugaskan mengonfirmasi payment CASH.
30. Membuat payment record baru setiap kali bukti QRIS diunggah ulang untuk order yang sama.
31. Menganggap pilihan CASH sebagai bukti bahwa payment sudah `PAID`.

------------------------------------------------------------------------

# 25. DECISION PRIORITY

Ketika developer harus memilih tindakan:

``` text
1. Security
2. Data integrity
3. Business requirement
4. Existing correct architecture
5. Maintainability
6. Testability
7. Performance
8. Convenience
```

Kemudahan coding tidak boleh mengalahkan security, data integrity, atau
business requirement.

------------------------------------------------------------------------

# 26. QUICK REFERENCE

## Stack

``` text
Android       = Kotlin + Jetpack Compose
Architecture  = MVVM
State         = ViewModel + StateFlow
Navigation    = Navigation Compose
HTTP          = Retrofit + OkHttp
Local         = DataStore
Maps          = Google Maps SDK
Location      = Fused Location Provider

Backend       = Laravel 13
PHP           = 8.3+
API           = REST
Auth          = Laravel Sanctum

Database      = MySQL 8.x

Payment       = QRIS + CASH
QRIS          = Static QRIS + Manual Owner Verification
CASH          = Cash on Delivery + Courier Confirmation
Payment State = PENDING / WAITING_VERIFICATION / PAID
Notification  = Firebase Cloud Messaging
```

## Golden Rules

``` text
ONE PLATFORM
ONE CODEBASE
ONE SOURCE OF TRUTH
NO STACK CHANGE WITHOUT APPROVAL
NO DUPLICATE MODULE
NO DUMMY PRODUCTION API
NO HARDCODED BUSINESS DATA
NO INVENTED REQUIREMENTS
TEST EVERY CHANGE
FIX BLOCKING ERROR FIRST
ONE MODULE = ONE MAIN COMMIT
MASTER SPECIFICATION = BUSINESS SOURCE OF TRUTH
```

------------------------------------------------------------------------

# 27. FINAL PRINCIPLE

KYŪSUI dikembangkan sebagai satu sistem yang konsisten dari requirement
sampai production.

Setiap perubahan harus menjawab empat pertanyaan:

``` text
1. Requirement-nya apa?
2. Implementasi existing yang terkait apa?
3. Dampaknya terhadap sistem apa?
4. Bagaimana perubahan tersebut dibuktikan melalui testing?
```

Jika jawabannya belum jelas, jangan menebak.

Gunakan:

``` text
UNRESOLVED DECISION
```

Jika implementasi lama masih benar, pertahankan.

Jika terjadi error, perbaiki sebelum menambah kompleksitas.

Jika requirement tidak ada, jangan mengarang.

Jika teknologi sudah dikunci, jangan mengganti.

Jika module sudah selesai, jangan membuat ulang.

Jika perubahan sudah selesai, uji, dokumentasikan, review, lalu commit
sebagai satu unit feature/module.

**KYŪSUI harus berkembang secara incremental, traceable, testable,
secure, dan konsisten terhadap `00_KYUSUI_MASTER_SPECIFICATION.md`.**
