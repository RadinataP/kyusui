# KYŪSUI — NOTIFICATION SPECIFICATION

**Project:** KYŪSUI  
**Study Case:** Berkah Water  
**Document:** `10_KYUSUI_NOTIFICATION_SPECIFICATION.md`  
**Status:** REBUILT — PAYMENT NOTIFICATION ARCHITECTURE SYNCHRONIZED  
**Technology:** Firebase Cloud Messaging (FCM)  
**Platform:** Android Native  
**Backend:** Laravel 13 / PHP 8.3+  
**Database:** MySQL 8.x  
**Authentication:** Laravel Sanctum

**Authority:**

```text
00_KYUSUI_MASTER_SPECIFICATION.md
        ↓
01_KYUSUI_PROJECT_RULES.md
        ↓
02_KYUSUI_SYSTEM_ARCHITECTURE.md
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
```

> **Notification Architecture Override:** FCM hanya merupakan notification transport. Backend Laravel dan MySQL tetap menjadi source of truth untuk order, payment, delivery, tracking, authorization, dan notification record. Payment notification hanya dipicu oleh business event internal KYŪSUI yang berhasil dipersist/commit.

---

# 1. Purpose

Dokumen ini mendefinisikan arsitektur notification KYŪSUI menggunakan Firebase Cloud Messaging (FCM).

Dokumen menetapkan:

- notification event;
- business trigger;
- sender dan recipient;
- notification persistence;
- FCM transport;
- payload contract;
- Android foreground behavior;
- Android background behavior;
- cold-start behavior;
- deep link/navigation;
- FCM token lifecycle;
- authorization;
- security;
- failure handling;
- idempotency;
- notification-to-payment integration;
- notification-to-order integration;
- notification-to-delivery integration;
- notification-to-tracking integration;
- testing requirements;
- acceptance criteria.

Dokumen ini tidak berisi source code.

Notification adalah mekanisme penyampaian informasi. Notification bukan source of truth untuk:

```text
Order
Payment
Delivery
Tracking
Authorization
```

Ketika notification diterima atau dibuka, Android harus mengambil state terbaru dari Laravel API.

---

# 2. Core Architectural Principles

## 2.1 Backend/MySQL Is the Source of Truth

Business state selalu berasal dari backend.

```text
Android
   ↓
Laravel REST API
   ↓
Business Validation
   ↓
MySQL
   ↓
Committed Business State
```

Notification dibuat berdasarkan business event yang telah berhasil dipersist.

```text
Business Action
      ↓
Authentication
      ↓
Authorization
      ↓
Business Validation
      ↓
Database Mutation
      ↓
COMMIT
      ↓
Notification Event
      ↓
Notification Record
      ↓
FCM
```

Notification tidak boleh menjadi mekanisme untuk mengubah business state.

---

## 2.2 FCM Is Transport Only

FCM digunakan untuk mengirim informasi dari backend ke Android.

```text
Laravel Notification Service
          ↓
Firebase Cloud Messaging
          ↓
Android Device
```

FCM tidak:

- menentukan payment status;
- menentukan order status;
- menentukan delivery status;
- melakukan authorization;
- membaca MySQL;
- menjadi source of truth;
- menggantikan REST API.

---

## 2.3 Notification Payload Is Not Authoritative

Payload notification hanya membawa context minimum untuk routing.

Contoh:

```text
type
notification_id
resource_type
resource_id
order_id
target_route
created_at
```

Payload tidak boleh digunakan sebagai satu-satunya dasar untuk:

```text
payment = PAID
order = SELESAI
delivery = active
tracking = available
```

Pola yang benar:

```text
FCM notification
      ↓
Open relevant screen
      ↓
Call Laravel API
      ↓
Receive current state
      ↓
Render authoritative state
```

---

## 2.4 Notification Must Follow Authorization

Notification hanya dikirim kepada user yang mempunyai relationship/context yang sah terhadap resource.

```text
Business Event
      ↓
Resource
      ↓
Determine Recipient
      ↓
Role Check
      ↓
Ownership / Assignment Check
      ↓
Notification Record
      ↓
FCM
```

Customer A tidak boleh menerima notification untuk order Customer B.

Courier hanya menerima notification untuk assignment yang menjadi miliknya.

Owner hanya menerima notification dalam operational scope Berkah Water.

---

## 2.5 Notification After Commit

Business mutation harus berhasil terlebih dahulu.

```text
DB Transaction
      ↓
COMMIT
      ↓
Dispatch Notification
```

Jika transaction gagal:

```text
ROLLBACK
      ↓
No committed business event
      ↓
No business notification
```

Kegagalan pengiriman FCM tidak boleh membatalkan business transaction yang sudah berhasil.

---

# 3. Notification Architecture

```text
                    KYŪSUI BUSINESS EVENT
                            │
                            ▼
                    Laravel 13 Backend
                            │
                ┌───────────┴───────────┐
                ▼                       ▼
        Business State             Notification
           Mutation                   Event
                │                       │
                ▼                       ▼
              MySQL              Notification Record
                │                       │
                │                       ▼
                │              Notification Service
                │                       │
                │                       ▼
                │              Firebase Cloud Messaging
                │                       │
                │                       ▼
                │                Android FCM Handler
                │                       │
                └──────────────┬────────┘
                               ▼
                       Deep Link / UI Event
                               │
                               ▼
                       Laravel API Refresh
                               │
                               ▼
                       Authoritative UI State
```

---

# 4. Notification Actors

## 4.1 Business Actors

Business actor adalah pihak yang menyebabkan business event.

```text
CUSTOMER
OWNER
COURIER
SYSTEM
```

`SYSTEM` berarti backend application logic yang menghasilkan event dari state transition internal.

Tidak ada actor eksternal yang menjadi authority payment notification.

---

## 4.2 Technical Sender

Technical sender untuk push notification selalu:

```text
Laravel Notification Service
        ↓
Firebase Cloud Messaging
```

Android actor tidak mengirim push notification langsung ke Android actor lain.

Contoh:

```text
Owner approves QRIS
        ↓
Laravel updates payment
        ↓
COMMIT
        ↓
Notification Service
        ↓
FCM
        ↓
Customer Android
```

---

# 5. Notification Event Taxonomy

Notification event aktif KYŪSUI dibagi menjadi empat domain:

```text
ORDER
PAYMENT
DELIVERY
TRACKING
```

## 5.1 Order Events

```text
ORDER_CREATED
ORDER_PROCESSED
```

## 5.2 Payment Events

```text
PAYMENT_QRIS_PROOF_UPLOADED
PAYMENT_QRIS_APPROVED
PAYMENT_QRIS_REJECTED
PAYMENT_CASH_CONFIRMED
```

## 5.3 Delivery Events

```text
COURIER_ASSIGNED
DELIVERY_STARTED
ORDER_COMPLETED
```

## 5.4 Tracking Events

```text
TRACKING_AVAILABLE
```

Event-event tersebut merupakan baseline notification trigger.

Tidak setiap internal state change harus menghasilkan push notification.

---

# 6. Final Notification Matrix

| Event | Trigger | Recipient | Business Meaning | Target |
|---|---|---|---|---|
| `ORDER_CREATED` | Order berhasil committed | Owner + Customer | Order baru berhasil dibuat | Order Detail |
| `ORDER_PROCESSED` | Order berubah ke `DIPROSES` | Customer | Order mulai diproses | Order Detail |
| `PAYMENT_QRIS_PROOF_UPLOADED` | Customer berhasil upload proof | Owner | Ada proof QRIS yang menunggu verifikasi | Payment Verification |
| `PAYMENT_QRIS_APPROVED` | Owner approve proof | Customer | Payment QRIS menjadi `PAID` | Payment / Order |
| `PAYMENT_QRIS_REJECTED` | Owner reject proof | Customer | Proof ditolak dan payment kembali `PENDING` | Payment / Order |
| `PAYMENT_CASH_CONFIRMED` | Assigned Courier konfirmasi uang diterima | Customer | Cash menjadi `PAID` | Payment / Order |
| `COURIER_ASSIGNED` | Assignment courier committed | Customer + Assigned Courier | Courier telah ditugaskan | Order / Delivery |
| `DELIVERY_STARTED` | Delivery dimulai | Customer | Order masuk pengantaran | Tracking |
| `TRACKING_AVAILABLE` | Lokasi valid tersedia untuk active delivery | Customer | Tracking dapat dilihat | Tracking |
| `ORDER_COMPLETED` | Order berubah ke `SELESAI` | Customer + Owner + Courier | Delivery selesai | Order / History |

---

# 7. Payment Notification Architecture

Payment notification harus mengikuti payment architecture terbaru:

```text
Payment Method:
QRIS
CASH
```

Canonical payment status:

```text
PENDING
WAITING_VERIFICATION
PAID
```

Payment lifecycle:

```text
QRIS:
PENDING
   ↓
WAITING_VERIFICATION
   ↓
PAID
```

Rejection:

```text
WAITING_VERIFICATION
   ↓
PENDING
```

Cash:

```text
PENDING
   ↓
Assigned Courier confirms cash
   ↓
PAID
```

Notification hanya mengikuti transition yang telah berhasil dilakukan backend.

---

# 8. QRIS Payment Notifications

## 8.1 PAYMENT_QRIS_PROOF_UPLOADED

### Trigger

Customer berhasil mengunggah bukti pembayaran QRIS untuk order miliknya.

Canonical transition:

```text
PENDING
   ↓
Customer uploads proof
   ↓
WAITING_VERIFICATION
```

Backend harus terlebih dahulu:

```text
authenticate customer
        ↓
validate order ownership
        ↓
validate payment exists
        ↓
validate payment method = QRIS
        ↓
validate payment status = PENDING
        ↓
validate order state
        ↓
validate proof
        ↓
store proof
        ↓
update payment status
        ↓
COMMIT
        ↓
emit PAYMENT_QRIS_PROOF_UPLOADED
```

### Recipient

```text
OWNER
```

Customer tidak perlu menerima push notification bahwa dirinya sendiri berhasil upload proof karena UI dapat menggunakan response API. Notification ini ditujukan kepada Owner sebagai operational alert.

### Notification

```text
Title:
Bukti Pembayaran QRIS Baru

Body:
Bukti pembayaran #ORD-XXXX menunggu verifikasi.
```

### Payload

```text
schema_version
notification_id
type = PAYMENT_QRIS_PROOF_UPLOADED
resource_type = PAYMENT
resource_id = payment_id
order_id
payment_method = QRIS
payment_status = WAITING_VERIFICATION
target_route
created_at
```

### Target Route

```text
owner/payment-verification/{order_id}
```

### Android Behavior

Owner membuka notification:

```text
Notification
    ↓
Payment Verification Screen
    ↓
GET current payment/proof
    ↓
Render current backend state
```

Jika payment sudah diverifikasi sebelum notification dibuka, Android harus menampilkan state terbaru dan tidak menganggap notification sebagai state `WAITING_VERIFICATION`.

---

# 9. QRIS Approval Notification

## 9.1 PAYMENT_QRIS_APPROVED

### Trigger

Owner berhasil memverifikasi proof QRIS.

Canonical transition:

```text
WAITING_VERIFICATION
        ↓
Owner APPROVE
        ↓
PAID
```

Backend wajib:

```text
authenticate Owner
        ↓
validate Owner role
        ↓
validate operational scope
        ↓
validate order/payment
        ↓
validate payment_method = QRIS
        ↓
validate payment_status = WAITING_VERIFICATION
        ↓
validate proof exists
        ↓
update payment = PAID
        ↓
set verification metadata
        ↓
COMMIT
        ↓
emit PAYMENT_QRIS_APPROVED
```

### Recipient

```text
CUSTOMER
```

### Notification

```text
Title:
Pembayaran Berhasil

Body:
Pembayaran QRIS untuk #ORD-XXXX telah disetujui.
```

### Payload

```text
schema_version
notification_id
type = PAYMENT_QRIS_APPROVED
resource_type = PAYMENT
resource_id = payment_id
order_id
payment_method = QRIS
payment_status = PAID
target_route
created_at
```

### Target Route

```text
customer/orders/{order_id}/payment
```

### Android Behavior

```text
FCM
 ↓
Open payment/order context
 ↓
GET latest payment
 ↓
Backend returns PAID
 ↓
Render "Pembayaran Diterima"
```

Android tidak boleh:

```text
FCM says PAID
    ↓
set local state to PAID
```

Tanpa refresh API.

---

# 10. QRIS Rejection Notification

## 10.1 PAYMENT_QRIS_REJECTED

### Trigger

Owner menolak proof QRIS.

Canonical transition:

```text
WAITING_VERIFICATION
        ↓
Owner REJECT
        ↓
PENDING
```

Rejection bukan payment failure state.

Tidak membuat status:

```text
REJECTED
FAILED
```

Payment kembali ke:

```text
PENDING
```

agar customer dapat mengunggah proof baru.

### Backend Sequence

```text
authenticate Owner
        ↓
validate authorization
        ↓
validate payment_method = QRIS
        ↓
validate payment_status = WAITING_VERIFICATION
        ↓
validate proof exists
        ↓
record rejection action
        ↓
set payment_status = PENDING
        ↓
COMMIT
        ↓
emit PAYMENT_QRIS_REJECTED
```

### Recipient

```text
CUSTOMER
```

### Notification

```text
Title:
Bukti Pembayaran Ditolak

Body:
Bukti pembayaran #ORD-XXXX ditolak.
Silakan periksa pembayaran dan upload bukti baru.
```

Jika rejection note tersedia, note dapat ditampilkan melalui API setelah notification dibuka. Jangan memasukkan detail internal yang sensitif ke FCM payload.

### Payload

```text
schema_version
notification_id
type = PAYMENT_QRIS_REJECTED
resource_type = PAYMENT
resource_id = payment_id
order_id
payment_method = QRIS
payment_status = PENDING
target_route
created_at
```

### Target Route

```text
customer/orders/{order_id}/payment
```

### Android Behavior

```text
FCM
 ↓
Payment Screen
 ↓
GET current payment
 ↓
PENDING
 ↓
Enable "Upload Bukti Baru"
```

Notification tidak mengubah payment state secara lokal.

---

# 11. CASH Payment Notification

## 11.1 PAYMENT_CASH_CONFIRMED

### Trigger

Assigned Courier menerima uang tunai dari customer dan menekan:

```text
Uang Diterima
```

Canonical transition:

```text
PENDING
   ↓
Assigned Courier confirms
   ↓
Backend validates assignment
   ↓
PAID
```

### Backend Validation

Minimal:

```text
authenticated user
        ↓
role = COURIER
        ↓
order exists
        ↓
payment exists
        ↓
payment_method = CASH
        ↓
payment_status = PENDING
        ↓
active courier assignment exists
        ↓
assignment belongs to authenticated courier
        ↓
update payment = PAID
        ↓
COMMIT
        ↓
emit PAYMENT_CASH_CONFIRMED
```

### Recipient

```text
CUSTOMER
```

Owner tidak menerima payment success notification sebagai primary recipient karena action ini merupakan confirmation oleh Courier untuk customer.

### Notification

```text
Title:
Pembayaran Cash Diterima

Body:
Pembayaran cash untuk #ORD-XXXX telah dikonfirmasi oleh Courier.
```

### Payload

```text
schema_version
notification_id
type = PAYMENT_CASH_CONFIRMED
resource_type = PAYMENT
resource_id = payment_id
order_id
payment_method = CASH
payment_status = PAID
target_route
created_at
```

### Target Route

```text
customer/orders/{order_id}/payment
```

### Android Behavior

```text
FCM
 ↓
Order / Payment Screen
 ↓
GET current payment
 ↓
PAID
 ↓
Render "Pembayaran Diterima"
```

---

# 12. Payment Notification Invariants

Invariant utama:

```text
QRIS:
PENDING
→ WAITING_VERIFICATION
→ PAID
```

```text
QRIS rejection:
WAITING_VERIFICATION
→ PENDING
```

```text
CASH:
PENDING
→ PAID
```

Notification invariant:

```text
QRIS proof uploaded
→ Owner notified
```

```text
Owner approves QRIS
→ Customer notified
→ payment is PAID
```

```text
Owner rejects QRIS
→ Customer notified
→ payment is PENDING
```

```text
Assigned Courier confirms CASH
→ Customer notified
→ payment is PAID
```

Tidak ada notification event yang boleh menghasilkan transition payment secara langsung.

---

# 13. ORDER_CREATED

## Trigger

Customer berhasil membuat order dan backend telah menyimpan order secara valid.

```text
Customer
   ↓
Create Order API
   ↓
Laravel validation
   ↓
Order committed
   ↓
ORDER_CREATED
```

## Recipients

```text
OWNER
CUSTOMER
```

## Owner Notification

```text
Title:
Pesanan Baru

Body:
Pesanan #ORD-XXXX telah masuk.
```

## Customer Notification

```text
Title:
Pesanan Berhasil Dibuat

Body:
Pesanan #ORD-XXXX berhasil dibuat.
```

## Payload

```text
schema_version
notification_id
type = ORDER_CREATED
resource_type = ORDER
resource_id = order_id
order_id
order_status
target_route
created_at
```

## Target

```text
Owner:
owner/orders/{order_id}

Customer:
customer/orders/{order_id}
```

---

# 14. ORDER_PROCESSED

## Trigger

Owner berhasil mengubah order menjadi:

```text
DIPROSES
```

setelah backend memvalidasi transition.

## Recipient

```text
CUSTOMER
```

## Notification

```text
Title:
Pesanan Sedang Diproses

Body:
Pesanan #ORD-XXXX sedang diproses oleh Berkah Water.
```

## Payload

```text
schema_version
notification_id
type = ORDER_PROCESSED
resource_type = ORDER
resource_id = order_id
order_id
order_status = DIPROSES
target_route
created_at
```

## Android Behavior

Foreground:

```text
Receive event
 ↓
Refresh current order
 ↓
Show in-app feedback
```

Background:

```text
Show system notification
```

Tap:

```text
→ Customer Order Detail
```

---

# 15. COURIER_ASSIGNED

## Trigger

Owner berhasil membuat courier assignment.

```text
Order
  ↓
Courier Assignment
  ↓
Assignment committed
  ↓
COURIER_ASSIGNED
```

## Recipients

```text
CUSTOMER
ASSIGNED COURIER
```

## Customer Notification

```text
Title:
Pengantar Ditugaskan

Body:
Pesanan #ORD-XXXX telah ditugaskan kepada pengantar.
```

## Courier Notification

```text
Title:
Pesanan Baru Ditugaskan

Body:
Anda mendapat tugas pengantaran #ORD-XXXX.
```

## Payload

```text
schema_version
notification_id
type = COURIER_ASSIGNED
resource_type = DELIVERY_ASSIGNMENT
resource_id = assignment_id
order_id
assignment_id
target_route
created_at
```

## Targets

```text
Customer:
customer/orders/{order_id}

Courier:
courier/deliveries/{order_id}
```

---

# 16. DELIVERY_STARTED

## Trigger

Courier berhasil memulai delivery.

```text
Assigned
   ↓
Courier starts delivery
   ↓
Backend validates
   ↓
DALAM_PENGANTARAN
   ↓
DELIVERY_STARTED
```

## Recipient

```text
CUSTOMER
```

## Notification

```text
Title:
Pesanan Sedang Diantar

Body:
Pesanan #ORD-XXXX sedang dalam pengantaran.
```

## Payload

```text
schema_version
notification_id
type = DELIVERY_STARTED
resource_type = ORDER
resource_id = order_id
order_id
order_status = DALAM_PENGANTARAN
assignment_id
target_route
created_at
```

## Target

```text
customer/orders/{order_id}/tracking
```

---

# 17. TRACKING_AVAILABLE

## Trigger

Tracking menjadi tersedia karena backend telah menerima lokasi courier yang valid untuk active delivery.

```text
Active Delivery
      ↓
Valid Courier Location
      ↓
Backend accepts location
      ↓
TRACKING_AVAILABLE
```

Notification tidak harus dikirim pada setiap location update.

Event ini hanya menandakan bahwa customer dapat mengakses tracking context.

## Recipient

```text
CUSTOMER
```

## Notification

```text
Title:
Lacak Pesanan

Body:
Lokasi pengantar tersedia untuk pesanan #ORD-XXXX.
```

## Payload

```text
schema_version
notification_id
type = TRACKING_AVAILABLE
resource_type = DELIVERY_ASSIGNMENT
resource_id = assignment_id
order_id
assignment_id
target_route
created_at
```

## Security

Jangan mengirim koordinat courier sebagai payload notification.

```text
FCM
 ↓
Tracking context
 ↓
Authorized Tracking API
 ↓
Current location
```

Lokasi tetap dilindungi oleh backend authorization.

---

# 18. ORDER_COMPLETED

## Trigger

Order berhasil berubah menjadi:

```text
SELESAI
```

melalui delivery/order completion workflow.

## Recipients

```text
CUSTOMER
OWNER
COURIER
```

## Customer Notification

```text
Title:
Pesanan Selesai

Body:
Pesanan #ORD-XXXX telah selesai.
```

## Owner/Courier Notification

```text
Title:
Pesanan Selesai

Body:
Pesanan #ORD-XXXX telah selesai.
```

## Payload

```text
schema_version
notification_id
type = ORDER_COMPLETED
resource_type = ORDER
resource_id = order_id
order_id
order_status = SELESAI
target_route
created_at
```

## Targets

```text
Customer:
customer/orders/{order_id}

Owner:
owner/orders/{order_id}

Courier:
courier/deliveries/{order_id}
```

## Tracking Behavior

```text
ORDER_COMPLETED
      ↓
Active tracking stops
```

Customer tidak boleh menerima notification yang mengesankan delivery masih aktif setelah order selesai.

---

# 19. Common Payload Contract

Semua notification menggunakan struktur konseptual:

```text
schema_version
notification_id
type
resource_type
resource_id
order_id
target_route
created_at
```

Event-specific fields:

| Event | Additional Fields |
|---|---|
| `ORDER_CREATED` | `order_status` |
| `ORDER_PROCESSED` | `order_status` |
| `PAYMENT_QRIS_PROOF_UPLOADED` | `payment_method`, `payment_status` |
| `PAYMENT_QRIS_APPROVED` | `payment_method`, `payment_status` |
| `PAYMENT_QRIS_REJECTED` | `payment_method`, `payment_status` |
| `PAYMENT_CASH_CONFIRMED` | `payment_method`, `payment_status` |
| `COURIER_ASSIGNED` | `assignment_id` |
| `DELIVERY_STARTED` | `assignment_id`, `order_status` |
| `TRACKING_AVAILABLE` | `assignment_id` |
| `ORDER_COMPLETED` | `order_status` |

Payload harus:

- kecil;
- non-secret;
- cocok untuk routing;
- tidak menjadi source of truth;
- tidak membawa password;
- tidak membawa Sanctum token;
- tidak membawa Firebase server credential;
- tidak membawa private proof image;
- tidak membawa koordinat courier yang tidak diperlukan;
- tidak membawa full database record.

---

# 20. Notification Persistence

Database KYŪSUI memiliki konsep:

```text
notifications
```

Notification record digunakan untuk:

- notification history;
- unread state;
- audit notification intent;
- mapping notification ID;
- in-app notification list.

Konseptual field:

```text
id
user_id
type
title
body
data
read_at
sent_at
created_at
updated_at
```

Notification record bukan pengganti:

```text
orders
payments
courier_assignments
courier_locations
```

---

# 21. Notification Lifecycle

```text
BUSINESS ACTION
      ↓
Authentication
      ↓
Authorization
      ↓
Business Validation
      ↓
DB Mutation
      ↓
COMMIT
      ↓
Create Notification Record
      ↓
Dispatch Notification Job
      ↓
FCM
      ↓
Android
      ↓
Foreground / Background Handling
      ↓
Deep Link
      ↓
GET Authoritative Resource
      ↓
Render Current State
      ↓
Mark Notification Read
```

Jika FCM gagal:

```text
Business state remains valid
Notification delivery may fail
Retry may occur
```

Tidak boleh:

```text
FCM failure
   ↓
Rollback payment
```

---

# 22. Android Notification Handling

## 22.1 Reception Layer

Android menggunakan:

```text
FirebaseMessagingService
        ↓
Notification Handler
        ↓
Navigation / UI Event
```

`FirebaseMessagingService` tidak menjadi tempat business logic kompleks.

Tanggung jawabnya:

- menerima message;
- membaca payload;
- membuat system notification bila diperlukan;
- meneruskan routing;
- menangani token refresh.

---

## 22.2 Foreground

Ketika aplikasi sedang aktif:

```text
FCM
 ↓
Notification Handler
 ↓
Determine current screen
 ↓
Refresh relevant API resource
 ↓
Show in-app notification/feedback
```

Jangan membuat duplicate notification UX jika user sudah berada pada screen yang sama dan state telah diperbarui.

---

## 22.3 Background

Ketika aplikasi berada di background:

```text
FCM
 ↓
System Notification
 ↓
User Tap
 ↓
Open Target Route
 ↓
API Refresh
```

---

## 22.4 Cold Start

Jika aplikasi belum berjalan:

```text
FCM
 ↓
User Tap
 ↓
Application Start
 ↓
Restore Session
 ↓
Resolve Role
 ↓
Validate Route
 ↓
Fetch Authoritative Resource
 ↓
Render Screen
```

Jika session tidak valid:

```text
Login
 ↓
After authentication
 ↓
Resolve pending notification route
```

---

# 23. Notification Tap Rules

Notification tap selalu menghasilkan navigation context.

Contoh:

```text
ORDER_CREATED
    ↓
Order Detail
```

```text
PAYMENT_QRIS_PROOF_UPLOADED
    ↓
Owner Payment Verification
```

```text
PAYMENT_QRIS_APPROVED
    ↓
Customer Payment / Order
```

```text
PAYMENT_QRIS_REJECTED
    ↓
Customer Payment
```

```text
PAYMENT_CASH_CONFIRMED
    ↓
Customer Payment / Order
```

```text
COURIER_ASSIGNED
    ↓
Order / Delivery
```

```text
DELIVERY_STARTED
    ↓
Tracking
```

```text
TRACKING_AVAILABLE
    ↓
Tracking
```

```text
ORDER_COMPLETED
    ↓
Order / History
```

Setelah navigation:

```text
GET latest resource
```

---

# 24. FCM Token Lifecycle

## 24.1 Registration

Android memperoleh FCM token lalu mendaftarkannya ke backend melalui authenticated API.

Conceptual flow:

```text
Firebase generates token
        ↓
Android receives token
        ↓
Authenticated API
        ↓
Laravel associates token with user/device
```

FCM token bukan:

```text
password
Sanctum token
authorization credential
```

---

## 24.2 Token Refresh

Jika token berubah:

```text
New FCM token
      ↓
Authenticated registration
      ↓
Backend updates token association
```

Android tidak boleh menganggap token lama masih valid tanpa batas.

---

## 24.3 Logout

Sanctum session lifecycle dan FCM token lifecycle tetap berbeda.

Saat logout, backend harus memiliki mekanisme agar device token tidak lagi digunakan untuk user yang sudah tidak memiliki session/device association yang valid.

---

## 24.4 Multi-device

Jika satu user mendukung lebih dari satu device, model token harus mengikuti keputusan database yang berlaku.

Konseptual:

```text
User
 └── Device Token(s)
       ├── Device A
       ├── Device B
       └── ...
```

Jangan menjadikan satu `users.fcm_token` sebagai asumsi permanen tanpa keputusan schema.

---

# 25. Token Security

FCM token:

- bukan password;
- bukan Sanctum token;
- bukan Firebase server credential;
- tidak digunakan sebagai authorization;
- harus dikaitkan dengan authenticated user;
- tidak boleh didaftarkan atas nama user lain;
- tidak boleh digunakan untuk menentukan role;
- tidak boleh dicetak ke production log tanpa kebutuhan yang sah.

Backend harus memastikan:

```text
authenticated user
      ↓
owns device/token registration
```

---

# 26. Notification Authorization Matrix

| Event | Customer | Owner | Courier |
|---|---:|---:|---:|
| `ORDER_CREATED` | Own order | Operational scope | No |
| `ORDER_PROCESSED` | Own order | No | No |
| `PAYMENT_QRIS_PROOF_UPLOADED` | No | Operational scope | No |
| `PAYMENT_QRIS_APPROVED` | Own order | No | No |
| `PAYMENT_QRIS_REJECTED` | Own order | No | No |
| `PAYMENT_CASH_CONFIRMED` | Own order | No | No |
| `COURIER_ASSIGNED` | Own order | No | Assigned courier only |
| `DELIVERY_STARTED` | Own order | No | Assigned courier context only if needed |
| `TRACKING_AVAILABLE` | Own order | No | Assignment context if needed |
| `ORDER_COMPLETED` | Own order | Operational scope | Assigned courier |

Authorization harus tetap dilakukan backend. Matrix ini tidak menggantikan policy/authorization implementation.

---

# 27. Failure Handling

## 27.1 FCM Send Failure

Jika FCM gagal:

```text
Business event = valid
Notification delivery = failed
```

Business transaction tidak boleh di-rollback.

---

## 27.2 Invalid Token

Jika token diketahui invalid:

```text
Invalid token
    ↓
Mark inactive/remove according to token policy
    ↓
Do not repeatedly send to invalid token
```

---

## 27.3 Temporary FCM Failure

Backend dapat melakukan retry melalui job/retry mechanism.

```text
Business event committed
       ↓
Notification job
       ↓
FCM temporary failure
       ↓
Retry
```

Retry tidak boleh menghasilkan duplicate notification business yang tidak diperlukan.

---

## 27.4 Android Offline

Jika Android offline:

```text
No immediate push delivery
```

Ketika aplikasi tersedia:

```text
Open app
 ↓
Fetch current state
 ↓
Render authoritative data
```

Notification delivery tidak menggantikan data synchronization.

---

# 28. Idempotency

Notification harus memiliki identitas event yang dapat digunakan untuk mencegah duplicate notification.

Conceptual identity:

```text
event_type
+
resource_type
+
resource_id
+
recipient_user_id
```

Contoh:

```text
PAYMENT_QRIS_APPROVED
:
PAYMENT
:
7001
:
USER-10
```

Event yang sama untuk resource dan recipient yang sama tidak boleh menghasilkan duplicate notification tanpa alasan yang jelas.

Jika satu event memang harus dikirim ulang karena delivery failure, retry harus mempertahankan event identity yang sama.

---

# 29. Notification Read State

Notification dapat memiliki:

```text
UNREAD
READ
```

State read hanya berkaitan dengan notification UI.

```text
Notification read
≠
Payment paid
```

```text
Notification unread
≠
Payment pending
```

```text
Notification deleted/read
≠
Order deleted
```

Business state tetap berada pada backend domain resource.

---

# 30. Notification vs Business State

## Wrong

```text
FCM:
payment_status = PAID

Android:
localPaymentStatus = PAID
```

## Correct

```text
FCM:
PAYMENT_QRIS_APPROVED

Android:
open Payment Screen
        ↓
GET /payment
        ↓
Backend
        ↓
payment_status = PAID
```

Hal yang sama berlaku untuk order:

```text
FCM ORDER_COMPLETED
        ↓
GET latest order
        ↓
Backend says SELESAI
        ↓
UI shows SELESAI
```

---

# 31. Notification and Payment Sequence

## 31.1 QRIS Upload

```text
Customer
   ↓
Upload QRIS proof
   ↓
Laravel validates
   ↓
Store proof
   ↓
Payment = WAITING_VERIFICATION
   ↓
COMMIT
   ↓
PAYMENT_QRIS_PROOF_UPLOADED
   ↓
Notification Record
   ↓
FCM
   ↓
Owner
```

---

## 31.2 QRIS Approval

```text
Owner
   ↓
Open payment verification
   ↓
Approve
   ↓
Laravel validates
   ↓
Payment = PAID
   ↓
COMMIT
   ↓
PAYMENT_QRIS_APPROVED
   ↓
Notification Record
   ↓
FCM
   ↓
Customer
```

---

## 31.3 QRIS Rejection

```text
Owner
   ↓
Reject proof
   ↓
Laravel validates
   ↓
Payment = PENDING
   ↓
COMMIT
   ↓
PAYMENT_QRIS_REJECTED
   ↓
Notification Record
   ↓
FCM
   ↓
Customer
   ↓
Upload new proof
```

---

## 31.4 CASH Confirmation

```text
Customer pays Courier
   ↓
Courier selects "Uang Diterima"
   ↓
Laravel validates assignment
   ↓
Payment = PAID
   ↓
COMMIT
   ↓
PAYMENT_CASH_CONFIRMED
   ↓
Notification Record
   ↓
FCM
   ↓
Customer
```

---

# 32. Notification and Order Sequence

## 32.1 Create Order

```text
Customer
   ↓
Create Order
   ↓
Laravel validation
   ↓
Order committed
   ↓
ORDER_CREATED
   ↓
Owner + Customer notification
```

---

## 32.2 Process Order

```text
Owner
   ↓
Process Order
   ↓
Backend validates transition
   ↓
Order = DIPROSES
   ↓
COMMIT
   ↓
ORDER_PROCESSED
   ↓
Customer notification
```

---

## 32.3 Assign Courier

```text
Owner
   ↓
Assign Courier
   ↓
Assignment committed
   ↓
COURIER_ASSIGNED
   ↓
Customer + Assigned Courier notification
```

---

# 33. Notification and Delivery Sequence

```text
Courier
   ↓
Start Delivery
   ↓
Order = DALAM_PENGANTARAN
   ↓
COMMIT
   ↓
DELIVERY_STARTED
   ↓
Customer notification
```

Completion:

```text
Courier
   ↓
Complete Delivery
   ↓
Order = SELESAI
   ↓
COMMIT
   ↓
ORDER_COMPLETED
   ↓
Customer + Owner + Courier notification
```

---

# 34. Notification and Tracking Sequence

Tracking notification bukan location streaming.

```text
Delivery active
      ↓
Backend receives valid location
      ↓
Tracking becomes available
      ↓
TRACKING_AVAILABLE
      ↓
Customer notification
      ↓
Customer opens tracking
      ↓
Authorized tracking API
      ↓
Latest courier location
```

Tidak mengirim notification untuk setiap GPS coordinate.

---

# 35. Security Requirements

## 35.1 Server-side Authorization

Sebelum notification dikirim:

```text
event
 ↓
resource
 ↓
recipient
 ↓
role check
 ↓
ownership/assignment check
 ↓
send
```

---

## 35.2 Sensitive Data

Jangan memasukkan:

```text
password
Sanctum token
Firebase server credential
private QRIS proof URL tanpa authorization
full customer PII
internal database record
raw authentication data
```

ke dalam notification payload.

---

## 35.3 Payment Security

Notification tidak boleh menjadi bukti pembayaran.

QRIS:

```text
Proof
 ↓
Owner verification
 ↓
Database state
 ↓
Notification
```

Bukan:

```text
Notification
 ↓
Payment PAID
```

Cash:

```text
Courier confirmation
 ↓
Backend validation
 ↓
Database state
 ↓
Notification
```

---

## 35.4 Tracking Privacy

Jangan mengirim latitude/longitude courier dalam push notification kecuali ada requirement eksplisit yang benar-benar membutuhkannya.

Normal pattern:

```text
Notification:
"Lokasi pengantar tersedia."

Then:
Customer → authorized tracking API → current location
```

---

# 36. Notification API Integration

Notification API mengikuti `06_KYUSUI_API_SPECIFICATION_REBUILT.md`.

Conceptual endpoints:

```http
POST /notifications/device-token
GET  /notifications
PATCH /notifications/{notification}/read
```

Payment notification tidak memerlukan endpoint FCM khusus.

Payment action tetap menggunakan payment API:

```text
Customer upload proof
Owner approve/reject
Courier confirm cash
```

Setelah business action berhasil, backend notification service menghasilkan event notification.

---

# 37. Separation of Responsibilities

## Android

Android bertanggung jawab atas:

```text
FCM token registration
FCM message reception
notification display
notification tap handling
deep-link navigation
API refresh
UI rendering
read state interaction
```

Android tidak bertanggung jawab atas:

```text
payment authorization
payment verification
payment state transition
order authorization
courier assignment authority
business event authority
```

---

## Backend

Backend bertanggung jawab atas:

```text
authentication
authorization
business transition
payment state
order state
notification event creation
recipient determination
notification persistence
FCM dispatch
idempotency
token association
```

---

## MySQL

MySQL menyimpan authoritative persistent state:

```text
orders
payments
courier_assignments
courier_locations
notifications
```

FCM tidak menggantikan persistent state tersebut.

---

# 38. Notification Testing

Minimal notification test suite:

## NOTIF-001 — Order Created

Expected:

```text
Order berhasil committed.
Owner menerima notification.
Customer menerima confirmation notification.
```

## NOTIF-002 — Order Processed

Expected:

```text
Customer menerima notification setelah order committed sebagai DIPROSES.
```

## NOTIF-003 — QRIS Proof Uploaded

Expected:

```text
Customer upload berhasil.
Payment menjadi WAITING_VERIFICATION.
Owner menerima notification.
```

## NOTIF-004 — QRIS Approved

Expected:

```text
Owner approve berhasil.
Payment menjadi PAID.
Customer menerima notification.
```

## NOTIF-005 — QRIS Rejected

Expected:

```text
Owner reject berhasil.
Payment kembali PENDING.
Customer menerima notification.
Customer dapat upload proof baru.
```

## NOTIF-006 — Cash Confirmed

Expected:

```text
Assigned Courier confirm berhasil.
Payment menjadi PAID.
Customer menerima notification.
```

## NOTIF-007 — Unauthorized Recipient

Expected:

```text
User yang tidak memiliki ownership/context
tidak menerima notification untuk resource tersebut.
```

## NOTIF-008 — Courier Assignment

Expected:

```text
Customer dan Assigned Courier menerima notification yang relevan.
```

## NOTIF-009 — Delivery Started

Expected:

```text
Customer menerima notification.
```

## NOTIF-010 — Tracking Available

Expected:

```text
Customer menerima notification hanya setelah valid tracking context tersedia.
```

## NOTIF-011 — Order Completed

Expected:

```text
Customer, Owner, dan Courier menerima notification sesuai authorization.
```

## NOTIF-012 — Foreground

Expected:

```text
Tidak ada duplicate UX yang membingungkan.
Current screen dapat refresh.
```

## NOTIF-013 — Background

Expected:

```text
System notification tampil.
Tap membuka resource yang benar.
```

## NOTIF-014 — Cold Start

Expected:

```text
Session diperiksa.
Role resolved.
Deep link resolved.
Resource terbaru diambil dari API.
```

## NOTIF-015 — Invalid Session

Expected:

```text
User diarahkan ke authentication.
Protected resource tidak dibuka sebelum authentication valid.
```

## NOTIF-016 — Invalid Resource Ownership

Expected:

```text
Backend menolak resource meskipun resource_id terdapat di payload.
```

## NOTIF-017 — Duplicate Event

Expected:

```text
Tidak ada duplicate notification yang tidak diperlukan.
```

## NOTIF-018 — Invalid FCM Token

Expected:

```text
Token invalid ditangani tanpa mengubah business state.
```

## NOTIF-019 — FCM Failure

Expected:

```text
Payment/order tetap berhasil.
Notification failure tidak rollback business transaction.
```

## NOTIF-020 — Payment State Authority

Expected:

```text
FCM payload tidak dapat membuat Android menetapkan PAID.
Android mengambil state terbaru dari API.
```

---

# 39. Payment Notification Acceptance Criteria

Sistem dianggap memenuhi notification payment contract apabila:

```text
[✓] QRIS proof upload menghasilkan Owner notification
[✓] QRIS approval menghasilkan Customer notification
[✓] QRIS rejection menghasilkan Customer notification
[✓] QRIS rejection kembali ke PENDING
[✓] CASH confirmation menghasilkan Customer notification
[✓] CASH confirmation dilakukan Assigned Courier
[✓] Customer tidak dapat confirm CASH untuk dirinya sendiri
[✓] Owner tidak menjadi actor CASH confirmation
[✓] QRIS approval hanya dari Owner
[✓] Customer tidak dapat menetapkan PAID
[✓] Notification bukan source of truth
[✓] FCM hanya transport
[✓] Backend/MySQL tetap source of truth
[✓] Notification dibuat setelah business commit
[✓] FCM failure tidak rollback business transaction
[✓] Notification payload tidak mengandung secret
[✓] Notification tidak membawa payment proof private secara langsung
[✓] Notification tidak membawa koordinat tracking yang tidak diperlukan
```

---

# 40. Non-Payment Notification Acceptance Criteria

Notification non-payment baseline tetap dipertahankan:

```text
[✓] ORDER_CREATED
[✓] ORDER_PROCESSED
[✓] COURIER_ASSIGNED
[✓] DELIVERY_STARTED
[✓] TRACKING_AVAILABLE
[✓] ORDER_COMPLETED
```

Behavior, recipient, authorization, deep link, dan API refresh tetap mengikuti workflow masing-masing.

---

# 41. Removed Legacy Payment Notification Concepts

Payment notification architecture aktif tidak memiliki event yang berasal dari payment provider.

Tidak ada:

```text
Provider payment event
Provider webhook trigger
Automatic provider payment event
Provider callback notification
Provider transaction notification
```

Tidak ada dependency notification terhadap external payment provider.

Payment notification sekarang hanya berasal dari internal KYŪSUI business event:

```text
PAYMENT_QRIS_PROOF_UPLOADED
PAYMENT_QRIS_APPROVED
PAYMENT_QRIS_REJECTED
PAYMENT_CASH_CONFIRMED
```

---

# 42. Final Architecture Summary

```text
                         KYŪSUI
                            │
                            ▼
                    Laravel 13 Backend
                            │
              ┌─────────────┴─────────────┐
              ▼                           ▼
        Business State              Notification Event
              │                           │
              ▼                           ▼
            MySQL                 Notification Record
                                          │
                                          ▼
                                Notification Service
                                          │
                                          ▼
                                         FCM
                                          │
                                          ▼
                                   Android Device
                                          │
                              ┌───────────┴───────────┐
                              ▼                       ▼
                         Notification             Deep Link
                                                      │
                                                      ▼
                                               Laravel API
                                                      │
                                                      ▼
                                          Authoritative UI State
```

---

# 43. Final Payment Notification Flow

## QRIS

```text
Customer uploads proof
        ↓
Backend validates
        ↓
Payment = WAITING_VERIFICATION
        ↓
COMMIT
        ↓
PAYMENT_QRIS_PROOF_UPLOADED
        ↓
FCM
        ↓
Owner notified
```

```text
Owner approves
        ↓
Payment = PAID
        ↓
COMMIT
        ↓
PAYMENT_QRIS_APPROVED
        ↓
FCM
        ↓
Customer notified
```

```text
Owner rejects
        ↓
Payment = PENDING
        ↓
COMMIT
        ↓
PAYMENT_QRIS_REJECTED
        ↓
FCM
        ↓
Customer notified
        ↓
Customer uploads new proof
```

## CASH

```text
Customer pays Courier
        ↓
Assigned Courier confirms
        ↓
Backend validates assignment
        ↓
Payment = PAID
        ↓
COMMIT
        ↓
PAYMENT_CASH_CONFIRMED
        ↓
FCM
        ↓
Customer notified
```

---

# 44. Final Notification Rules

1. FCM hanya transport.
2. Laravel adalah business authority.
3. MySQL menyimpan authoritative state.
4. Notification bukan source of truth.
5. Notification dibuat setelah business transaction berhasil commit.
6. Payment notification berasal dari internal KYŪSUI business event.
7. QRIS proof upload memberi notification kepada Owner.
8. QRIS approval memberi notification kepada Customer.
9. QRIS rejection memberi notification kepada Customer.
10. QRIS rejection mengembalikan payment ke `PENDING`.
11. Cash confirmation dilakukan Assigned Courier.
12. Cash confirmation memberi notification kepada Customer.
13. Cash confirmation mengubah payment dari `PENDING` menjadi `PAID`.
14. Customer tidak dapat menetapkan `PAID`.
15. Owner tidak mengonfirmasi CASH.
16. Courier tidak memverifikasi QRIS.
17. Notification payload hanya membawa routing/context minimum.
18. Android wajib refresh API untuk memperoleh authoritative state.
19. FCM failure tidak membatalkan business transaction.
20. Invalid FCM token tidak boleh merusak business state.
21. Duplicate event harus ditangani secara idempotent.
22. Notification authorization harus server-side.
23. Tracking notification tidak mengirim lokasi sensitif yang tidak diperlukan.
24. Notification non-payment tetap menggunakan baseline order, delivery, dan tracking events.
25. Tidak ada dependency notification terhadap payment provider.

---

# 45. Definition of Notification Architecture Done

```text
[✓] FCM transport defined
[✓] Backend source-of-truth boundary defined
[✓] Notification persistence defined
[✓] Notification lifecycle defined
[✓] Recipient matrix defined
[✓] Authorization rules defined
[✓] Common payload defined
[✓] Foreground behavior defined
[✓] Background behavior defined
[✓] Cold-start behavior defined
[✓] Deep-link behavior defined
[✓] FCM token lifecycle defined
[✓] Token security defined
[✓] Failure handling defined
[✓] Idempotency defined
[✓] Order notifications preserved
[✓] Delivery notifications preserved
[✓] Tracking notifications preserved
[✓] QRIS proof notification defined
[✓] QRIS approval notification defined
[✓] QRIS rejection notification defined
[✓] CASH confirmation notification defined
[✓] Payment notification authority defined
[✓] Legacy provider notification architecture removed
[✓] Payment notification testing defined
[✓] Acceptance criteria defined
```

---

# 46. Final Status

```text
Document:
10_KYUSUI_NOTIFICATION_SPECIFICATION.md

Status:
REBUILT — PAYMENT NOTIFICATION ARCHITECTURE SYNCHRONIZED

Platform:
Android Native

Notification Transport:
Firebase Cloud Messaging

Backend:
Laravel 13 / PHP 8.3+

Database:
MySQL 8.x

Business Authority:
Laravel + MySQL

Payment:
QRIS + CASH

QRIS Notification Flow:
Proof Upload
→ Owner Notification
→ Owner Approve / Reject
→ Customer Notification

CASH Notification Flow:
Courier Confirms Cash
→ Customer Notification

Canonical Payment Status:
PENDING
WAITING_VERIFICATION
PAID

Non-Payment Notifications:
ORDER_CREATED
ORDER_PROCESSED
COURIER_ASSIGNED
DELIVERY_STARTED
TRACKING_AVAILABLE
ORDER_COMPLETED

Source of Truth:
Backend / MySQL

FCM Role:
Notification Transport Only
```

---

# 47. Source Documents

Dokumen ini disusun dan diselaraskan terhadap:

```text
04_KYUSUI_SYSTEM_WORKFLOW.md
04_KYUSUI_SYSTEM_WORKFLOW_REBUILT.md

06_KYUSUI_API_SPECIFICATION.md
06_KYUSUI_API_SPECIFICATION_REBUILT.md

08_KYUSUI_PAYMENT_SPECIFICATION.md
08_KYUSUI_PAYMENT_SPECIFICATION_REBUILT.md

05_KYUSUI_DATABASE_SCHEMA_REBUILT.md
07_KYUSUI_ANDROID_ARCHITECTURE_REBUILT.md
09_KYUSUI_TRACKING_SPECIFICATION.md
```

Dokumen ini tidak mengubah source code implementasi.

Jika terdapat dokumen lama yang masih memuat payment notification berbasis provider, dokumen tersebut harus diselaraskan terhadap payment architecture terbaru sebelum implementasi final.
