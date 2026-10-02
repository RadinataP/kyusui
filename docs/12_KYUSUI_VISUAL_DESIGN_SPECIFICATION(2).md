# 12_KYUSUI_VISUAL_DESIGN_SPECIFICATION.md

**Project:** KYŪSUI  
**Study Case:** Berkah Water  
**Document:** `12_KYUSUI_VISUAL_DESIGN_SPECIFICATION.md`  
**Status:** REBUILT — Visual Design & UI Implementation Baseline  
**Platform:** Android Native  
**Language:** Kotlin  
**UI:** Jetpack Compose + Material 3  
**Architecture:** MVVM + ViewModel + StateFlow + Repository  
**Backend:** Laravel 13 / PHP 8.3+ REST API  
**Database:** MySQL 8.x  
**Authentication:** Laravel Sanctum  
**Payment:** QRIS Static + Manual Owner Verification / CASH + Courier Confirmation  
**Maps:** Google Maps SDK  
**Location:** Fused Location Provider  
**Notification:** Firebase Cloud Messaging  

---

# 0. Document Purpose

Dokumen ini menetapkan visual language, design tokens, component rules, payment visual states, formatting rules, accessibility rules, dan aturan implementasi UI KYŪSUI agar seluruh screen Android memiliki struktur visual yang konsisten dan dapat langsung diterjemahkan ke Jetpack Compose.

Dokumen ini melengkapi `03_KYUSUI_UI_UX_SPECIFICATION.md`.

Dokumen ini tidak mengganti business requirement, workflow, API contract, database schema, payment rules, tracking rules, notification rules, atau authorization rules pada dokumen dengan authority lebih tinggi.

Payment visual architecture pada dokumen ini mengikuti payment model terbaru:

```text
QRIS Static
    ↓
Customer pays externally
    ↓
Upload proof
    ↓
Owner verifies manually
    ↓
PAID
```

dan:

```text
CASH
    ↓
Customer pays Courier
    ↓
Assigned Courier confirms
    ↓
PAID
```

Canonical payment status:

```text
PENDING
WAITING_VERIFICATION
PAID
```

---

# 1. Source Authority and Consistency

Urutan authority project:

```text
00_KYUSUI_MASTER_SPECIFICATION.md
        ↓
01_KYUSUI_PROJECT_RULES.md
        ↓
02_KYUSUI_SYSTEM_ARCHITECTURE.md
        ↓
03_KYUSUI_UI_UX_SPECIFICATION.md
        ↓
04_KYUSUI_SYSTEM_WORKFLOW_REBUILT.md
        ↓
05_KYUSUI_DATABASE_SCHEMA_REBUILT.md
        ↓
06_KYUSUI_API_SPECIFICATION_REBUILT.md
        ↓
07_KYUSUI_ANDROID_ARCHITECTURE_REBUILT.md
        ↓
08_KYUSUI_PAYMENT_SPECIFICATION_REBUILT.md
        ↓
09_KYUSUI_TRACKING_SPECIFICATION.md
        ↓
10_KYUSUI_NOTIFICATION_SPECIFICATION.md
        ↓
11_KYUSUI_TESTING_SPECIFICATION.md
        ↓
12_KYUSUI_VISUAL_DESIGN_SPECIFICATION.md
```

Jika terdapat konflik, keputusan pada dokumen authority yang lebih tinggi harus dipertahankan dan dokumen visual harus diselaraskan.

Dokumen visual tidak boleh menciptakan business rule baru.

---

# 2. Design Direction

## 2.1 Brand Direction

KYŪSUI adalah aplikasi operasional pemesanan dan pengantaran air galon untuk satu depot, yaitu Berkah Water.

Visual identity harus memberi kesan:

- bersih;
- segar;
- terpercaya;
- praktis;
- cepat dipahami;
- modern;
- ringan;
- berorientasi tugas.

KYŪSUI bukan marketplace umum.

Fokus visual:

```text
ORDER
  ↓
PAYMENT
  ↓
PROCESS
  ↓
COURIER
  ↓
DELIVERY
  ↓
TRACKING
  ↓
COMPLETION
```

## 2.2 Visual Character

Karakter visual:

```text
Clean
Minimal
Functional
Mobile-first
Information-first
Task-oriented
```

Hindari:

- promo banner besar;
- product grid seperti marketplace;
- carousel promosi;
- wishlist;
- cart marketplace;
- dekorasi berlebihan;
- gradient dekoratif yang tidak diperlukan;
- shadow berat;
- dashboard desktop yang dipaksakan ke mobile;
- tabel besar pada layar Android;
- animasi yang mengganggu task utama.

## 2.3 Role-Based Visual Priority

### Customer

```text
Create Order
    ↓
Payment
    ↓
Active Order
    ↓
Tracking
    ↓
History
```

### Owner

```text
Incoming Order
    ↓
Payment Verification
    ↓
Process Order
    ↓
Courier Assignment
    ↓
Delivery Monitoring
```

### Courier

```text
Assigned Delivery
    ↓
Customer Location
    ↓
Navigation
    ↓
Active Delivery
    ↓
Completion
```

---

# 3. Platform and Implementation Rules

## 3.1 Android Native

KYŪSUI menggunakan satu aplikasi Android native.

```text
Kotlin
  ↓
Jetpack Compose
  ↓
Material 3
  ↓
ViewModel + StateFlow
  ↓
Repository
  ↓
Retrofit + OkHttp
  ↓
Laravel REST API
```

Tidak membuat tiga APK terpisah untuk Customer, Owner, dan Courier.

## 3.2 Material 3

Material 3 menjadi foundation untuk:

- color scheme;
- typography;
- buttons;
- text fields;
- cards;
- dialogs;
- bottom sheets;
- navigation bar;
- top app bar;
- snackbar;
- progress indicators;
- accessibility state.

Komponen custom hanya dibuat apabila komponen Material 3 tidak cukup untuk kebutuhan KYŪSUI.

## 3.3 UI Is Not Security Boundary

UI bukan security boundary.

Backend tetap menjadi authority untuk:

- authentication;
- authorization;
- ownership;
- payment state;
- order state;
- courier assignment;
- QRIS verification;
- CASH confirmation.

Hide menu atau route pada Android bukan pengganti authorization backend.

---

# 4. Design Tokens

## 4.1 Color Palette

### Primary

```text
Primary: #0EA5E9
```

### Secondary

```text
Secondary: #06B6D4
```

### Neutral

```text
Background: #F8FAFC
Surface: #FFFFFF
Text Primary: #0F172A
Text Secondary: #475569
Disabled: #94A3B8
Outline: #CBD5E1
```

### Semantic

```text
Success:
Foreground: #16A34A
Container: #DCFCE7

Warning:
Foreground: #D97706
Container: #FEF3C7

Error:
Foreground: #DC2626
Container: #FEE2E2

Info:
Foreground: #2563EB
Container: #DBEAFE
```

Warna semantic tidak boleh menjadi satu-satunya indikator state. State penting harus menggunakan kombinasi:

```text
Color + Icon + Text
```

## 4.2 Typography

Baseline typography:

| Style | Size | Weight |
|---|---:|---|
| Display Large | 32sp | Bold |
| Headline Large | 28sp | Bold |
| Headline Medium | 24sp | Bold |
| Title Large | 22sp | SemiBold |
| Title Medium | 16sp | SemiBold |
| Title Small | 14sp | SemiBold |
| Body Large | 16sp | Regular |
| Body Medium | 14sp | Regular |
| Body Small | 12sp | Regular |
| Label Large | 14sp | SemiBold |
| Label Medium | 12sp | SemiBold |
| Label Small | 11sp | SemiBold |

Button text menggunakan `Label Large`.

Screen title menggunakan `Headline` atau `Title` sesuai hierarchy.

## 4.3 Spacing

Base spacing unit:

```text
4dp
```

Token:

```text
4dp
8dp
12dp
16dp
20dp
24dp
32dp
40dp
48dp
```

Default horizontal screen padding:

```text
16dp
```

## 4.4 Shapes

```text
Small: 8dp
Medium: 12dp
Large: 16dp
Extra Large: 24dp
```

## 4.5 Elevation

```text
Level 0: normal content
Level 1: card
Level 2: floating element
Level 3: modal/dialog
```

Gunakan elevation secukupnya.

---

# 5. Iconography

Icon harus:

- mudah dikenali;
- konsisten;
- mendukung makna text;
- tidak menjadi satu-satunya informasi state.

Contoh:

```text
QRIS        → payment/QR related icon
CASH        → payments/cash related icon
PAID        → success/check icon
Verification → visibility/check related icon
Location    → location marker
Delivery    → delivery/navigation icon
```

Gunakan Material Icons atau icon set yang konsisten dengan Material 3.

Jangan menggunakan icon dekoratif yang tidak memiliki fungsi.

---

# 6. Core Components

Komponen utama:

- `PrimaryButton`
- `SecondaryButton`
- `DestructiveButton`
- `StatusChip`
- `OrderCard`
- `PaymentCard`
- `PaymentSummaryCard`
- `QRISCard`
- `ProofUploadCard`
- `ProofPreview`
- `CourierCard`
- `OrderProgress`
- `LocationCard`
- `TrackingMap`
- `InformationCard`
- `EmptyState`
- `ErrorState`
- `LoadingState`
- `ConfirmationDialog`
- `BottomSheet`
- `Snackbar`

## 6.1 Primary Actions

Contoh:

```text
Pesan Sekarang
Lanjutkan
Upload Bukti
Setujui
Uang Diterima
Mulai Pengantaran
```

## 6.2 Secondary Actions

Contoh:

```text
Lihat Detail
Ubah Lokasi
Lihat Bukti
Coba Lagi
```

## 6.3 Destructive Actions

Contoh:

```text
Tolak Bukti
```

Destructive action harus membutuhkan confirmation ketika perubahan bersifat consequential.

---

# 7. Order Status Visual

Status order yang digunakan pada UI harus mengikuti workflow canonical.

```text
MENUNGGU_PEMBAYARAN
MENUNGGU_DIPROSES
DIPROSES
DITUGASKAN
DALAM_PENGANTARAN
SELESAI
```

Status harus divisualisasikan dengan:

```text
Status text
+
Semantic icon
+
Semantic color
```

Jangan menggunakan warna tanpa label.

---

# 8. Order Progress

Progress order ditampilkan secara linear:

```text
Order
  ↓
Payment
  ↓
Processing
  ↓
Courier
  ↓
Delivery
  ↓
Completion
```

Progress hanya menampilkan state berdasarkan data backend.

Android tidak boleh menganggap sebuah step selesai hanya karena screen sebelumnya telah dibuka.

---

# 9. Payment Visual Architecture

Payment UI terdiri dari dua metode:

```text
QRIS
CASH
```

Tidak ada payment gateway dalam visual architecture.

## 9.1 Canonical Payment Status

Hanya:

```text
PENDING
WAITING_VERIFICATION
PAID
```

## 9.2 Visual Mapping

| Canonical Status | Visual Meaning | Primary Action |
|---|---|---|
| `PENDING` | Pembayaran belum selesai | Sesuai method |
| `WAITING_VERIFICATION` | Bukti menunggu pemeriksaan Owner | Tidak ada aksi pembayaran baru kecuali backend mengizinkan |
| `PAID` | Pembayaran diterima | Tidak ada payment action |

---

# 10. Payment Method Selection

Customer memilih:

```text
QRIS
CASH
```

Payment method card harus menampilkan:

- nama metode;
- icon;
- deskripsi singkat;
- state selected/unselected.

Contoh:

```text
QRIS
Bayar menggunakan QRIS Berkah Water

CASH
Bayar tunai kepada Courier
```

Selected state menggunakan:

- outline/filled container;
- icon;
- visual emphasis.

---

# 11. QRIS Visual Concept

QRIS KYŪSUI adalah static QRIS milik Berkah Water.

Visual harus memperjelas:

```text
QRIS Berkah Water
+
Total pembayaran
+
Instruksi pembayaran
+
Upload bukti
+
Status verifikasi
```

QRIS tidak boleh divisualisasikan sebagai dynamic transaction QR.

## 11.1 QRIS Content Hierarchy

Urutan visual:

```text
Total Pembayaran
      ↓
QRIS Image
      ↓
Instruksi
      ↓
Upload Bukti
      ↓
Payment Status
```

Nominal total harus berasal dari authoritative order/payment response.

---

# 12. QRIS PENDING

Visual state:

```text
PENDING
```

UI menampilkan:

```text
QRIS Berkah Water

Total
Rp 25.000

[ QR IMAGE ]

Bayar sesuai nominal yang tertera.

[ Upload Bukti ]
```

Customer belum mengirim proof.

Primary action:

```text
Upload Bukti
```

Jika proof belum ada, jangan menampilkan state seolah pembayaran sudah diterima.

---

# 13. QRIS WAITING_VERIFICATION

Setelah proof berhasil diterima backend:

```text
WAITING_VERIFICATION
```

UI:

```text
Bukti pembayaran sudah dikirim.

Bukti pembayaran sedang diperiksa oleh Owner.

Status:
WAITING_VERIFICATION
```

Tidak ada action:

```text
Mark as Paid
Confirm Payment
```

Customer tidak dapat menetapkan pembayaran berhasil.

---

# 14. QRIS PAID

Setelah Owner berhasil melakukan verifikasi:

```text
PAID
```

Visual:

```text
✓
Pembayaran diterima

Rp 25.000

QRIS
PAID
```

Gunakan success semantic styling.

Tidak perlu lagi menampilkan action upload proof.

---

# 15. QRIS Rejected / Re-upload

Jika Owner menolak proof, canonical status kembali:

```text
WAITING_VERIFICATION
        ↓
Owner rejection
        ↓
PENDING
```

UI dapat memberikan contextual message:

```text
Bukti Pembayaran Perlu Diperiksa

Silakan upload bukti pembayaran yang sesuai.
```

Canonical state tetap:

```text
PENDING
```

Primary action:

```text
Upload Bukti
```

Tidak membuat canonical payment status baru hanya untuk rejection.

---

# 16. QRIS Proof Upload

Format yang diperbolehkan:

```text
JPG
JPEG
PNG
```

Maximum size:

```text
5 MB
```

Visual upload state:

```text
Idle
  ↓
Selecting
  ↓
Uploading
  ↓
Uploaded
  ↓
WAITING_VERIFICATION
```

Error state harus dapat membedakan:

```text
File tidak didukung
File terlalu besar
Upload gagal
Network error
Server validation error
```

---

# 17. QRIS Proof Preview

Preview harus:

- mempertahankan aspect ratio;
- dapat dibuka untuk melihat detail;
- memiliki action yang jelas;
- tidak mengubah proof menjadi editable business record.

Customer hanya dapat melihat proof miliknya.

Owner hanya dapat melihat proof melalui authorization backend.

---

# 18. Owner QRIS Verification

Owner memiliki verification screen.

Information hierarchy:

```text
Order
↓
Customer
↓
Total
↓
Payment Method
↓
Proof Image
↓
Verification Action
```

Actions:

```text
Setujui
Tolak Bukti
```

Approval confirmation:

```text
Setujui bukti pembayaran ini?
```

Rejection confirmation:

```text
Tolak bukti pembayaran ini?
Customer perlu mengirim ulang bukti.
```

Setelah backend berhasil:

```text
PAID
```

atau:

```text
PENDING
```

UI mengikuti response backend.

---

# 19. QRIS Replacement

Owner dapat mengganti QRIS aktif.

## 19.1 Active QRIS

Hanya satu QRIS aktif yang digunakan oleh sistem.

Owner screen:

```text
QRIS Aktif
[ QR IMAGE ]

[ Ganti QRIS ]
```

## 19.2 Replacement Flow

```text
Open QRIS Settings
       ↓
Select New QRIS
       ↓
Preview
       ↓
Confirmation
       ↓
Upload
       ↓
Backend Validation
       ↓
New QRIS Active
```

## 19.3 Replacement Loading

Selama proses:

```text
Menyimpan QRIS...
```

Action penggantian harus dicegah dari duplicate submission.

## 19.4 Replacement Error

Jika gagal:

```text
QRIS belum berhasil diperbarui.

[Coba Lagi]
```

QRIS lama tetap menjadi active configuration sampai backend menyatakan replacement berhasil.

---

# 20. CASH Visual Concept

CASH adalah Cash on Delivery.

Payment dimulai:

```text
PENDING
```

Customer membayar kepada Courier.

Courier yang assigned mengonfirmasi uang diterima.

Flow:

```text
PENDING
   ↓
Customer gives cash
   ↓
Assigned Courier confirms
   ↓
PAID
```

---

# 21. CASH PENDING

Customer:

```text
Metode Pembayaran
CASH

Total
Rp 25.000

Bayar tunai kepada Courier saat pesanan diterima.
```

Courier:

```text
Pembayaran
CASH

Nominal
Rp 25.000

[ Uang Diterima ]
```

Action hanya tersedia ketika backend mengizinkan dan Courier benar-benar assigned.

---

# 22. CASH PAID

Setelah valid confirmation:

```text
PAID
```

Visual:

```text
✓
Pembayaran diterima

Rp 25.000

CASH
```

Tidak ada proof upload.

---

# 23. Courier Cash Confirmation

Confirmation screen/dialog:

```text
Konfirmasi Pembayaran

Apakah uang sebesar
Rp 25.000
sudah diterima dari Customer?

[ Batal ]
[ Uang Diterima ]
```

Backend tetap memvalidasi:

```text
authenticated courier
assigned courier
payment method = CASH
payment status = PENDING
order/payment belongs to assigned delivery
```

UI tidak boleh membuat Courier dapat mengonfirmasi pembayaran milik order lain.

---

# 24. Payment Detail Card

Standard payment card:

```text
Metode Pembayaran
QRIS

Nominal
Rp 25.000

Status
Menunggu Pembayaran
```

Untuk status `WAITING_VERIFICATION`:

```text
Metode Pembayaran
QRIS

Nominal
Rp 25.000

Status
Menunggu Verifikasi
```

Untuk `PAID`:

```text
Metode Pembayaran
QRIS

Nominal
Rp 25.000

Status
Pembayaran Diterima
```

Untuk CASH:

```text
Metode Pembayaran
CASH

Nominal
Rp 25.000

Status
Menunggu Pembayaran
```

Status canonical tetap mengikuti API.

---

# 25. Payment Summary Card

Contoh:

```text
Ringkasan Pembayaran

Subtotal
Rp 20.000

Biaya Pengantaran
Rp 5.000

Total
Rp 25.000
```

Jika komponen biaya tidak tersedia dalam business model tertentu, jangan membuat nilai baru.

Total harus berasal dari authoritative backend calculation.

---

# 26. Payment State Matrix

| Method | State | Customer | Owner | Courier |
|---|---|---|---|---|
| QRIS | `PENDING` | Upload proof | Monitor | - |
| QRIS | `WAITING_VERIFICATION` | Wait | Verify/Reject | - |
| QRIS | `PAID` | View | View | View if relevant |
| CASH | `PENDING` | Pay Courier | View | Confirm receipt |
| CASH | `WAITING_VERIFICATION` | Invalid combination | Invalid | Invalid |
| CASH | `PAID` | View | View | View |

`CASH + WAITING_VERIFICATION` adalah kombinasi state yang tidak valid.

---

# 27. QRIS State Transition Visual

```text
PENDING
   │
   │ Upload Proof
   ↓
WAITING_VERIFICATION
   │
   ├── Owner Approve
   │       ↓
   │      PAID
   │
   └── Owner Reject
           ↓
        PENDING
```

UI harus mengikuti current state dari backend.

---

# 28. CASH State Transition Visual

```text
PENDING
   │
   │ Courier confirms cash received
   ↓
PAID
```

Tidak ada state verification untuk CASH.

---

# 29. Payment State Rules

Rules visual:

1. Backend adalah source of truth.
2. Customer tidak dapat menetapkan `PAID`.
3. QRIS proof upload tidak sama dengan payment success.
4. `WAITING_VERIFICATION` hanya digunakan untuk QRIS.
5. CASH dimulai dari `PENDING`.
6. CASH dikonfirmasi oleh assigned Courier.
7. `PAID` hanya berasal dari valid backend transition.
8. UI tidak boleh mengarang state baru.
9. UI tidak boleh mengubah status hanya berdasarkan local click.
10. Setelah mutation berhasil, UI harus menggunakan response atau refreshed authoritative state.

---

# 30. Loading States

Loading state harus:

- mempertahankan layout utama;
- memberikan feedback;
- mencegah duplicate critical action;
- tidak menghapus context yang masih berguna.

Contoh:

```text
Mengunggah bukti...
Menyimpan QRIS...
Memuat detail pesanan...
```

---

# 31. Network Error

Format:

```text
Tidak dapat terhubung ke server.

Periksa koneksi internet dan coba lagi.

[Coba Lagi]
```

Jangan menampilkan stack trace atau technical exception kepada user.

---

# 32. Validation Error

Contoh:

```text
File terlalu besar.
Maksimal 5 MB.
```

atau:

```text
Format file tidak didukung.
Gunakan JPG, JPEG, atau PNG.
```

Error harus actionable.

---

# 33. Conflict State

Jika backend menolak mutation karena state telah berubah:

```text
Data pesanan sudah berubah.

Muat ulang data untuk melihat status terbaru.

[Muat Ulang]
```

Jangan menampilkan local state seolah mutation berhasil.

---

# 34. Unauthorized State

Jika backend mengembalikan authorization failure:

```text
Anda tidak memiliki akses ke data ini.
```

UI tidak boleh mencoba bypass authorization.

---

# 35. Not Found State

Contoh:

```text
Data pesanan tidak ditemukan.

[Kembali]
```

---

# 36. Success Feedback

Success feedback menggunakan:

- icon;
- text;
- semantic color;
- snackbar/dialog jika diperlukan.

Contoh:

```text
Bukti pembayaran berhasil dikirim.
```

atau:

```text
QRIS berhasil diperbarui.
```

Success feedback tidak boleh menjadi pengganti authoritative state refresh.

---

# 37. Notification Visual Integration

FCM notification bersifat informational.

Contoh:

```text
Pembayaran QRIS perlu diperiksa.
```

Notification membuka context yang relevan.

Notification tidak menjadi source of truth.

Saat screen dibuka:

```text
Open Screen
   ↓
Request Current State
   ↓
Render Backend State
```

---

# 38. Owner Dashboard QRIS Card

Owner dashboard dapat memiliki card:

```text
Verifikasi Pembayaran

3 pembayaran menunggu pemeriksaan

[ Lihat ]
```

Angka berasal dari backend.

Jika tidak ada:

```text
Tidak ada pembayaran yang menunggu pemeriksaan.
```

---

# 39. Owner Verification List

List item minimal:

```text
Order #XXXX
Customer Name
Rp 25.000
QRIS
Menunggu Verifikasi
```

Action:

```text
Lihat Detail
```

Verification action berada di detail screen agar context cukup.

---

# 40. QRIS Settings Access

QRIS replacement hanya untuk Owner.

```text
Customer → View active QRIS
Owner    → View + Replace QRIS
Courier  → No management access
```

Backend tetap melakukan authorization.

---

# 41. Accessibility

Semua critical information harus dapat dipahami melalui text.

Aturan:

- touch target minimal 48dp;
- contrast harus memadai;
- icon memiliki content description jika bermakna;
- jangan mengandalkan warna saja;
- text tidak boleh terpotong untuk nominal penting;
- font scale harus tetap usable;
- dialog harus dapat dinavigasi dengan accessibility services.

---

# 42. Payment Image Rules

QRIS image:

- harus cukup besar untuk dipindai;
- tidak boleh terdistorsi;
- gunakan aspect ratio asli;
- berikan background yang cukup kontras;
- jangan menambahkan overlay yang mengganggu QR code.

Proof image:

- preview tidak boleh terdistorsi;
- dapat diperbesar jika diperlukan;
- loading/error state harus tersedia.

---

# 43. General Screen State Architecture

Screen yang melakukan data loading harus mendukung state sesuai kebutuhan:

```text
Initial
Loading
Success
Empty
Error
Conflict
Unauthorized
```

Tidak semua screen wajib memiliki semua state, tetapi critical screens harus mendefinisikannya secara eksplisit.

---

# 44. Empty State

Empty state harus menjawab:

```text
Apa yang kosong?
Mengapa?
Apa tindakan berikutnya?
```

Contoh:

```text
Belum ada pembayaran yang menunggu pemeriksaan.
```

---

# 45. Dialog Rules

Dialog digunakan untuk:

- confirmation;
- consequential action;
- destructive action;
- payment confirmation;
- QRIS replacement confirmation.

Dialog tidak digunakan untuk informasi sederhana yang cukup disampaikan melalui screen atau snackbar.

---

# 46. Bottom Sheet Rules

Bottom sheet digunakan untuk:

- payment method selection;
- filter;
- detail;
- action contextual;
- courier selection;
- map-related controls.

Bottom sheet tidak boleh menampung workflow panjang yang lebih tepat menjadi full screen.

---

# 47. Animation Rules

Animation harus:

- singkat;
- meaningful;
- tidak menghambat task;
- tidak digunakan sebagai dekorasi berlebihan.

Hindari animation pada setiap perubahan data.

---

# 48. QRIS Animation

Animation dapat digunakan ketika:

- QRIS loading;
- proof upload;
- verification result.

Contoh:

```text
Uploading
   ↓
Success feedback
```

Tidak perlu animasi berlebihan pada QR image.

---

# 49. CASH Animation

CASH confirmation cukup menggunakan:

```text
Action
  ↓
Loading
  ↓
Success state
```

Tidak membutuhkan visual animation kompleks.

---

# 50. Navigation Rules

Navigation harus mengikuti role:

```text
CUSTOMER
Home
Orders
History
Profile

OWNER
Dashboard
Orders
Verification
QRIS
Profile

COURIER
Home
Assigned Orders
Delivery
History
Profile
```

Exact navigation destination mengikuti `03_KYUSUI_UI_UX_SPECIFICATION.md`.

---

# 51. Order Detail Payment Section

Order detail harus memiliki payment section.

Struktur:

```text
Order Information
      ↓
Customer/Delivery Information
      ↓
Payment Information
      ↓
Order Progress
      ↓
Tracking/Delivery
```

Payment section menampilkan:

- method;
- amount;
- canonical status;
- relevant action;
- timestamp jika tersedia;
- proof context jika relevan.

---

# 52. Customer Home Payment Reminder

Jika order membutuhkan action:

```text
Pembayaran belum selesai.

[ Lanjutkan Pembayaran ]
```

Untuk QRIS `WAITING_VERIFICATION`:

```text
Bukti pembayaran sedang diperiksa.
```

Untuk `PAID`:

```text
Pembayaran diterima.
```

---

# 53. Owner Home Payment Reminder

Contoh:

```text
Ada pembayaran QRIS yang menunggu pemeriksaan.

[ Lihat ]
```

---

# 54. Courier Home Payment Reminder

Untuk assigned CASH:

```text
Pembayaran CASH
Rp 25.000

Konfirmasi setelah uang diterima.

[ Uang Diterima ]
```

Action hanya tersedia jika backend menyatakan action valid.

---

# 55. Payment Action Rules

| Method | State | Actor | Action |
|---|---|---|---|
| QRIS | `PENDING` | Customer | Upload Bukti |
| QRIS | `WAITING_VERIFICATION` | Owner | Setujui / Tolak Bukti |
| QRIS | `PAID` | None | No payment mutation |
| CASH | `PENDING` | Courier | Uang Diterima |
| CASH | `PAID` | None | No payment mutation |

---

# 56. Payment Terminology

Canonical technical terminology:

```text
QRIS
CASH

PENDING
WAITING_VERIFICATION
PAID

Upload Bukti
Setujui
Tolak Bukti
Uang Diterima
QRIS Aktif
Ganti QRIS
```

Display labels dapat menggunakan Bahasa Indonesia sesuai UI/UX.

Technical canonical status tetap dipertahankan dalam API/domain model.

---

# 57. Component Inventory

Minimum reusable components:

```text
AppScaffold
TopBar
BottomNavigation
PrimaryButton
SecondaryButton
DestructiveButton
StatusChip
OrderCard
PaymentCard
PaymentSummaryCard
QRISCard
QRISSettingsCard
ProofUploadCard
ProofPreview
VerificationCard
CourierCard
OrderProgress
TrackingMap
LocationCard
InformationCard
LoadingState
EmptyState
ErrorState
ConfirmationDialog
```

---

# 58. Compose Mapping

Design token harus dipetakan ke:

```text
MaterialTheme.colorScheme
MaterialTheme.typography
MaterialTheme.shapes
```

Spacing dapat menggunakan constants/dimension tokens.

Reusable UI components tidak boleh membuat nilai visual berbeda untuk kasus yang sama tanpa alasan desain yang jelas.

---

# 59. ViewModel Mapping

ViewModel harus mengekspos UI state berdasarkan backend state.

Contoh konseptual:

```text
PaymentUiState
├── payment
├── loading
├── error
├── canUploadProof
├── canVerify
├── canReject
└── canConfirmCash
```

Boolean action state tidak boleh mengalahkan payment state dari backend.

---

# 60. QRIS Proof UI State

Minimum visual states:

```text
NoProof
Selecting
Uploading
Uploaded
WaitingVerification
RejectedRequiresResubmission
Paid
Error
```

`RejectedRequiresResubmission` adalah UI/context state, bukan canonical payment status.

Canonical state setelah rejection:

```text
PENDING
```

---

# 61. Owner Verification UI State

Minimum:

```text
Loading
Ready
Approving
Rejecting
Success
Error
Conflict
Unauthorized
```

---

# 62. QRIS Replacement UI State

Minimum:

```text
Loading
ActiveQRIS
Selecting
Preview
Saving
Success
Error
Conflict
```

---

# 63. CASH Confirmation UI State

Minimum:

```text
Ready
Confirming
Paid
Error
Conflict
Unauthorized
```

---

# 64. Money Formatting Rules

Seluruh nilai uang pada UI KYŪSUI menggunakan Rupiah Indonesia.

Format standar:

```text
Rp 10.000
```

## 64.1 Rules

- Gunakan prefix `Rp`.
- Gunakan satu spasi antara `Rp` dan angka.
- Gunakan titik (`.`) sebagai pemisah ribuan.
- Tidak menampilkan desimal untuk nominal Rupiah.
- Tidak menggunakan simbol mata uang lain.
- Tidak menampilkan format internal numeric sebagai presentation.
- Nominal `0` ditampilkan sebagai `Rp 0`.

Contoh:

```text
Rp 5.000
Rp 10.000
Rp 25.000
Rp 100.000
Rp 1.250.000
```

## 64.2 Internal vs Presentation

Nilai uang dari API/database diperlakukan sebagai numeric/integer amount.

Contoh:

```text
API:
25000

UI:
Rp 25.000
```

Jangan menyimpan string presentation seperti:

```text
"Rp 25.000"
```

sebagai nilai transaksi numeric.

Formatting dilakukan oleh presentation layer melalui formatter terpusat.

## 64.3 Money Consistency

Format uang harus konsisten pada:

- harga produk;
- subtotal;
- total order;
- biaya pengantaran jika tersedia;
- payment summary;
- payment detail;
- order history;
- dashboard Owner;
- QRIS;
- CASH;
- Courier confirmation.

## 64.4 Money Hierarchy

Contoh:

```text
Subtotal
Rp 20.000

Biaya Pengantaran
Rp 5.000

Total
Rp 25.000
```

Total memiliki visual emphasis lebih tinggi daripada subtotal.

Nominal tidak boleh terpotong secara ambigu.

---

# 65. Date and Time Formatting Rules

Tanggal pada UI menggunakan Bahasa Indonesia.

Format tanggal standar:

```text
dd MMMM yyyy
```

Contoh:

```text
05 Januari 2026
17 Februari 2026
09 Maret 2026
31 Desember 2026
```

Nama bulan:

```text
Januari
Februari
Maret
April
Mei
Juni
Juli
Agustus
September
Oktober
November
Desember
```

Hari menggunakan dua digit.

## 65.1 DateTime

Jika waktu diperlukan:

```text
dd MMMM yyyy, HH:mm
```

Contoh:

```text
05 Januari 2026, 14:30
```

Gunakan format 24 jam.

Contoh:

```text
08:00
12:30
17:45
21:15
```

## 65.2 Relative Date

Untuk list atau informasi ringkas dapat digunakan:

```text
Hari ini, 14:30
Kemarin, 09:15
```

Namun detail transaksi penting harus menyediakan timestamp absolut:

```text
05 Januari 2026, 14:30
```

## 65.3 Date Range

Format rentang:

```text
05–07 Januari 2026
28 Januari–02 Februari 2026
30 Desember 2026–02 Januari 2027
```

Gunakan en dash (`–`) untuk rentang.

## 65.4 Order Timeline

Event penting menggunakan:

```text
dd MMMM yyyy, HH:mm
```

Contoh:

```text
Pesanan dibuat
05 Januari 2026, 14:30

Bukti pembayaran dikirim
05 Januari 2026, 14:35

Pembayaran diterima
05 Januari 2026, 15:02
```

Event yang ditampilkan harus berasal dari timestamp authoritative backend.

---

# 66. Timezone Rules

Timestamp transaksi berasal dari backend.

Android harus melakukan parsing timestamp berdasarkan format dan timezone yang disepakati API contract.

Jangan melakukan perubahan timezone secara sembarangan di setiap screen.

Presentation timezone harus konsisten pada:

- order;
- payment;
- verification;
- delivery;
- tracking event;
- notification context;
- history.

---

# 67. Localization Rules

Bahasa UI utama:

```text
Bahasa Indonesia
```

Terminologi teknis canonical seperti enum/status internal tetap mengikuti API/domain contract.

Presentation layer menerjemahkan canonical state menjadi display label.

Contoh:

```text
PENDING
→ Menunggu Pembayaran

WAITING_VERIFICATION
→ Menunggu Verifikasi

PAID
→ Pembayaran Diterima
```

---

# 68. Responsive Mobile Rules

KYŪSUI adalah Android mobile application.

Prioritas layout:

```text
Small Android screen
        ↓
Normal Android screen
        ↓
Large Android screen
```

Layout harus tetap usable pada:

- portrait;
- font scale besar;
- screen width terbatas;
- keyboard visible;
- dialog;
- bottom sheet.

Jangan mengandalkan fixed width yang menyebabkan clipping.

---

# 69. Error Messaging Rules

Error message harus:

- singkat;
- jelas;
- actionable;
- tidak menyalahkan user;
- tidak membocorkan technical detail.

Contoh:

```text
Bukti pembayaran belum berhasil dikirim.
Silakan coba lagi.
```

Bukan:

```text
HTTP 500 / NullPointerException / SQL error
```

---

# 70. Duplicate Action Prevention

Critical actions harus memiliki protection terhadap duplicate submission:

```text
Upload Proof
Approve
Reject
Replace QRIS
Uang Diterima
```

Pattern:

```text
Idle
 ↓
Submitting
 ↓
Success / Error
```

Button disabled selama request aktif jika duplicate submission dapat menyebabkan side effect.

---

# 71. Backend Authority

UI harus selalu memperlakukan backend sebagai authority.

Contoh:

```text
Android local state
        ↓
Request
        ↓
Backend validation
        ↓
Database mutation
        ↓
Authoritative response
        ↓
UI update
```

Jangan:

```text
Button clicked
    ↓
Assume success
    ↓
Show PAID
```

---

# 72. Notification-to-Payment

Notification hanya trigger untuk membuka context.

Contoh:

```text
FCM:
"Pembayaran QRIS perlu diperiksa."
```

Saat Owner membuka:

```text
Request current verification data
        ↓
Backend
        ↓
Render current state
```

Jika state sudah berubah, UI menampilkan state terbaru.

---

# 73. Payment Visual Invariants

Invariant:

```text
QRIS:
PENDING
→ WAITING_VERIFICATION
→ PAID

Rejection:
WAITING_VERIFICATION
→ PENDING

CASH:
PENDING
→ PAID
```

Invariant:

```text
CASH + WAITING_VERIFICATION
= INVALID
```

Invariant:

```text
Customer cannot set PAID.
Owner verifies QRIS.
Assigned Courier confirms CASH.
```

---

# 74. Dark Theme

Jika dark theme diimplementasikan, semantic meaning harus tetap dipertahankan.

Jangan hanya membalik warna secara otomatis tanpa memastikan:

- contrast;
- readability;
- status visibility;
- QRIS readability;
- button readability;
- error/success visibility.

QRIS image harus tetap dapat dipindai dalam dark theme.

---

# 75. QA Acceptance Criteria

Visual implementation dianggap konsisten apabila:

- Material 3 digunakan sebagai foundation;
- design tokens digunakan secara konsisten;
- role-specific UI mengikuti workflow;
- payment hanya menampilkan QRIS dan CASH;
- canonical payment state hanya tiga state;
- QRIS proof flow divisualisasikan dengan benar;
- Owner verification divisualisasikan;
- Courier CASH confirmation divisualisasikan;
- QRIS replacement divisualisasikan;
- backend state menjadi authority;
- loading/error/conflict states tersedia untuk critical flows;
- accessibility rules diterapkan;
- format uang konsisten;
- format tanggal konsisten;
- timestamp menggunakan timezone yang disepakati;
- tidak ada clipping pada nominal penting;
- duplicate critical actions dicegah.

---

# 76. Visual QA Checklist

## General

- [ ] Material 3.
- [ ] Kotlin + Compose.
- [ ] Consistent spacing.
- [ ] Consistent typography.
- [ ] Consistent shape.
- [ ] Consistent elevation.
- [ ] Correct semantic colors.
- [ ] Accessibility labels.
- [ ] Minimum 48dp touch target.

## Payment

- [ ] QRIS tersedia.
- [ ] CASH tersedia.
- [ ] QRIS static.
- [ ] QRIS proof upload.
- [ ] Owner verification.
- [ ] Owner rejection.
- [ ] Re-upload flow.
- [ ] CASH Courier confirmation.
- [ ] `PENDING` supported.
- [ ] `WAITING_VERIFICATION` supported.
- [ ] `PAID` supported.
- [ ] Invalid CASH verification state prevented.
- [ ] No client-side payment success assumption.

## Money

- [ ] Prefix `Rp`.
- [ ] One space after `Rp`.
- [ ] Thousand separator uses `.`.
- [ ] No unnecessary decimal.
- [ ] Long amounts do not clip.
- [ ] Total visually emphasized.
- [ ] Centralized formatter.

## Date and Time

- [ ] Indonesian month names.
- [ ] Two-digit day.
- [ ] 24-hour time.
- [ ] Absolute timestamp available for important transactions.
- [ ] Relative date only used where appropriate.
- [ ] Consistent timezone.
- [ ] Centralized formatter.

---

# 77. Forbidden Visual Patterns

Jangan membuat:

```text
Marketplace-style home
Large promotional carousel
Unnecessary discount system
Unnecessary wishlist
Unnecessary cart abstraction
Dynamic QR payment visualization
Fake payment success
Client-side payment state mutation
Fake courier location
Fake tracking coordinate
Heavy dashboard table
Excessive animation
```

---

# 78. Forbidden Payment Architecture in UI

Visual implementation tidak boleh mengasumsikan:

```text
Payment Gateway
Provider Transaction
Provider Webhook
Dynamic QRIS
Automatic Provider Verification
```

QRIS hanya:

```text
Static QRIS
+
Customer Proof
+
Manual Owner Verification
```

CASH hanya:

```text
Cash to Courier
+
Assigned Courier Confirmation
```

---

# 79. Final Visual Principles

1. KYŪSUI adalah aplikasi operasional pemesanan air galon, bukan marketplace.
2. Material 3 menjadi design foundation.
3. Jetpack Compose menjadi UI implementation technology.
4. Visual language clean, functional, dan mobile-first.
5. Customer memprioritaskan order, payment, tracking, dan history.
6. Owner memprioritaskan order processing, payment verification, QRIS management, dan courier assignment.
7. Courier memprioritaskan assigned delivery, destination, payment CASH, dan completion.
8. Backend adalah authority untuk business state.
9. Notification bukan business-state authority.
10. UI bukan security boundary.
11. Status penting menggunakan text dan icon serta semantic color.
12. QRIS menggunakan static QRIS Berkah Water.
13. QRIS proof diverifikasi manual oleh Owner.
14. CASH dikonfirmasi oleh Assigned Courier.
15. Canonical payment state hanya `PENDING`, `WAITING_VERIFICATION`, dan `PAID`.
16. QRIS rejection kembali ke `PENDING`.
17. CASH tidak menggunakan verification state.
18. Nominal uang menggunakan format Rupiah Indonesia.
19. Tanggal dan waktu menggunakan format Bahasa Indonesia dan 24 jam.
20. Timestamp transaksi berasal dari backend.
21. Critical actions harus mencegah duplicate submission.
22. Loading, empty, error, dan conflict state harus dirancang secara eksplisit.
23. Tidak boleh ada business state yang diinventasikan oleh UI.
24. Reusable components harus konsisten melalui Compose design system.

---

# 80. Source Documents

Dokumen ini menggunakan sumber utama:

```text
03_KYUSUI_UI_UX_SPECIFICATION.md
07_KYUSUI_ANDROID_ARCHITECTURE.md
08_KYUSUI_PAYMENT_SPECIFICATION.md
```

dan mempertahankan aturan visual non-payment dari baseline sebelumnya.

Dokumen terkait:

```text
00_KYUSUI_MASTER_SPECIFICATION.md
01_KYUSUI_PROJECT_RULES.md
02_KYUSUI_SYSTEM_ARCHITECTURE.md
04_KYUSUI_SYSTEM_WORKFLOW_REBUILT.md
05_KYUSUI_DATABASE_SCHEMA_REBUILT.md
06_KYUSUI_API_SPECIFICATION_REBUILT.md
09_KYUSUI_TRACKING_SPECIFICATION.md
10_KYUSUI_NOTIFICATION_SPECIFICATION.md
11_KYUSUI_TESTING_SPECIFICATION.md
13_KYUSUI_DATABASE_FINALIZATION.md
13_KYUSUI_INTEGRATION_CONTRACT.md
```

Supporting academic/reference documents do not override KYŪSUI's locked business and technical requirements.

---

# 81. Final Status

```text
Document:
12_KYUSUI_VISUAL_DESIGN_SPECIFICATION.md

Status:
REBUILT — VISUAL DESIGN BASELINE

Platform:
Android Native

UI:
Kotlin + Jetpack Compose + Material 3

Architecture:
MVVM + ViewModel + StateFlow + Repository

Roles:
Customer / Owner / Courier

Payment:
QRIS Static + Manual Owner Verification
CASH + Courier Confirmation

Payment Status:
PENDING
WAITING_VERIFICATION
PAID

Money:
Rupiah Indonesia — Rp 10.000

Date:
dd MMMM yyyy

DateTime:
dd MMMM yyyy, HH:mm

Primary Domain:
Gallon Water Ordering + Payment + Delivery + Tracking
```

This document is the visual implementation reference for KYŪSUI Android before screen-by-screen Compose implementation.
