# 03_KYUSUI_UI_UX_SPECIFICATION.md

**Project:** KYŪSUI\
**Study Case:** Berkah Water\
**Document:** `03_KYUSUI_UI_UX_SPECIFICATION.md`\
**Status:** UI/UX Design Baseline --- Rebuilt / Payment Synchronized\
**Platform:** Android Native\
**UI Technology:** Kotlin + Jetpack Compose + Material 3\
**Architecture:** MVVM + ViewModel + StateFlow + Repository\
**Backend:** Laravel 13 / PHP 8.3+\
**Database:** MySQL 8.x\
**Authentication:** Laravel Sanctum\
**Network:** Retrofit + OkHttp\
**Maps:** Google Maps SDK\
**Location:** Fused Location Provider\
**Notification:** Firebase Cloud Messaging

------------------------------------------------------------------------

## 1. Purpose

Dokumen ini mendefinisikan standar UI/UX KYŪSUI agar seluruh antarmuka
Android memiliki struktur, visual language, navigasi, interaction
pattern, dan state handling yang konsisten.

Dokumen ini menjadi dasar implementasi:

``` text
Jetpack Compose
       ↓
Screen
       ↓
ViewModel
       ↓
StateFlow
       ↓
Repository
       ↓
Retrofit + OkHttp
       ↓
Laravel REST API
```

KYŪSUI hanya menggunakan satu aplikasi Android dengan pengalaman UI yang
berubah berdasarkan role:

``` text
                    KYŪSUI Android
                         │
          ┌──────────────┼──────────────┐
          ↓              ↓              ↓
      Customer         Owner         Courier
          │              │              │
       Order          Manage         Delivery
       Payment        Orders         Tracking
       Tracking       Payment        Status
       History        Courier        Cash
                      QRIS
```

Dokumen ini mempertahankan seluruh UI/UX non-payment dari baseline
sebelumnya. Bagian payment direbuild agar konsisten dengan
`08_KYUSUI_PAYMENT_SPECIFICATION.md` rebuilt dan
`04_KYUSUI_SYSTEM_WORKFLOW.md` rebuilt.

------------------------------------------------------------------------

## 2. Source Authority and Consistency

UI/UX harus konsisten dengan:

``` text
00_KYUSUI_MASTER_SPECIFICATION.md
        ↓
01_KYUSUI_PROJECT_RULES.md
        ↓
02_KYUSUI_SYSTEM_ARCHITECTURE.md
        ↓
03_KYUSUI_UI_UX_SPECIFICATION.md
        ↓
04_KYUSUI_SYSTEM_WORKFLOW.md
        ↓
05_KYUSUI_DATABASE_SCHEMA.md
        ↓
06_KYUSUI_API_SPECIFICATION.md
        ↓
07_KYUSUI_ANDROID_ARCHITECTURE.md
        ↓
08_KYUSUI_PAYMENT_SPECIFICATION.md
```

Untuk implementasi Android, arsitektur menetapkan Kotlin + Jetpack
Compose + Material 3, MVVM + Repository, ViewModel + StateFlow,
Navigation Compose, Retrofit + OkHttp, DataStore, Google Maps SDK, Fused
Location Provider, dan FCM. UI tidak menjadi security boundary dan
business state tetap berasal dari backend.

Untuk payment, architecture terbaru hanya menggunakan `QRIS` dan `CASH`,
dengan status `PENDING`, `WAITING_VERIFICATION`, dan `PAID`. QRIS
menggunakan static QRIS Berkah Water dan bukti pembayaran yang
diverifikasi Owner. CASH dikonfirmasi oleh Courier yang menerima uang.
Tidak ada payment gateway, dynamic QRIS, provider transaction, provider
webhook, atau `payment_transactions`.

------------------------------------------------------------------------

## 3. Design Principles

### 3.1 Primary UX Principle

KYŪSUI harus terasa:

-   sederhana;
-   cepat dipahami;
-   task-oriented;
-   informatif;
-   konsisten;
-   aman;
-   mudah digunakan dengan satu tangan;
-   tidak membebani user dengan informasi yang tidak diperlukan.

Fokus utama:

``` text
Customer
Memesan → Membayar → Memantau → Menerima

Owner
Menerima → Memverifikasi → Memproses → Menugaskan → Memantau

Courier
Menerima → Menuju Lokasi → Menerima Pembayaran Cash bila ada
→ Mengirim → Selesai
```

### 3.2 Role-Based Experience

Jangan membuat tiga aplikasi Android terpisah.

``` text
Single Android Application
          ↓
Authenticated User
          ↓
Role
          ↓
Role-specific Navigation
          ↓
Role-specific Screens
```

Role:

``` text
CUSTOMER
OWNER
COURIER
```

Authorization tetap dilakukan backend. UI hanya menyesuaikan tampilan
berdasarkan role.

### 3.3 Backend as Business Authority

Android boleh melakukan validasi untuk UX, tetapi tidak boleh menetapkan
business state.

Contoh:

``` text
Android:
"File bukti harus dipilih"

Backend:
"Apakah customer memiliki order ini?"
"Apakah payment masih PENDING?"
"Apakah proof boleh diupload?"
"Apakah Owner boleh approve?"
"Apakah Courier benar-benar assigned?"
"Apakah CASH boleh dikonfirmasi?"
```

------------------------------------------------------------------------

## 4. Information Architecture

``` text
KYŪSUI
│
├── Authentication
│   ├── Splash
│   ├── Login
│   └── Register
│
├── Customer
│   ├── Home
│   ├── Create Order
│   ├── Location Picker
│   ├── Order Confirmation
│   ├── Payment Method
│   ├── QRIS Payment
│   ├── Cash Payment
│   ├── Orders
│   ├── Order Detail
│   ├── Tracking
│   ├── History
│   └── Profile
│
├── Owner
│   ├── Dashboard
│   ├── Orders
│   ├── Order Detail
│   ├── QRIS Verification
│   ├── Payment Detail
│   ├── QRIS Settings
│   ├── Courier Assignment
│   ├── Courier
│   └── Profile
│
└── Courier
    ├── Dashboard
    ├── Assigned Orders
    ├── Order Detail
    ├── Delivery Navigation
    ├── Active Delivery
    ├── Cash Confirmation
    ├── Delivery Completion
    ├── History
    └── Profile
```

Payment screen dipisahkan berdasarkan workflow internal KYŪSUI, bukan
provider.

------------------------------------------------------------------------

## 5. Navigation Architecture

### 5.1 Authentication Navigation

``` text
Splash
  │
  ├── authenticated → Role Home
  │
  └── unauthenticated
          ↓
        Login
          │
          ├── Login Success → Role Home
          │
          └── Register
```

### 5.2 Customer Navigation

``` text
┌─────────────────────────────────────┐
│             Screen Content          │
├─────────────────────────────────────┤
│ Home    Orders    Tracking    Profile│
└─────────────────────────────────────┘
```

Primary destinations:

1.  Home
2.  Orders
3.  Tracking
4.  Profile

History berada di dalam Orders sebagai bagian dari order lifecycle.

### 5.3 Owner Navigation

``` text
┌─────────────────────────────────────┐
│             Screen Content          │
├─────────────────────────────────────┤
│ Dashboard  Orders  Courier  Profile │
└─────────────────────────────────────┘
```

Payment tidak menjadi bottom navigation utama.

Payment Verification dan QRIS Settings diakses dari area Owner
Orders/Dashboard/Profile sesuai implementasi navigation.

### 5.4 Courier Navigation

``` text
┌─────────────────────────────────────┐
│             Screen Content          │
├─────────────────────────────────────┤
│ Home    Deliveries    History Profile│
└─────────────────────────────────────┘
```

Cash confirmation muncul hanya pada delivery yang relevan.

------------------------------------------------------------------------

## 6. Navigation Rules

Gunakan Navigation Compose.

Route baseline:

``` kotlin
object Routes {
    const val Splash = "splash"
    const val Login = "login"
    const val Register = "register"

    const val CustomerHome = "customer/home"
    const val CustomerOrders = "customer/orders"
    const val CustomerTracking = "customer/tracking"
    const val CustomerProfile = "customer/profile"
    const val CustomerCreateOrder = "customer/order/create"
    const val CustomerLocation = "customer/order/location"
    const val CustomerConfirmation = "customer/order/confirmation"
    const val CustomerPayment = "customer/order/payment"
    const val CustomerOrderDetail = "customer/orders/{orderId}"
    const val CustomerHistory = "customer/history"

    const val OwnerDashboard = "owner/dashboard"
    const val OwnerOrders = "owner/orders"
    const val OwnerOrderDetail = "owner/orders/{orderId}"
    const val OwnerPaymentVerification = "owner/payment-verification"
    const val OwnerPaymentDetail = "owner/orders/{orderId}/payment"
    const val OwnerQrisSettings = "owner/qris-settings"
    const val OwnerCourier = "owner/courier"
    const val OwnerProfile = "owner/profile"

    const val CourierHome = "courier/home"
    const val CourierDeliveries = "courier/deliveries"
    const val CourierOrderDetail = "courier/orders/{orderId}"
    const val CourierNavigation = "courier/orders/{orderId}/navigation"
    const val CourierActiveDelivery = "courier/orders/{orderId}/active"
    const val CourierCashConfirmation = "courier/orders/{orderId}/cash"
    const val CourierHistory = "courier/history"
    const val CourierProfile = "courier/profile"
}
```

Path final harus tetap mengikuti API specification dan Android
architecture yang aktif.

------------------------------------------------------------------------

# 7. UI Design System

## 7.1 Design Language

Visual identity:

``` text
Water
Clean
Fresh
Reliable
Modern
Simple
Functional
```

Visual tidak boleh terlihat seperti dashboard admin web yang dipindahkan
ke Android.

Gunakan mobile-first layout dengan hierarchy yang jelas.

## 7.2 Color System

### Primary

``` text
Primary           #0EA5E9
Primary Dark      #0284C7
Primary Container #E0F2FE
```

Digunakan untuk CTA utama, active navigation, selected state, progress,
dan map marker.

### Secondary

``` text
Secondary           #06B6D4
Secondary Container #CFFAFE
```

### Background

``` text
Background        #F8FAFC
Surface           #FFFFFF
Surface Variant   #F1F5F9
```

### Text

``` text
Text Primary      #0F172A
Text Secondary    #475569
Text Disabled     #94A3B8
```

### Semantic Colors

``` text
Success            #16A34A
Success Container  #DCFCE7

Warning            #D97706
Warning Container  #FEF3C7

Error              #DC2626
Error Container    #FEE2E2

Info               #2563EB
Info Container     #DBEAFE
```

Semantic color tidak boleh menjadi satu-satunya indikator.

## 7.3 Dark Mode

Dark mode dapat menggunakan Material 3 theme system.

Semantic meaning harus tetap konsisten:

``` text
Success = Success
Error = Error
Pending = Warning
Primary = Primary
```

## 7.4 Typography

``` text
Display Large    32sp
Headline Large   28sp
Headline Medium  24sp
Title Large      22sp
Title Medium     16sp
Title Small      14sp

Body Large       16sp
Body Medium      14sp
Body Small       12sp

Label Large      14sp
Label Medium     12sp
Label Small      11sp
```

Weight:

``` text
Headline       Bold
Screen title   SemiBold
Card title     SemiBold
Body           Regular
Supporting     Regular
Button         SemiBold
```

## 7.5 Spacing

Gunakan base 4dp:

``` text
4dp   XXS
8dp   XS
12dp  SM
16dp  MD
20dp  LG
24dp  XL
32dp  XXL
40dp  XXXL
48dp  Section
```

Default horizontal screen padding:

``` text
16dp
```

## 7.6 Shape

``` text
Small      8dp
Medium     12dp
Large      16dp
ExtraLarge 24dp
```

Default:

``` text
Input       12dp
Card        16dp
Button      12dp
Dialog      24dp
BottomSheet 24dp
```

## 7.7 Elevation

``` text
Level 0 → normal surface
Level 1 → card
Level 2 → floating element
Level 3 → dialog/modal
```

Gunakan elevation secara minimal.

------------------------------------------------------------------------

# 8. Core Components

## 8.1 Top App Bar

Format:

``` text
←  Detail Pesanan
```

Digunakan untuk screen title, back navigation, dan contextual action.

## 8.2 Bottom Navigation

Maksimal empat destination utama.

Setiap item:

``` text
Icon
Label
Selected State
```

## 8.3 Buttons

Primary:

``` text
[ Pesan Sekarang ]
[ Lanjutkan ]
[ Upload Bukti ]
[ Setujui ]
[ Uang Diterima ]
[ Mulai Pengantaran ]
```

Secondary:

``` text
[ Lihat Detail ]
[ Ubah Lokasi ]
[ Lihat Bukti ]
```

Destructive:

``` text
[ Tolak Bukti ]
```

Action kritis menggunakan confirmation bila diperlukan.

## 8.4 Forms

Semua form:

``` text
Label
Input
Supporting Text
Validation
Error Message
```

## 8.5 Cards

Card digunakan untuk:

-   order;
-   payment;
-   QRIS;
-   courier;
-   summary;
-   status;
-   information.

## 8.6 Dialog

Gunakan untuk keputusan yang membutuhkan perhatian.

## 8.7 Bottom Sheet

Gunakan untuk:

-   payment method;
-   courier selection;
-   filter;
-   detail singkat;
-   map information.

------------------------------------------------------------------------

# 9. Loading, Empty, Error, Success

## 9.1 Loading

Tiga tingkat:

``` text
Screen Loading
Content Loading
Button Loading
```

Button disabled ketika request berlangsung.

## 9.2 Empty State

``` text
Belum Ada Pesanan

Anda belum membuat pesanan.

[ Pesan Air Galon ]
```

## 9.3 Error State

``` text
Gagal Memuat Pesanan

Terjadi masalah saat mengambil data pesanan.

[ Coba Lagi ]
```

## 9.4 Network Error

``` text
Tidak Ada Koneksi

Periksa koneksi internet Anda lalu coba kembali.

[ Coba Lagi ]
```

## 9.5 Success

``` text
✓

Berhasil

Data berhasil diperbarui.

[ Lihat Detail ]
```

Untuk payment dan action kritis, gunakan state yang persistent/terlihat
jelas, bukan Snackbar saja.

------------------------------------------------------------------------

# 10. Order Status Design

Canonical order status:

``` text
MENUNGGU_PEMBAYARAN
        ↓
MENUNGGU_DIPROSES
        ↓
DIPROSES
        ↓
DITUGASKAN
        ↓
DALAM_PENGANTARAN
        ↓
SELESAI
```

Display labels:

  Backend State           UI Label              Semantic
  ----------------------- --------------------- ----------
  `MENUNGGU_PEMBAYARAN`   Menunggu Pembayaran   Warning
  `MENUNGGU_DIPROSES`     Menunggu Diproses     Warning
  `DIPROSES`              Diproses              Info
  `DITUGASKAN`            Ditugaskan            Info
  `DALAM_PENGANTARAN`     Dalam Pengantaran     Primary
  `SELESAI`               Selesai               Success

Jangan membuat status order baru hanya untuk kebutuhan visual.

------------------------------------------------------------------------

# 11. Order Progress Component

``` text
✓ Pesanan Dibuat
│
✓ Pembayaran
│
✓ Diproses
│
✓ Kurir Ditugaskan
│
● Dalam Pengantaran
│
○ Selesai
```

Gunakan:

``` text
Icon + Text + Color
```

bukan warna saja.

------------------------------------------------------------------------

# 12. Tracking UI

Tracking merupakan core feature.

Customer:

``` text
┌───────────────────────────────────┐
│ ←  Lacak Pesanan                  │
├───────────────────────────────────┤
│                                   │
│          GOOGLE MAP               │
│                                   │
│              🚚                   │
│                     📍            │
│                                   │
├───────────────────────────────────┤
│ Dalam Pengantaran                 │
│ Kurir sedang menuju lokasi Anda   │
│                                   │
│ Lokasi terakhir diperbarui        │
│ beberapa saat lalu                │
│                                   │
│ [ Lihat Detail Pesanan ]          │
└───────────────────────────────────┘
```

Tracking hanya tersedia dalam delivery context yang valid.

Tracking bottom sheet:

``` text
Kurir Anda

Nama Kurir
Status: Dalam Pengantaran

Lokasi terakhir diperbarui
beberapa saat lalu
```

Jika lokasi belum tersedia:

``` text
Posisi Kurir Belum Tersedia

Kurir belum mengirimkan posisi terbaru.

[ Coba Lagi ]
```

Jangan menyebut data sebagai realtime jika data sebenarnya stale.

------------------------------------------------------------------------

# 13. AUTHENTICATION UI

## 13.1 Splash

### Tujuan

Memeriksa session dan menentukan tujuan awal.

### Elemen

``` text
KYŪSUI Logo
App Name
Loading Indicator
```

### State

``` text
CheckingSession
Authenticated
Unauthenticated
SessionExpired
Error
```

## 13.2 Login

### Elemen

``` text
Logo
Welcome Text
Identifier Field
Password Field
Show Password
Login Button
Register Link
Error Message
```

Identifier harus mengikuti keputusan authentication yang berlaku.

### State

``` text
Idle
Loading
Success
InvalidCredential
ValidationError
NetworkError
ServerError
```

## 13.3 Register

### Elemen

``` text
Nama
Email/identifier sesuai policy
Nomor Telepon sesuai policy
Password
Konfirmasi Password
Register
```

### State

``` text
Idle
Loading
ValidationError
Success
Error
```

------------------------------------------------------------------------

# 14. CUSTOMER UI/UX

## 14.1 Customer Screen Map

``` text
Splash
  ↓
Login
  ↓
Customer Home
  ├── Create Order
  │      ├── Location
  │      ├── Confirmation
  │      └── Payment
  │             ├── QRIS
  │             └── CASH
  │
  ├── Orders
  │      └── Order Detail
  │             └── Tracking
  │
  ├── History
  └── Profile
```

## 14.2 Customer Home

### Tujuan

Menjadi pusat aktivitas customer.

### Elemen

``` text
Top App Bar
Greeting
Current Order Card
Order CTA
Quick Action
Recent Orders
```

Contoh:

``` text
Halo, Customer

Butuh air galon?

┌──────────────────────────────┐
│ Pesanan Aktif                │
│ #ORD-001                     │
│ 2 Galon                      │
│ Dalam Pengantaran            │
│                              │
│ [ Lacak Pesanan ]            │
└──────────────────────────────┘

[ + Pesan Air Galon ]

Pesanan Terbaru
...
```

### State

``` text
Loading
Loaded
Empty
Error
```

## 14.3 Customer Create Order

``` text
Jumlah Galon

[-] 2 [+]

Lokasi Pengantaran
[ Pilih Lokasi ]

[ Lanjutkan ]
```

Jangan menambahkan promo, diskon, minimum order, atau biaya bisnis lain
yang belum ditetapkan.

State:

``` text
Idle
Editing
Validating
Submitting
Success
ValidationError
Error
```

## 14.4 Customer Location Picker

Elemen:

``` text
Google Map
Current Location Marker
Delivery Marker
Confirm Location Button
```

State:

``` text
LoadingMap
LocationAvailable
PermissionRequired
PermissionDenied
LocationUnavailable
Confirmed
Error
```

## 14.5 Customer Order Confirmation

Elemen:

``` text
Order Summary
Quantity
Delivery Location
Authoritative Total
Payment Method
Confirm Button
```

Nominal final berasal dari backend.

------------------------------------------------------------------------

# 15. CUSTOMER PAYMENT UI --- FINAL

Payment UI lama yang menggunakan Midtrans, payment gateway, dynamic
QRIS, provider transaction, provider callback, `PROCESSING`, `FAILED`,
`EXPIRED`, atau status provider lain **dihapus dari active UI
architecture**.

Payment method hanya:

``` text
QRIS
CASH
```

Payment status hanya:

``` text
PENDING
WAITING_VERIFICATION
PAID
```

------------------------------------------------------------------------

## 15.1 Customer Payment Method Selection

### Tujuan

Memilih metode pembayaran untuk order.

### UI

``` text
Pilih Pembayaran

┌─────────────────────────────────┐
│ ◉ QRIS                          │
│    Bayar menggunakan QRIS       │
│    dan upload bukti pembayaran  │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ ○ CASH                          │
│    Bayar tunai kepada Courier   │
└─────────────────────────────────┘

Total
Rp xx.xxx

[ Lanjutkan ]
```

Tidak ada:

``` text
Midtrans
Digital Payment
Payment Gateway
Dynamic QRIS
Provider
```

------------------------------------------------------------------------

## 15.2 Customer QRIS Payment Screen

### Tujuan

Memberikan semua informasi yang diperlukan customer untuk membayar
melalui QRIS static Berkah Water.

### Elemen UI

``` text
← Pembayaran QRIS

Ringkasan Pesanan
#ORD-001
2 Galon

Total Pembayaran
Rp xx.xxx

QRIS Berkah Water
┌───────────────────────────────┐
│                               │
│          QRIS IMAGE           │
│                               │
└───────────────────────────────┘

QRIS aktif
Berkah Water

Cara Pembayaran
1. Scan QRIS di atas.
2. Selesaikan pembayaran.
3. Simpan bukti transaksi.
4. Upload bukti di aplikasi.

Status Pembayaran
PENDING

[ Upload Bukti Pembayaran ]
```

### Data UI

``` text
orderId
totalAmount
activeQrisImage
paymentMethod = QRIS
paymentStatus
proofImage
```

### State

``` text
Loading
Loaded
UploadReady
Uploading
WaitingVerification
Paid
Rejected
Error
```

------------------------------------------------------------------------

## 15.3 QRIS Payment Summary

Summary harus menampilkan:

``` text
Pesanan
Jumlah
Lokasi Pengantaran
Total
Metode: QRIS
Status
```

Total harus berasal dari server.

Android tidak menghitung ulang nominal authoritative.

------------------------------------------------------------------------

## 15.4 Active QRIS

UI menampilkan QRIS yang saat ini aktif.

``` text
QRIS Aktif

┌───────────────────────────────┐
│                               │
│          QRIS IMAGE           │
│                               │
└───────────────────────────────┘

Berkah Water
```

Customer tidak dapat:

``` text
mengubah QRIS
mengupload QRIS baru
memilih provider
membuat dynamic QRIS
```

Customer hanya dapat melihat active QRIS.

------------------------------------------------------------------------

## 15.5 QRIS Instruction

Copywriting:

``` text
Cara Pembayaran

1. Scan QRIS Berkah Water.
2. Bayar sesuai total yang tertera.
3. Simpan bukti transaksi.
4. Upload bukti pembayaran di aplikasi.
5. Tunggu verifikasi Owner.
```

Jangan menyatakan:

``` text
Pembayaran otomatis diverifikasi.
```

------------------------------------------------------------------------

## 15.6 QRIS Upload Proof

### UI

``` text
Bukti Pembayaran

┌───────────────────────────────┐
│                               │
│       Preview / Empty         │
│                               │
└───────────────────────────────┘

[ Pilih Foto ]
[ Ambil Foto ]

[ Upload Bukti ]
```

Setelah file dipilih:

``` text
Bukti Pembayaran

┌───────────────────────────────┐
│       PROOF IMAGE             │
└───────────────────────────────┘

Nama file
proof.jpg

[ Ganti Foto ]

[ Upload Bukti ]
```

### UX Validation

Validasi client dapat memeriksa:

``` text
File tersedia
Format gambar
Ukuran file
```

Backend tetap menjadi authority.

### Upload State

``` text
Idle
Selecting
Previewing
Uploading
UploadSuccess
ValidationError
NetworkError
ServerError
```

------------------------------------------------------------------------

## 15.7 QRIS Status: PENDING

UI:

``` text
Pembayaran QRIS

Status
PENDING

Belum ada bukti pembayaran yang sedang
menunggu verifikasi.

[ Upload Bukti Pembayaran ]
```

Jika proof sebelumnya ditolak:

``` text
Pembayaran QRIS

Status
PENDING

Bukti pembayaran sebelumnya ditolak.
Silakan upload bukti pembayaran yang benar.

[ Upload Ulang Bukti ]
```

------------------------------------------------------------------------

## 15.8 QRIS Status: WAITING_VERIFICATION

UI:

``` text
Pembayaran QRIS

Status
MENUNGGU VERIFIKASI

Bukti pembayaran telah dikirim.

Owner akan memeriksa bukti pembayaran Anda.

Bukti:
┌───────────────────────────────┐
│       PROOF PREVIEW           │
└───────────────────────────────┘

[ Lihat Bukti ]
```

Tidak menampilkan tombol:

``` text
Bayar lagi
Upload ulang
```

selama bukti masih menunggu verifikasi, kecuali backend secara eksplisit
mengizinkan action tersebut.

------------------------------------------------------------------------

## 15.9 QRIS Status: PAID

UI:

``` text
✓ Pembayaran Berhasil

QRIS
PAID

Total
Rp xx.xxx

Bukti pembayaran telah diverifikasi
oleh Owner.

[ Lihat Pesanan ]
```

Customer tidak dapat mengubah status menjadi PAID.

------------------------------------------------------------------------

## 15.10 QRIS Rejected Flow

`REJECTED` bukan canonical payment status.

Rejection adalah event/action yang mengembalikan payment ke:

``` text
WAITING_VERIFICATION
       ↓
Owner Reject
       ↓
PENDING
       ↓
Customer Upload Again
       ↓
WAITING_VERIFICATION
       ↓
Owner Verify
       ↓
PAID
```

UI setelah rejection:

``` text
Bukti Pembayaran Ditolak

Bukti pembayaran yang Anda kirim
belum dapat diverifikasi.

Status Pembayaran
PENDING

Silakan lakukan pembayaran sesuai
total order dan upload bukti baru.

[ Upload Ulang Bukti ]
```

Jika alasan rejection tersedia dari backend:

``` text
Alasan Penolakan

<reason dari backend>
```

UI tidak boleh membuat alasan sendiri.

------------------------------------------------------------------------

# 16. CUSTOMER CASH PAYMENT UI --- FINAL

## 16.1 Cash Payment Screen

### Tujuan

Memberi tahu customer bahwa pembayaran CASH dilakukan kepada Courier
saat delivery.

### UI

``` text
← Pembayaran Cash

Ringkasan Pesanan

#ORD-001
2 Galon

Total Pembayaran
Rp xx.xxx

Metode Pembayaran
CASH

Status Pembayaran
PENDING

Pembayaran dilakukan kepada Courier
saat pesanan diantar.

Siapkan uang sesuai total pembayaran.

[ Lanjutkan ]
```

Tidak ada:

``` text
Owner Cash Confirmation
Cash Upload Proof
Payment Gateway
Provider Screen
```

------------------------------------------------------------------------

## 16.2 Cash PENDING

``` text
Pembayaran Cash

Status
PENDING

Pembayaran akan dilakukan kepada
Courier saat pesanan diantar.

Belum ada konfirmasi pembayaran.

Total
Rp xx.xxx
```

Customer tidak dapat mengubah PENDING menjadi PAID.

------------------------------------------------------------------------

## 16.3 Cash During Delivery

Pada Order Detail ketika courier telah ditugaskan:

``` text
Pembayaran

CASH

Status: PENDING

Total
Rp xx.xxx

Bayarkan kepada Courier saat
pesanan diterima.
```

Jika backend memberikan identity courier:

``` text
Courier
Nama Courier

Pembayaran Cash
Rp xx.xxx
```

Jangan menampilkan action konfirmasi pembayaran kepada customer.

------------------------------------------------------------------------

## 16.4 Cash PAID

Setelah Courier memilih `Uang Diterima` dan backend berhasil
memvalidasi:

``` text
✓ Pembayaran Cash Diterima

CASH
PAID

Total
Rp xx.xxx

Pembayaran telah dikonfirmasi
oleh Courier.
```

Status order tetap mengikuti order lifecycle backend.

------------------------------------------------------------------------

# 17. CUSTOMER ORDERS

### Tujuan

Melihat order aktif dan history.

``` text
Aktif | Riwayat

┌──────────────────────────────┐
│ #ORD-001                     │
│ 2 Galon                      │
│ QRIS                         │
│ PAID                         │
│ Dalam Pengantaran            │
│                              │
│ [ Lihat Detail ]             │
└──────────────────────────────┘
```

Untuk CASH:

``` text
CASH
PENDING
```

atau:

``` text
CASH
PAID
```

sesuai data server.

State:

``` text
Loading
Loaded
Empty
Error
Refreshing
```

------------------------------------------------------------------------

# 18. CUSTOMER ORDER DETAIL

Elemen:

``` text
Order ID
Customer
Quantity
Delivery Location
Total
Payment Method
Payment Status
Order Status
Order Timeline
Courier Information jika assigned
Tracking CTA jika delivery active
```

Payment section:

``` text
Pembayaran

Metode:
QRIS

Status:
WAITING_VERIFICATION

[ Lihat Pembayaran ]
```

atau:

``` text
Pembayaran

Metode:
CASH

Status:
PENDING
```

Tracking CTA hanya muncul jika delivery aktif.

------------------------------------------------------------------------

# 19. CUSTOMER TRACKING

Data:

``` text
orderId
courier
courierLocation
deliveryLocation
orderStatus
lastUpdated
```

State:

``` text
Loading
TrackingActive
LocationStale
LocationUnavailable
OrderCompleted
Error
```

Tracking retrieval strategy tidak ditentukan oleh UI/UX. Ikuti tracking
specification.

------------------------------------------------------------------------

# 20. CUSTOMER HISTORY

Elemen:

``` text
Order History Cards
Date
Quantity
Status
Total
Payment Method
Payment Status
```

State:

``` text
Loading
Loaded
Empty
Error
```

------------------------------------------------------------------------

# 21. CUSTOMER PROFILE

Elemen:

``` text
Profile
Name
Email
Role
Logout
```

State:

``` text
Loading
Loaded
Error
LogoutLoading
LogoutSuccess
```

------------------------------------------------------------------------

# 22. OWNER UI/UX

Owner berorientasi pada operational workflow.

Prioritas:

``` text
Incoming Order
      ↓
Payment Verification
      ↓
Process
      ↓
Assign Courier
      ↓
Monitor
```

------------------------------------------------------------------------

## 22.1 Owner Dashboard

Elemen:

``` text
Today's Orders
Pending Orders
Processing Orders
Active Deliveries

Pending QRIS Verification
Recent Orders
```

Contoh:

``` text
Dashboard

Pesanan Masuk
12

Menunggu Diproses
5

QRIS Menunggu Verifikasi
3

Dalam Pengantaran
3
```

Angka berasal dari API.

State:

``` text
Loading
Loaded
Empty
Error
```

------------------------------------------------------------------------

# 23. OWNER ORDERS

Elemen:

``` text
Filter Status
Order List
Order Card
Payment Method
Payment Status
Delivery Status
```

Contoh:

``` text
#ORD-001
Customer A
2 Galon

QRIS
WAITING_VERIFICATION

[ Lihat Detail ]
```

atau:

``` text
#ORD-002
Customer B
2 Galon

CASH
PENDING

[ Lihat Detail ]
```

State:

``` text
Loading
Loaded
Empty
Error
Refreshing
```

------------------------------------------------------------------------

# 24. OWNER ORDER DETAIL

Elemen:

``` text
Order ID
Customer
Quantity
Delivery Location
Total
Payment Method
Payment Status
Order Status
Courier
Action Area
```

Action ditentukan current state.

Contoh:

``` text
Menunggu Diproses

[ Proses Pesanan ]
```

atau:

``` text
Siap Dikirim

[ Tugaskan Kurir ]
```

Payment action QRIS hanya muncul jika payment memang membutuhkan
verification.

------------------------------------------------------------------------

# 25. OWNER QRIS PAYMENT VERIFICATION

## 25.1 Verification List

### Tujuan

Menampilkan payment QRIS dengan status:

``` text
WAITING_VERIFICATION
```

UI:

``` text
Verifikasi Pembayaran QRIS

┌───────────────────────────────┐
│ #ORD-001                      │
│ Customer A                    │
│ Total Rp xx.xxx               │
│                               │
│ WAITING_VERIFICATION          │
│                               │
│ [ Lihat Bukti ]               │
└───────────────────────────────┘
```

Tidak menampilkan Cash sebagai QRIS verification queue.

State:

``` text
Loading
Loaded
Empty
Refreshing
Error
```

------------------------------------------------------------------------

# 26. OWNER PAYMENT DETAIL

### Tujuan

Memeriksa payment detail sebelum melakukan verification.

UI:

``` text
← Detail Pembayaran

Order
#ORD-001

Customer
Customer A

Metode
QRIS

Total
Rp xx.xxx

Status
WAITING_VERIFICATION

Bukti Pembayaran

┌───────────────────────────────┐
│                               │
│       PROOF PREVIEW           │
│                               │
└───────────────────────────────┘

[ Lihat Penuh ]

[ Setujui ]
[ Tolak ]
```

Data:

``` text
order
customer
paymentMethod
paymentStatus
amount
proofImage
```

Owner tidak boleh approve tanpa workflow backend yang sah.

------------------------------------------------------------------------

# 27. OWNER QRIS APPROVE

Sebelum action:

``` text
Setujui Pembayaran?

Bukti pembayaran untuk #ORD-001
akan ditandai sebagai pembayaran
berhasil.

[ Batal ] [ Setujui ]
```

Loading:

``` text
[ Menyetujui... ]
```

Success:

``` text
✓ Pembayaran Disetujui

Status pembayaran:
PAID
```

UI mengikuti response backend.

------------------------------------------------------------------------

# 28. OWNER QRIS REJECT

Sebelum action:

``` text
Tolak Bukti Pembayaran?

Bukti akan dikembalikan ke status
PENDING dan customer dapat mengirim
bukti baru.

[ Batal ] [ Tolak ]
```

Jika alasan diwajibkan oleh API:

``` text
Alasan Penolakan
┌───────────────────────────────┐
│                               │
└───────────────────────────────┘
```

Setelah berhasil:

``` text
Bukti Ditolak

Status payment:
PENDING

Customer dapat upload bukti baru.
```

`REJECTED` tidak menjadi payment status canonical.

------------------------------------------------------------------------

# 29. OWNER QRIS SETTINGS

## 29.1 Tujuan

Mengelola QRIS static aktif milik Berkah Water.

UI:

``` text
Pengaturan QRIS

QRIS Aktif

┌───────────────────────────────┐
│                               │
│          QRIS IMAGE           │
│                               │
└───────────────────────────────┘

[ Ganti QRIS ]
```

Owner dapat mengganti active QRIS.

Customer hanya dapat melihat QRIS aktif.

------------------------------------------------------------------------

## 29.2 Replace Active QRIS

UI:

``` text
Ganti QRIS

QRIS Baru

┌───────────────────────────────┐
│                               │
│      Preview QRIS Baru        │
│                               │
└───────────────────────────────┘

[ Pilih Gambar ]

[ Simpan QRIS ]
```

Confirmation:

``` text
Ganti QRIS Aktif?

QRIS baru akan digunakan untuk
pembayaran QRIS berikutnya.

[ Batal ] [ Ganti QRIS ]
```

Tidak membuat:

``` text
dynamic QRIS
payment provider
QRIS transaction
provider QRIS callback
```

------------------------------------------------------------------------

# 30. OWNER COURIER ASSIGNMENT

Tujuan:

``` text
Order
 ↓
Available Couriers
 ↓
Select Courier
 ↓
Confirm
```

UI:

``` text
Order #ORD-001

Pilih Courier

┌─────────────────────────────┐
│ Courier A                   │
│ Available                   │
│                 [ Pilih ]   │
└─────────────────────────────┘

[ Tugaskan ]
```

State:

``` text
Loading
Loaded
NoAvailableCourier
Assigning
Success
Conflict
Error
```

------------------------------------------------------------------------

# 31. OWNER COURIER

Elemen:

``` text
Courier List
Name
Availability
Assigned Order Count
```

State:

``` text
Loading
Loaded
Empty
Error
```

------------------------------------------------------------------------

# 32. OWNER PROFILE

Elemen:

``` text
Name
Email
Role
Logout
```

State:

``` text
Loading
Loaded
Error
LogoutLoading
LogoutSuccess
```

------------------------------------------------------------------------

# 33. COURIER UI/UX

Courier interface harus task-oriented.

Prioritas:

``` text
Assigned Delivery
      ↓
Customer Location
      ↓
Navigation
      ↓
Cash Confirmation bila CASH
      ↓
Delivery Status
      ↓
Complete
```

------------------------------------------------------------------------

# 34. COURIER DASHBOARD

Elemen:

``` text
Active Delivery
Assigned Orders
Delivery Status
Start Delivery CTA
Cash Payment Indicator bila CASH
```

Contoh:

``` text
Pengantaran Aktif

#ORD-001
Customer A

2 Galon
Rp xx.xxx

Payment
CASH
PENDING

[ Buka Pengantaran ]
```

State:

``` text
Loading
Active
NoDelivery
Error
```

------------------------------------------------------------------------

# 35. COURIER ASSIGNED ORDERS

Elemen:

``` text
Order Cards
Customer
Quantity
Delivery Location
Order Status
Payment Method
Payment Status
```

State:

``` text
Loading
Loaded
Empty
Error
```

------------------------------------------------------------------------

# 36. COURIER ORDER DETAIL

Elemen:

``` text
Order ID
Customer
Quantity
Delivery Address/Location
Order Status
Payment Method
Payment Status
Start Delivery
```

Jika CASH:

``` text
Pembayaran

CASH
PENDING

Total
Rp xx.xxx

Customer akan membayar kepada Anda
saat delivery.
```

Jika QRIS:

``` text
Pembayaran

QRIS
PAID
```

Courier tidak melakukan QRIS verification.

------------------------------------------------------------------------

# 37. COURIER DELIVERY NAVIGATION

Elemen:

``` text
Google Map
Courier Current Location
Customer Delivery Location
Navigation Action
Order Summary
```

State:

``` text
Loading
LocationReady
PermissionRequired
PermissionDenied
GPSUnavailable
Error
```

------------------------------------------------------------------------

# 38. COURIER ACTIVE DELIVERY

Elemen:

``` text
Delivery Status
Map
Current Location
Customer Location
Delivery Information
Payment Information
Primary Action
```

Location flow:

``` text
Fused Location Provider
          ↓
Location State
          ↓
ViewModel
          ↓
Repository
          ↓
Laravel API
```

State:

``` text
Preparing
Active
SendingLocation
LocationError
NetworkUnavailable
Completed
```

------------------------------------------------------------------------

# 39. COURIER CASH PAYMENT CONFIRMATION

## 39.1 Scope

Screen/action ini hanya muncul jika:

``` text
payment_method = CASH
AND
payment_status = PENDING
AND
assignment belongs to authenticated courier
AND
delivery context is valid
```

## 39.2 UI

``` text
Pembayaran Cash

Order
#ORD-001

Customer
Customer A

Total yang Harus Diterima
Rp xx.xxx

Status
PENDING

Setelah uang diterima dari customer,
tekan tombol berikut.

[ Uang Diterima ]
```

## 39.3 Confirmation Dialog

``` text
Konfirmasi Uang Diterima?

Pastikan Anda telah menerima uang
sebesar:

Rp xx.xxx

[ Batal ] [ Uang Diterima ]
```

## 39.4 Loading

``` text
[ Mengonfirmasi... ]
```

Button disabled untuk mencegah duplicate action.

## 39.5 Success

``` text
✓ Uang Diterima

Pembayaran Cash telah dikonfirmasi.

Status:
PAID
```

## 39.6 Rules

Courier tidak boleh:

``` text
mengonfirmasi order courier lain
mengonfirmasi QRIS
mengubah QRIS
menetapkan PAID secara manual
```

Backend harus memvalidasi assignment dan payment state.

------------------------------------------------------------------------

# 40. COURIER DELIVERY COMPLETION

UI:

``` text
Delivery Summary

Status:
Dalam Pengantaran

[ Selesaikan Pengantaran ]
```

Confirmation:

``` text
Selesaikan Pengantaran?

Pastikan pesanan sudah diterima
oleh pelanggan.

[ Belum ] [ Ya, Selesai ]
```

State:

``` text
Confirming
Submitting
Success
Conflict
Error
```

------------------------------------------------------------------------

# 41. COURIER HISTORY

Elemen:

``` text
Completed Delivery Cards
Date
Order ID
Quantity
Status
Payment Method
Payment Status
```

State:

``` text
Loading
Loaded
Empty
Error
```

------------------------------------------------------------------------

# 42. COURIER PROFILE

Elemen:

``` text
Name
Role
Contact Information jika tersedia
Logout
```

State:

``` text
Loading
Loaded
Error
LogoutLoading
LogoutSuccess
```

------------------------------------------------------------------------

# 43. Shared Screen State Model

Conceptual state:

``` kotlin
sealed interface UiState<out T> {
    data object Loading : UiState<Nothing>

    data class Success<T>(
        val data: T
    ) : UiState<T>

    data object Empty : UiState<Nothing>

    data class Error(
        val message: String
    ) : UiState<Nothing>
}
```

Feature-specific states boleh menggunakan sealed state yang lebih
detail.

Payment harus memiliki state domain yang jelas.

------------------------------------------------------------------------

# 44. UI State Matrix

  State                          UI
  ------------------------------ ----------------------------------
  Initial                        Initial content
  Loading                        Progress/Skeleton
  Success                        Content
  Empty                          Empty State
  Error                          Error + Retry
  Refreshing                     Existing content + progress
  Submitting                     Disabled action + progress
  Unauthorized                   Redirect Login
  Forbidden                      Access denied
  Not Found                      Not found
  Conflict                       Explain current state + recovery
  Payment Pending                Pending UI
  Payment Waiting Verification   Verification waiting UI
  Payment Paid                   Success UI

------------------------------------------------------------------------

# 45. Unauthorized State

``` text
Session Expired

Silakan login kembali.

[ Login ]
```

Flow:

``` text
API 401
 ↓
Clear Authentication State
 ↓
Navigate Login
```

------------------------------------------------------------------------

# 46. Forbidden State

``` text
Akses Tidak Diizinkan

Anda tidak memiliki akses ke data tersebut.

[ Kembali ]
```

------------------------------------------------------------------------

# 47. Network Error

``` text
Koneksi Bermasalah

Tidak dapat terhubung ke server.

Periksa koneksi internet Anda.

[ Coba Lagi ]
```

------------------------------------------------------------------------

# 48. Server Error

``` text
Terjadi Kesalahan

Server sedang mengalami masalah.

Silakan coba kembali.

[ Coba Lagi ]
```

Jangan menampilkan raw exception.

------------------------------------------------------------------------

# 49. API Error Mapping

  HTTP              UX
  ----------------- ------------------------
  400               Request tidak valid
  401               Session expired/login
  403               Access denied
  404               Data tidak ditemukan
  409               Data berubah/konflik
  422               Validation error
  429               Terlalu banyak request
  500               Server error
  Network timeout   Connection error

------------------------------------------------------------------------

# 50. Snackbar

Gunakan Snackbar untuk feedback singkat.

Contoh:

``` text
✓ Pesanan berhasil dibuat
✓ Kurir berhasil ditugaskan
✓ QRIS berhasil diperbarui
✓ Bukti pembayaran berhasil dikirim
```

Untuk payment verification dan cash confirmation, Snackbar bukan
satu-satunya source of feedback. Screen state harus ikut berubah
berdasarkan response backend.

------------------------------------------------------------------------

# 51. Confirmation Pattern

``` text
User Action
    ↓
Confirmation
    ↓
API Request
    ↓
Loading
    ↓
Success/Error
```

Gunakan untuk:

``` text
Setujui Bukti
Tolak Bukti
Ganti QRIS
Uang Diterima
Tugaskan Courier
Selesaikan Pengantaran
Logout
```

------------------------------------------------------------------------

# 52. Pull to Refresh

Dapat digunakan pada:

``` text
Customer Orders
Owner Orders
Owner Verification Queue
Courier Deliveries
```

Tidak wajib:

``` text
Login
Register
Payment Upload
```

Tracking mengikuti strategi tracking specification.

------------------------------------------------------------------------

# 53. Accessibility

Wajib mempertimbangkan:

-   readable text;
-   sufficient contrast;
-   touch target cukup;
-   semantic content description;
-   icon tidak menjadi satu-satunya indikator;
-   state menggunakan icon + text + color;
-   screen reader semantics.

------------------------------------------------------------------------

# 54. Touch Target

Recommended:

``` text
≥ 48dp
```

------------------------------------------------------------------------

# 55. Form Validation UX

Validation dua layer:

``` text
Android
↓
Immediate UX feedback

Laravel
↓
Authoritative validation
```

Untuk proof upload:

``` text
Android:
format/size/basic selection

Backend:
ownership/state/file validation/business rule
```

------------------------------------------------------------------------

# 56. Loading UX Rule

``` text
User taps
↓
Button disabled
↓
Progress
↓
API
↓
Result
```

Mencegah duplicate request.

------------------------------------------------------------------------

# 57. Order Creation Flow

``` text
Customer Home
      ↓
Pesan Air
      ↓
Quantity
      ↓
Delivery Location
      ↓
Order Confirmation
      ↓
Payment Method
      ↓
QRIS / CASH
      ↓
Payment UI
      ↓
Order Detail
```

QRIS:

``` text
Payment PENDING
 ↓
Active QRIS
 ↓
Customer pays
 ↓
Upload Proof
 ↓
WAITING_VERIFICATION
 ↓
Owner Verify
 ↓
PAID
```

CASH:

``` text
Payment PENDING
 ↓
Order processed/delivered
 ↓
Customer pays Courier
 ↓
Courier "Uang Diterima"
 ↓
PAID
```

------------------------------------------------------------------------

# 58. Customer Tracking Flow

``` text
Orders
  ↓
Order Detail
  ↓
Status = Dalam Pengantaran
  ↓
Lacak Pesanan
  ↓
Tracking Screen
  ↓
Google Maps
  ↓
Courier Location
  ↓
Order Completed
```

------------------------------------------------------------------------

# 59. Owner Processing Flow

``` text
Owner Dashboard
      ↓
Orders
      ↓
Order Detail
      ↓
Check Payment
      ↓
Process Order
      ↓
Ready for Delivery
      ↓
Assign Courier
      ↓
Order Assigned
```

Untuk QRIS:

``` text
WAITING_VERIFICATION
 ↓
Payment Detail
 ↓
Approve / Reject
```

Untuk CASH:

``` text
PENDING
 ↓
Order may continue processing
 ↓
Courier delivery
```

Owner tidak mengonfirmasi CASH.

------------------------------------------------------------------------

# 60. Courier Delivery Flow

``` text
Courier Dashboard
      ↓
Assigned Orders
      ↓
Order Detail
      ↓
Start Delivery
      ↓
Location Permission
      ↓
Active Delivery
      ↓
Send Location
      ↓
If CASH:
    Customer pays
      ↓
    Uang Diterima
      ↓
    Payment PAID
      ↓
Arrive at Customer
      ↓
Complete Delivery
```

------------------------------------------------------------------------

# 61. Cross-Role Order Lifecycle

``` text
                    CUSTOMER
                       │
                       │ Create
                       ▼
                 ┌─────────────┐
                 │    ORDER    │
                 └──────┬──────┘
                        │
                        ▼
                     PAYMENT
                        │
             ┌──────────┴──────────┐
             │                     │
            QRIS                  CASH
             │                     │
       Upload Proof          Pay Courier
             │                     │
       Owner Verify        Uang Diterima
             │                     │
             └──────────┬──────────┘
                        │
                        ▼
                       OWNER
                        │
                     Process
                        │
                        ▼
                  Assign Courier
                        │
                        ▼
                    COURIER
                        │
                  Start Delivery
                        │
                  Send Location
                        │
                        ▼
                    CUSTOMER
                        │
                     Tracking
                        │
                        ▼
                    COMPLETE
```

------------------------------------------------------------------------

# 62. Screen/API Traceability

  Role       Screen               Primary Dependency
  ---------- -------------------- ------------------------------
  All        Splash               Session/DataStore
  All        Login                Auth API
  All        Register             Auth API
  All        Profile              User API
  All        Logout               Auth API
  Customer   Home                 Orders/User API
  Customer   Create Order         Order API
  Customer   Location             Fused Location Provider
  Customer   Orders               Orders API
  Customer   Order Detail         Order API
  Customer   Payment Method       Payment API
  Customer   QRIS Payment         Payment + QRIS API
  Customer   Cash Payment         Payment API
  Customer   Upload Proof         Payment Proof API
  Customer   Tracking             Tracking API + Google Maps
  Customer   History              Orders API
  Owner      Dashboard            Owner Dashboard API
  Owner      Orders               Owner Orders API
  Owner      Order Detail         Owner Order API
  Owner      QRIS Verification    Owner Payment API
  Owner      Payment Detail       Owner Payment API
  Owner      QRIS Settings        QRIS Settings API
  Owner      Courier Assignment   Courier + Assignment API
  Owner      Courier              Courier API
  Courier    Dashboard            Courier Dashboard API
  Courier    Deliveries           Courier Orders API
  Courier    Order Detail         Courier Order API
  Courier    Navigation           Google Maps + Fused Location
  Courier    Active Delivery      Delivery + Location API
  Courier    Cash Confirmation    Cash Confirmation API
  Courier    Completion           Delivery Status API
  Courier    History              Courier Orders API

Exact endpoint path harus mengikuti API specification aktif. UI/UX tidak
boleh mengarang endpoint yang tidak ada.

------------------------------------------------------------------------

# 63. Compose Component Architecture

``` text
ui/
├── components/
│   ├── KyusuiButton
│   ├── KyusuiOutlinedButton
│   ├── KyusuiTextField
│   ├── KyusuiCard
│   ├── KyusuiTopBar
│   ├── KyusuiBottomBar
│   ├── OrderStatusBadge
│   ├── PaymentStatusBadge
│   ├── OrderStatusTimeline
│   ├── PaymentSummaryCard
│   ├── QrisImageCard
│   ├── PaymentProofPreview
│   ├── PaymentMethodCard
│   ├── CashPaymentCard
│   ├── LoadingView
│   ├── EmptyState
│   ├── ErrorState
│   ├── OrderCard
│   └── CourierCard
│
├── customer/
│   ├── home/
│   ├── order/
│   ├── payment/
│   ├── tracking/
│   └── profile/
│
├── owner/
│   ├── dashboard/
│   ├── orders/
│   ├── payment/
│   ├── qris/
│   ├── courier/
│   └── profile/
│
├── courier/
│   ├── dashboard/
│   ├── delivery/
│   ├── payment/
│   └── profile/
│
└── theme/
```

Reusable component dibuat berdasarkan pola nyata, bukan abstraction
berlebihan.

------------------------------------------------------------------------

# 64. Compose Screen Responsibility

Composable:

``` text
Render UI
Receive State
Emit User Event
```

Bukan:

``` text
Composable
  ↓
Retrofit
  ↓
Business Logic
  ↓
Database
```

Pola:

``` text
Composable
    ↓ event
ViewModel
    ↓
Repository
    ↓
Retrofit
    ↓
Laravel API
```

------------------------------------------------------------------------

# 65. ViewModel Responsibility

ViewModel menangani:

-   UI state;
-   user event;
-   loading;
-   error;
-   success;
-   orchestration;
-   lifecycle-safe state.

Contoh:

``` text
OrderViewModel
├── loadOrders()
├── loadOrderDetail()
├── createOrder()
├── selectLocation()
└── refreshTracking()

PaymentViewModel
├── loadPayment()
├── selectMethod()
├── loadActiveQris()
├── uploadProof()
└── refreshPayment()

OwnerPaymentViewModel
├── loadVerificationQueue()
├── loadPaymentDetail()
├── approveProof()
├── rejectProof()
└── replaceQris()

CourierPaymentViewModel
├── loadCashPayment()
└── confirmCashReceived()
```

Nama method adalah konseptual; implementasi mengikuti codebase.

------------------------------------------------------------------------

# 66. Repository Responsibility

Contoh:

``` text
OrderRepository
├── getOrders()
├── getOrder()
├── createOrder()
├── updateOrder()
└── getTracking()

PaymentRepository
├── getPayment()
├── createPayment()
├── getActiveQris()
└── uploadProof()

OwnerPaymentRepository
├── getVerificationQueue()
├── getPaymentDetail()
├── approveProof()
├── rejectProof()
└── replaceActiveQris()

CourierPaymentRepository
├── getAssignedCashPayment()
└── confirmCashReceived()
```

Repository tidak melakukan rendering UI.

------------------------------------------------------------------------

# 67. Tracking Component Architecture

``` text
Courier Device
      ↓
Fused Location Provider
      ↓
Courier ViewModel
      ↓
Courier Repository
      ↓
Laravel API
      ↓
Tracking Persistence
      ↓
Customer Repository
      ↓
Customer ViewModel
      ↓
Google Maps UI
```

Google Maps bukan source of truth.

------------------------------------------------------------------------

# 68. Payment Component Architecture --- Final

``` text
CUSTOMER
   ↓
Payment UI
   ↓
ViewModel
   ↓
Repository
   ↓
Laravel API
   ↓
MySQL Payment State
   ↓
Android refresh
```

QRIS:

``` text
Customer
   ↓
Active Static QRIS
   ↓
External banking/e-wallet app
   ↓
Payment Proof
   ↓
KYŪSUI Upload
   ↓
WAITING_VERIFICATION
   ↓
Owner
   ↓
Approve
   ↓
PAID
```

CASH:

``` text
Customer
   ↓
CASH selected
   ↓
PENDING
   ↓
Courier delivery
   ↓
Customer pays Courier
   ↓
Courier "Uang Diterima"
   ↓
Backend validates assignment
   ↓
PAID
```

Tidak ada:

``` text
Midtrans
Payment Gateway
Provider Transaction
Provider Webhook
Dynamic QRIS
PaymentTransaction
```

------------------------------------------------------------------------

# 69. Notification UX

FCM digunakan sebagai transport notification.

Contoh:

``` text
Pembayaran Diperbarui

Pembayaran pesanan #ORD-001
telah diverifikasi.
```

Untuk QRIS:

``` text
Bukti Pembayaran Diverifikasi

Pembayaran #ORD-001 telah disetujui.
```

Jika ditolak:

``` text
Bukti Pembayaran Ditolak

Silakan periksa pembayaran dan
upload bukti baru.
```

Untuk Cash:

``` text
Pembayaran Cash Diterima

Pembayaran #ORD-001 telah dikonfirmasi
oleh Courier.
```

Notification bukan source of truth. Saat dibuka, Android mengambil state
terbaru dari API.

------------------------------------------------------------------------

# 70. Notification Interaction

``` text
Notification
     ↓
Deep Link
     ↓
Relevant Order/Payment
     ↓
API Refresh
     ↓
Authoritative UI State
```

Jangan menetapkan payment state hanya berdasarkan payload notification.

------------------------------------------------------------------------

# 71. Offline / Network Behavior

KYŪSUI bergantung pada REST API untuk authoritative business data.

### Customer

Boleh melihat session lokal yang aman, tetapi tidak boleh menganggap
data payment/order offline sebagai authoritative.

### Owner

Tidak boleh menganggap approve/reject berhasil tanpa confirmation dari
backend.

### Courier

Tidak boleh menganggap cash confirmation berhasil tanpa response
backend.

### Location

Location update yang gagal tidak boleh ditampilkan sebagai successfully
sent.

------------------------------------------------------------------------

# 72. Data Freshness

Business status mengikuti backend.

Contoh:

``` text
Local UI:
PENDING

Backend:
PAID
```

Setelah response terbaru:

``` text
UI → PAID
```

Server state menang.

------------------------------------------------------------------------

# 73. Error Recovery

Setiap critical action:

``` text
Error
 ↓
Explain
 ↓
Retry / Back
```

Payment-specific:

``` text
QRIS Upload Error
 ↓
Retry Upload
```

``` text
QRIS Rejected
 ↓
PENDING
 ↓
Upload New Proof
```

``` text
Cash Confirmation Conflict
 ↓
Refresh Payment
 ↓
Show Current State
```

------------------------------------------------------------------------

# 74. UX Anti-Patterns

Dilarang:

``` text
❌ Giant dashboard
❌ Semua fitur di satu screen
❌ Button tanpa loading state
❌ Error tanpa recovery
❌ Status hanya menggunakan warna
❌ API call langsung dari Composable
❌ Hardcoded business data
❌ Customer melihat order customer lain
❌ Courier melihat order courier lain
❌ Payment status ditentukan Android
❌ Owner mengonfirmasi CASH
❌ Customer mengonfirmasi CASH untuk dirinya sendiri
❌ Courier mengonfirmasi QRIS
❌ Payment gateway screen
❌ Midtrans screen
❌ Dynamic QRIS
❌ Provider transaction screen
❌ Provider webhook UI
❌ PaymentTransaction UI
```

------------------------------------------------------------------------

# 75. Responsive Compose Rules

Gunakan:

``` text
WindowSizeClass
dp constraints
LazyColumn
LazyRow
Box
Column
Row
Scaffold
```

Hindari hardcoded screen width untuk layout utama.

------------------------------------------------------------------------

# 76. Screen Scaffold Standard

``` kotlin
Scaffold(
    topBar = ...,
    bottomBar = ...,
    floatingActionButton = ...
) { padding ->
    Content(
        modifier = Modifier.padding(padding)
    )
}
```

Tidak semua screen harus memiliki seluruh bagian.

------------------------------------------------------------------------

# 77. Customer Home Layout

``` text
Scaffold
│
├── TopAppBar
│
└── LazyColumn
    ├── Greeting
    ├── ActiveOrderCard
    ├── PrimaryOrderCTA
    ├── RecentOrderSection
    └── BottomNavigation
```

------------------------------------------------------------------------

# 78. Owner Dashboard Layout

``` text
Scaffold
│
├── TopAppBar
│
└── LazyColumn
    ├── OperationalSummary
    ├── PendingOrders
    ├── PendingQrisVerification
    ├── ActiveDeliveries
    └── RecentOrders
```

------------------------------------------------------------------------

# 79. Courier Dashboard Layout

``` text
Scaffold
│
├── TopAppBar
│
└── Column
    ├── ActiveDeliveryCard
    ├── PaymentCard
    ├── DeliveryStatus
    ├── MapPreview
    └── PrimaryAction
```

Courier memprioritaskan active task daripada statistik.

------------------------------------------------------------------------

# 80. Order Card Standard

``` text
OrderCard(
    orderId
    quantity
    status
    paymentMethod
    paymentStatus
    date
    onClick
)
```

Card tidak mengandung API logic.

------------------------------------------------------------------------

# 81. Order Status Badge

Mapping:

``` text
MENUNGGU_PEMBAYARAN → Warning
MENUNGGU_DIPROSES    → Warning
DIPROSES             → Info
DITUGASKAN           → Info
DALAM_PENGANTARAN    → Primary
SELESAI              → Success
```

------------------------------------------------------------------------

# 82. Payment Status Badge --- Final

Canonical status:

``` text
PENDING
WAITING_VERIFICATION
PAID
```

UI mapping:

``` text
PENDING               → Warning
WAITING_VERIFICATION  → Info
PAID                  → Success
```

Display labels:

``` text
PENDING
→ Menunggu Pembayaran

WAITING_VERIFICATION
→ Menunggu Verifikasi

PAID
→ Lunas / Berhasil
```

Untuk rejection QRIS, UI dapat menampilkan explanatory event:

``` text
Bukti Ditolak
```

tetapi payment status tetap:

``` text
PENDING
```

------------------------------------------------------------------------

# 83. Tracking Marker

Customer:

``` text
📍 Delivery Location
🚚 Courier
```

Courier:

``` text
● Current Position
📍 Customer Location
```

Marker harus memiliki semantic distinction.

------------------------------------------------------------------------

# 84. Tracking Last Updated

``` text
Lokasi terakhir diperbarui
30 detik lalu
```

Jika stale:

``` text
Lokasi belum diperbarui
```

Threshold stale mengikuti tracking specification.

------------------------------------------------------------------------

# 85. Payment Security UX

Jangan pernah menampilkan:

``` text
API Secret
Database Password
Authentication Token
Server Secret
Provider Secret
```

Tidak ada credential provider karena active architecture tidak
menggunakan payment provider.

------------------------------------------------------------------------

# 86. Sensitive Location UX

Lokasi hanya ditampilkan dalam konteks order yang sah.

``` text
Customer A
   ↓
Order A
   ↓
Courier A
```

Bukan:

``` text
Customer A
   ↓
Courier dari Order B
```

------------------------------------------------------------------------

# 87. Order Ownership UX

Customer:

``` text
My Orders
```

Owner:

``` text
Depot Orders
```

Courier:

``` text
Assigned Orders
```

------------------------------------------------------------------------

# 88. Copywriting Guidelines

Gunakan Bahasa Indonesia sederhana.

### Gunakan

``` text
Pesanan Anda
Dalam Pengantaran
Pembayaran Berhasil
Menunggu Verifikasi
Pilih Lokasi
Upload Bukti Pembayaran
Tugaskan Kurir
Uang Diterima
Selesaikan Pengantaran
Ganti QRIS
```

### Hindari

``` text
Execute
Submit
Process Entity
Transaction Mutation
Fetch Data
```

kecuali konteks teknis internal.

------------------------------------------------------------------------

# 89. Payment Copywriting

## QRIS

``` text
Bayar dengan QRIS
Upload Bukti Pembayaran
Menunggu Verifikasi
Bukti Pembayaran Ditolak
Upload Ulang Bukti
Pembayaran Berhasil
```

## CASH

``` text
Bayar Tunai
Bayar kepada Courier
Menunggu Pembayaran
Uang Diterima
Pembayaran Cash Berhasil
```

Jangan menggunakan:

``` text
Cash Confirmed by Owner
```

karena Owner bukan actor Cash confirmation.

------------------------------------------------------------------------

# 90. Confirmation Copy

### QRIS Approve

``` text
Setujui Pembayaran?

Bukti pembayaran ini akan ditandai
sebagai pembayaran berhasil.
```

### QRIS Reject

``` text
Tolak Bukti Pembayaran?

Customer akan dapat mengirim
bukti pembayaran baru.
```

### Cash

``` text
Konfirmasi Uang Diterima?

Pastikan Anda telah menerima uang
sebesar Rp xx.xxx dari customer.
```

------------------------------------------------------------------------

# 91. Empty State Copy

Customer:

``` text
Belum Ada Pesanan

Anda belum membuat pesanan.

[ Pesan Air Galon ]
```

Owner:

``` text
Belum Ada Pesanan

Belum ada pesanan yang perlu diproses.
```

Owner verification:

``` text
Tidak Ada Pembayaran untuk Diverifikasi

Belum ada bukti QRIS yang menunggu
verifikasi.
```

Courier:

``` text
Belum Ada Pengantaran

Belum ada pesanan yang ditugaskan
kepada Anda.
```

------------------------------------------------------------------------

# 92. Role-Specific UX Priority

  Role       Priority 1         Priority 2          Priority 3
  ---------- ------------------ ------------------- --------------------
  Customer   Order              Payment             Tracking
  Owner      Order Management   QRIS Verification   Courier Assignment
  Courier    Active Delivery    Location            Cash Confirmation

------------------------------------------------------------------------

# 93. UX Flow Priority

### Customer

``` text
Home
 ↓
Order
 ↓
Payment
 ↓
Track
 ↓
Complete
```

### Owner

``` text
Dashboard
 ↓
Order
 ↓
Payment Verification
 ↓
Process
 ↓
Assign
 ↓
Monitor
```

### Courier

``` text
Assignment
 ↓
Navigate
 ↓
Deliver
 ↓
Cash Confirmation bila CASH
 ↓
Update Location
 ↓
Complete
```

------------------------------------------------------------------------

# 94. API Loading Boundary

Jangan membuat global loading screen untuk setiap request.

Gunakan:

``` text
Screen Loading
Content Loading
Button Loading
Map Loading
Payment Loading
Upload Loading
Verification Loading
```

------------------------------------------------------------------------

# 95. Refresh Strategy

Customer:

``` text
Order list → Pull refresh
Payment → Refresh setelah action bila diperlukan
Tracking → mengikuti tracking strategy
```

Owner:

``` text
Orders → Pull refresh
Dashboard → Refresh
QRIS Verification → Pull refresh
Payment Detail → Refresh setelah approve/reject
```

Courier:

``` text
Assignments → Refresh
Active Delivery → Location update
Cash Confirmation → Refresh payment after confirmation
```

------------------------------------------------------------------------

# 96. Compose State Ownership

  State                  Owner
  ---------------------- ----------------------------
  Text input sementara   Screen/state holder
  Form validation        ViewModel
  API loading            ViewModel
  API result             ViewModel
  Auth session           Auth/session layer
  Order data             ViewModel/Repository
  Payment status         Server + ViewModel
  QRIS active image      Server + ViewModel
  Proof preview          UI state
  Courier location       Location layer + ViewModel
  Map camera state       UI state
  Navigation             Navigation layer

------------------------------------------------------------------------

# 97. Source of Truth

``` text
UI
 ↓
ViewModel State
 ↓
Repository
 ↓
Laravel API
 ↓
MySQL
```

Untuk business data:

``` text
Backend/MySQL = authoritative
```

Untuk transient UI:

``` text
Compose State = local UI state
```

------------------------------------------------------------------------

# 98. Security Boundary

UI bukan security boundary.

Contoh:

``` text
User manually navigates to:
owner/payment-verification
```

Android tidak boleh hanya mengandalkan hidden menu.

Backend harus melakukan:

``` text
Authentication
      ↓
Role Check
      ↓
Ownership / Assignment Check
      ↓
Payment State Check
      ↓
Action Authorization
      ↓
Response
```

------------------------------------------------------------------------

# 99. Payment UI Security Boundary

### Customer

Hanya dapat:

``` text
melihat payment miliknya
melihat active QRIS
upload proof untuk order miliknya
melihat status
upload ulang setelah rejection
```

### Owner

Hanya dapat:

``` text
melihat payment QRIS dalam scope Berkah Water
melihat proof
approve
reject
mengganti active QRIS
```

### Courier

Hanya dapat:

``` text
melihat CASH pada assignment miliknya
melihat total
menekan Uang Diterima
```

------------------------------------------------------------------------

# 100. Reference Design Principles

Referensi proyek digunakan sebagai inspirasi pola UX untuk order, depot,
courier, delivery, dan tracking.

Referensi tidak boleh menggantikan requirement KYŪSUI.

Payment architecture terbaru menjadi acuan utama untuk payment UI.

------------------------------------------------------------------------

# 101. Unresolved UI/UX Decisions

## UD-UI-001 --- Tracking Refresh Interval

**Question:**\
Berapa interval refresh posisi courier?

**Decision:**\
Mengikuti keputusan teknis pada tracking specification. Jangan hardcode
sebagai business requirement pada UI/UX.

------------------------------------------------------------------------

## UD-UI-002 --- Tracking Retrieval Strategy

**Question:**\
Polling, adaptive polling, atau strategi lain?

**Decision:**\
Mengikuti tracking/API architecture.

------------------------------------------------------------------------

## UD-UI-003 --- Order Cancellation

**Question:**\
Apakah customer dapat membatalkan order?

**Decision:**\
Belum menjadi UI feature sampai business rule ditetapkan.

------------------------------------------------------------------------

## UD-UI-004 --- Notification Detail

**Question:**\
Event tambahan apa yang perlu menghasilkan push notification?

**Decision:**\
Mengikuti notification specification.

------------------------------------------------------------------------

## UD-UI-005 --- Authentication Identifier

**Question:**\
Identifier final login menggunakan email, phone, atau kombinasi?

**Decision:**\
Mengikuti database/API decision yang telah disetujui.

------------------------------------------------------------------------

# 102. Explicitly Removed Legacy Payment UI

Bagian berikut merupakan legacy dan tidak boleh diimplementasikan:

``` text
Midtrans Payment Screen
Payment Gateway Screen
Provider Checkout Screen
Provider Transaction Screen
Dynamic QRIS
Provider Webhook Screen
Provider Callback Screen
Transaction Reference UI
PaymentTransaction UI
PROCESSING UI
FAILED UI
EXPIRED UI
CONFIRMED UI
Owner Cash Confirmation UI
```

Penghapusan ini hanya berlaku pada payment architecture. UI non-payment
tidak dihapus.

------------------------------------------------------------------------

# 103. Final Payment State Matrix

  ---------------------------------------------------------------------------------
  Method      Initial     Intermediate           Success     Rejection
  ----------- ----------- ---------------------- ----------- ----------------------
  QRIS        PENDING     WAITING_VERIFICATION   PAID        WAITING_VERIFICATION →
                                                             PENDING

  CASH        PENDING     PENDING                PAID        Tidak ada proof
                                                             rejection
  ---------------------------------------------------------------------------------

### QRIS

``` text
PENDING
  ↓
Customer Upload Proof
  ↓
WAITING_VERIFICATION
  ↓
Owner Approve
  ↓
PAID
```

Rejection:

``` text
WAITING_VERIFICATION
  ↓
Owner Reject
  ↓
PENDING
  ↓
Upload Again
```

### CASH

``` text
PENDING
  ↓
Courier Delivery
  ↓
Customer Pays Courier
  ↓
Courier "Uang Diterima"
  ↓
Backend Validation
  ↓
PAID
```

------------------------------------------------------------------------

# 104. Final Screen Inventory

## Authentication

  Screen     Role                            Priority
  ---------- ------------------------------- ----------
  Splash     All                             Core
  Login      All                             Core
  Register   Customer / sesuai auth policy   Core

## Customer

  Screen               Priority
  -------------------- ------------
  Home                 Core
  Create Order         Core
  Location Picker      Core
  Order Confirmation   Core
  Payment Method       Core
  QRIS Payment         Core
  Cash Payment         Core
  QRIS Proof Upload    Core
  Orders               Core
  Order Detail         Core
  Tracking             Core
  History              Core
  Profile              Supporting

## Owner

  Screen                    Priority
  ------------------------- ------------
  Dashboard                 Core
  Orders                    Core
  Order Detail              Core
  QRIS Verification Queue   Core
  Payment Detail            Core
  QRIS Settings             Core
  Replace Active QRIS       Core
  Courier Assignment        Core
  Courier List              Core
  Profile                   Supporting

## Courier

  Screen                Priority
  --------------------- ------------
  Dashboard             Core
  Assigned Orders       Core
  Order Detail          Core
  Delivery Navigation   Core
  Active Delivery       Core
  Cash Confirmation     Core
  Delivery Completion   Core
  History               Supporting
  Profile               Supporting

------------------------------------------------------------------------

# 105. Definition of UI/UX Done

Screen dianggap selesai apabila:

``` text
Screen defined
     ↓
Purpose defined
     ↓
Navigation defined
     ↓
UI components defined
     ↓
User actions defined
     ↓
Required data defined
     ↓
API dependency defined
     ↓
Loading state defined
     ↓
Empty state defined
     ↓
Error state defined
     ↓
Success state defined
     ↓
Authorization context defined
     ↓
Compose implementation mapping defined
```

Feature transaksi/state harus memiliki:

``` text
Normal Flow
+
Loading
+
Validation Error
+
Network Error
+
Server Error
+
Unauthorized
+
Forbidden
+
Conflict
+
Empty
+
Success
```

Untuk payment:

``` text
PENDING
WAITING_VERIFICATION
PAID
```

harus memiliki UI state yang jelas.

------------------------------------------------------------------------

# 106. Final Design Rules

1.  KYŪSUI hanya menggunakan Android Native.
2.  UI menggunakan Kotlin + Jetpack Compose.
3.  Material 3 menjadi basis component system.
4.  MVVM + ViewModel + StateFlow digunakan untuk state management.
5.  Navigation menggunakan Navigation Compose.
6.  Network menggunakan Retrofit + OkHttp.
7.  Authentication menggunakan Laravel Sanctum.
8.  Data bisnis berasal dari Laravel API.
9.  Android tidak mengakses MySQL secara langsung.
10. Google Maps SDK digunakan untuk map UI.
11. Fused Location Provider digunakan untuk location acquisition.
12. FCM digunakan untuk notification transport.
13. Customer fokus pada order, payment, tracking, dan history.
14. Owner fokus pada order processing, QRIS verification, QRIS settings,
    dan courier assignment.
15. Courier fokus pada assigned delivery, location, cash confirmation,
    dan completion.
16. Order status konsisten pada seluruh role.
17. Payment status authoritative dari backend.
18. Payment method hanya `QRIS` dan `CASH`.
19. Payment status hanya `PENDING`, `WAITING_VERIFICATION`, dan `PAID`.
20. QRIS menggunakan static QRIS Berkah Water.
21. Customer dapat melihat active QRIS.
22. Customer dapat upload proof QRIS.
23. Customer dapat upload ulang setelah QRIS proof ditolak.
24. Owner dapat approve/reject QRIS proof.
25. Owner dapat mengganti active QRIS.
26. Courier dapat mengonfirmasi CASH hanya untuk assignment miliknya.
27. Owner tidak melakukan Cash confirmation.
28. Customer tidak dapat mengonfirmasi pembayaran Cash untuk dirinya
    sendiri.
29. Tidak ada payment gateway.
30. Tidak ada Midtrans.
31. Tidak ada dynamic QRIS.
32. Tidak ada provider transaction screen.
33. Tidak ada provider webhook UI.
34. Tidak ada `payment_transactions` UI.
35. Rejection QRIS bukan payment status.
36. Rejection QRIS mengembalikan payment ke `PENDING`.
37. Payment PAID hanya berasal dari workflow backend yang sah.
38. Tracking hanya ditampilkan dalam delivery context yang valid.
39. UI bukan security boundary.
40. API call tidak dilakukan langsung dari Composable.
41. Business logic tidak ditempatkan di Composable.
42. Sensitive credential tidak boleh berada di Android.
43. Semua critical action memiliki loading dan recovery state.
44. Semua relevant remote screen memiliki loading, empty, success, dan
    error handling.
45. Jangan menambahkan business feature yang belum ditetapkan.
46. UI copy menggunakan Bahasa Indonesia sederhana untuk user-facing
    text.
47. Status backend dapat menggunakan canonical enum, tetapi display
    label menggunakan bahasa yang mudah dipahami.
48. Component reusable dibuat berdasarkan kebutuhan nyata.
49. Tidak menggunakan React, Vite, Flutter, atau framework UI lain untuk
    KYŪSUI.
50. Implementasi harus tetap mengikuti master specification, project
    rules, Android architecture, workflow, API contract, dan payment
    specification terbaru.

------------------------------------------------------------------------

# 107. Final UI/UX Architecture

``` text
                         KYŪSUI
                            │
                  Android Kotlin App
                            │
                    Jetpack Compose
                            │
                    Navigation Compose
                            │
            ┌───────────────┼───────────────┐
            │               │               │
        CUSTOMER          OWNER          COURIER
            │               │               │
       ┌────┼────┐     ┌────┼─────┐    ┌───┼────┐
       │    │    │     │    │      │    │   │    │
      Home Order Track Dash Order Payment Home Delivery
       │    │    │      │    │      │    │   │
       │ Payment       QRIS  Order  QRIS  │ Cash
       │ QRIS/Cash     Verify Detail      │ Confirm
       │ Proof         Settings           │
       └────┴────┘     └────┴─────┘    └───┴────┘
            │               │               │
        ViewModel        ViewModel        ViewModel
            │               │               │
        StateFlow        StateFlow        StateFlow
            │               │               │
        Repository       Repository       Repository
            │               │               │
         Retrofit         Retrofit         Retrofit
            │               │               │
            └───────────────┼───────────────┘
                            │
                    Laravel REST API
                            │
                          MySQL
```

Payment boundary:

``` text
                 KYŪSUI PAYMENT
                       │
              ┌────────┴────────┐
              │                 │
             QRIS              CASH
              │                 │
       Static QRIS        Cash on Delivery
              │                 │
       Upload Proof       Courier Receives
              │                 │
       Owner Verify       "Uang Diterima"
              │                 │
              └────────┬────────┘
                       │
                      PAID
```

------------------------------------------------------------------------

# 108. Document Dependency

``` text
00_KYUSUI_MASTER_SPECIFICATION.md
                ↓
01_KYUSUI_PROJECT_RULES.md
                ↓
02_KYUSUI_SYSTEM_ARCHITECTURE.md
                ↓
03_KYUSUI_UI_UX_SPECIFICATION.md
                ↓
04_KYUSUI_SYSTEM_WORKFLOW.md
                ↓
05_KYUSUI_DATABASE_SCHEMA.md
                ↓
06_KYUSUI_API_SPECIFICATION.md
                ↓
07_KYUSUI_ANDROID_ARCHITECTURE.md
                ↓
08_KYUSUI_PAYMENT_SPECIFICATION.md
```

Untuk payment, UI/UX wajib mengikuti keputusan terbaru pada
`08_KYUSUI_PAYMENT_SPECIFICATION.md` dan workflow rebuilt.

**Status dokumen: UI/UX Baseline --- Rebuilt and Payment Synchronized.**
