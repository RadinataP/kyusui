# KYŪSUI — API SPECIFICATION

**Project:** KYŪSUI  
**Study Case:** Berkah Water  
**Document:** `06_KYUSUI_API_SPECIFICATION.md`  
**Status:** REBUILT — PAYMENT ARCHITECTURE SYNCHRONIZED  
**Backend:** Laravel 13 / PHP 8.3+  
**API Style:** REST / JSON / HTTPS  
**Authentication:** Laravel Sanctum  
**Android Client:** Kotlin + Jetpack Compose + Retrofit + OkHttp  
**Database:** MySQL 8.x  
**Payment:** QRIS + CASH  
**Notification:** Firebase Cloud Messaging (FCM)

---

# 1. Purpose

Dokumen ini mendefinisikan kontrak REST API resmi KYŪSUI sebagai batas komunikasi antara aplikasi Android dan backend Laravel.

API contract menjadi acuan untuk:

- Laravel routes;
- middleware;
- Sanctum authentication;
- Form Request validation;
- Policies / authorization;
- controllers;
- application/domain services;
- API Resources;
- database interaction;
- Android Retrofit interfaces;
- Android repositories;
- ViewModel state mapping;
- error handling;
- payment workflow;
- tracking authorization;
- notification integration.

Dokumen ini tidak berisi source code implementasi.

API tidak boleh mengubah business requirement yang telah dikunci pada dokumen authority yang lebih tinggi.

---

# 2. Source Authority

API ini diselaraskan dengan:

```text
00_KYUSUI_MASTER_SPECIFICATION.md
01_KYUSUI_PROJECT_RULES.md
02_KYUSUI_SYSTEM_ARCHITECTURE.md
04_KYUSUI_SYSTEM_WORKFLOW_REBUILT.md
05_KYUSUI_DATABASE_SCHEMA_REBUILT.md
08_KYUSUI_PAYMENT_SPECIFICATION_REBUILT.md
13_KYUSUI_DATABASE_FINALIZATION.md
```

Untuk domain payment, keputusan terbaru yang wajib digunakan adalah:

```text
QRIS
CASH
```

dengan status:

```text
PENDING
WAITING_VERIFICATION
PAID
```

Payment provider, payment gateway, provider transaction, provider webhook, dynamic QRIS, dan `payment_transactions` bukan bagian dari active architecture.

---

# 3. API Architectural Principles

## 3.1 Backend Is the Authority

Alur utama:

```text
Android
   ↓
Retrofit + OkHttp
   ↓
HTTPS / REST / JSON
   ↓
Laravel API
   ↓
Authentication
   ↓
Validation
   ↓
Authorization
   ↓
Business Rules
   ↓
MySQL
```

Android tidak boleh menjadi source of truth untuk:

- payment status;
- order status;
- ownership;
- courier assignment;
- payment verification;
- QRIS configuration;
- nominal transaksi.

## 3.2 UI Is Not a Security Boundary

Role-based UI hanya membantu navigasi.

Backend tetap harus memvalidasi:

```text
authentication
role
ownership
assignment
resource state
business transition
actor authorization
```

Menyembunyikan tombol atau screen pada Android bukan authorization.

## 3.3 Server-Controlled State Transition

Client hanya mengirim intent/action yang diizinkan.

Client tidak boleh mengirim arbitrary final state seperti:

```json
{
  "payment_status": "PAID"
}
```

Backend menentukan apakah transition valid berdasarkan current state dan actor.

---

# 4. Base API Contract

## 4.1 Base URL

Production:

```text
https://<api-domain>/api/v1
```

Development:

```text
http://<development-host>/api/v1
```

Domain bukan bagian dari business requirement dan dikonfigurasi per environment.

## 4.2 Transport

```text
HTTPS
JSON
UTF-8
```

Production wajib menggunakan HTTPS.

## 4.3 Request Headers

Authenticated request:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <sanctum-token>
```

Multipart upload:

```http
Accept: application/json
Authorization: Bearer <sanctum-token>
Content-Type: multipart/form-data
```

Public authentication request:

```http
Accept: application/json
Content-Type: application/json
```

Tidak ada endpoint payment provider/webhook yang dipanggil Android.

## 4.4 Response Envelope

Single resource:

```json
{
  "data": {},
  "message": "Success."
}
```

Collection:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 0
  },
  "message": "Success."
}
```

Validation error:

```json
{
  "message": "Validation failed.",
  "errors": {
    "field": [
      "The field is invalid."
    ]
  }
}
```

General error:

```json
{
  "message": "Request could not be processed."
}
```

## 4.5 HTTP Status Convention

| Status | Meaning |
|---|---|
| `200` | Read/update/action berhasil |
| `201` | Resource berhasil dibuat |
| `204` | Action berhasil tanpa response body |
| `400` | Malformed request |
| `401` | Authentication gagal/tidak ada |
| `403` | Authenticated tetapi tidak berwenang |
| `404` | Resource tidak ditemukan/tidak terlihat |
| `409` | Business state conflict / duplicate operation |
| `422` | Validation gagal |
| `429` | Rate limit |
| `500` | Internal server error |
| `503` | Service/dependency unavailable |

API tidak menggunakan `200` untuk seluruh kondisi error.

---

# 5. Authentication and Authorization

## 5.1 Roles

```text
CUSTOMER
OWNER
COURIER
```

Tidak membuat role baru hanya untuk kebutuhan UI.

## 5.2 Sanctum

Semua endpoint private menggunakan:

```http
Authorization: Bearer <sanctum-token>
```

Token:

- dibuat backend;
- disimpan aman di Android;
- tidak di-hardcode;
- tidak dicetak pada production log;
- tidak dikirim ke endpoint public;
- tidak diberikan kepada user lain.

## 5.3 Customer Authorization

Customer hanya dapat:

- membaca profile sendiri;
- mengubah profile sendiri;
- membaca product catalog sesuai access policy;
- membuat order atas dirinya;
- melihat order miliknya;
- memilih payment untuk order miliknya;
- melihat payment order miliknya;
- melihat payment history miliknya;
- melihat active QRIS;
- mengunggah proof QRIS untuk order miliknya;
- melihat tracking order miliknya;
- membaca notification miliknya.

## 5.4 Owner Authorization

Owner hanya dapat melakukan operasi dalam scope Berkah Water.

Owner dapat:

- melihat incoming orders;
- melihat detail order;
- melihat payment QRIS yang membutuhkan verifikasi;
- melihat payment proof;
- approve QRIS proof;
- reject QRIS proof;
- melihat active QRIS;
- mengganti active QRIS;
- melakukan order processing;
- membuat courier assignment;
- mengubah order status sesuai policy.

Owner tidak dapat mengakses atau memodifikasi resource yang berada di luar operational scope.

## 5.5 Courier Authorization

Courier hanya dapat:

- melihat assignment miliknya;
- melihat order yang ditugaskan kepadanya;
- memperbarui delivery status sesuai transition;
- mengirim location untuk assignment aktif miliknya;
- melihat payment CASH pada order yang ditugaskan;
- mengonfirmasi `Uang Diterima` hanya untuk payment CASH milik assignment aktifnya.

Courier tidak boleh:

- mengonfirmasi payment order courier lain;
- mengonfirmasi QRIS;
- mengubah QRIS;
- mengakses payment customer lain tanpa delivery context yang sah.

## 5.6 Ownership and Resource Hiding

Untuk resource yang tidak boleh diketahui keberadaannya oleh caller, backend dapat mengembalikan `404`.

Contoh:

```text
Customer A mencoba membuka Order Customer B
        ↓
404
```

Tujuannya mencegah resource enumeration.

---

# 6. Common Domain Values

## 6.1 Order Status

Canonical backend values:

```text
MENUNGGU_PEMBAYARAN
MENUNGGU_DIPROSES
DIPROSES
DITUGASKAN
DALAM_PENGANTARAN
SELESAI
```

Client tidak boleh mengirim arbitrary order status.

## 6.2 Payment Method

Hanya:

```text
QRIS
CASH
```

Tidak digunakan:

```text
MIDTRANS
DIGITAL
PAYMENT_GATEWAY
TRANSFER
CARD
EWALLET
```

## 6.3 Payment Status

Hanya:

```text
PENDING
WAITING_VERIFICATION
PAID
```

Tidak digunakan:

```text
PROCESSING
CONFIRMED
FAILED
EXPIRED
REJECTED
```

## 6.4 QRIS State Transition

```text
PENDING
   ↓
Customer uploads proof
   ↓
WAITING_VERIFICATION
   ↓
Owner approves
   ↓
PAID
```

Reject:

```text
WAITING_VERIFICATION
   ↓
Owner rejects
   ↓
PENDING
```

## 6.5 CASH State Transition

```text
PENDING
   ↓
Customer pays Courier
   ↓
Assigned Courier confirms "Uang Diterima"
   ↓
Backend validates assignment
   ↓
PAID
```

CASH tidak pernah masuk:

```text
WAITING_VERIFICATION
```

## 6.6 Courier Assignment Status

```text
ASSIGNED
ACTIVE
COMPLETED
```

Courier rejection belum menjadi requirement.

---

# 7. Resource Representation

## 7.1 User Resource

```json
{
  "id": 1,
  "name": "Budi",
  "email": "budi@example.com",
  "phone": "081234567890",
  "role": {
    "name": "CUSTOMER",
    "display_name": "Pelanggan"
  },
  "status": "ACTIVE"
}
```

Password tidak pernah dikembalikan.

## 7.2 Customer Profile

```json
{
  "id": 10,
  "user_id": 1,
  "name": "Budi",
  "phone": "081234567890",
  "email": "budi@example.com",
  "default_address": "Alamat customer"
}
```

## 7.3 Product Resource

```json
{
  "id": 1,
  "name": "Air Galon",
  "description": "Air galon",
  "price": "8000.00",
  "is_available": true
}
```

## 7.4 Order Resource

```json
{
  "id": 1001,
  "order_number": "ORD-20260930-0001",
  "customer_id": 10,
  "items": [
    {
      "product_id": 1,
      "product_name": "Air Galon",
      "quantity": 2,
      "unit_price": "8000.00",
      "line_total": "16000.00"
    }
  ],
  "subtotal_amount": "16000.00",
  "delivery_fee": "0.00",
  "total_amount": "16000.00",
  "order_status": "MENUNGGU_DIPROSES",
  "payment": {
    "id": 7001,
    "payment_method": "CASH",
    "payment_status": "PENDING",
    "amount": "16000.00"
  },
  "assignment": null
}
```

## 7.5 Payment Resource

```json
{
  "id": 7001,
  "order_id": 1001,
  "payment_method": "QRIS",
  "payment_status": "WAITING_VERIFICATION",
  "amount": "16000.00",
  "proof": {
    "available": true
  },
  "verified_by": null,
  "verified_at": null,
  "created_at": "2026-09-30T10:00:00Z",
  "updated_at": "2026-09-30T10:05:00Z"
}
```

**Format `verified_by` dan `verified_at`:**
- `verified_by`: **Flat integer** (user ID), **bukan** nested object. Contoh: `5` (Owner) atau `20` (Courier). `null` bila payment belum `PAID`.
- `verified_at`: **ISO 8601 timestamp string** (server time). Contoh: `"2026-09-30T10:15:00Z"`. `null` bila payment belum `PAID`.

Provider fields tidak pernah dikembalikan.

Tidak ada:

```text
provider_name
provider_reference
transaction_id
payment_url
webhook_status
```

## 7.6 Active QRIS Resource

```json
{
  "qris_image": "/storage/qris/active-qris.png",
  "updated_at": "2026-09-30T09:00:00Z"
}
```

Jika file storage bersifat private, response dapat menggunakan authorized temporary URL atau mekanisme private file delivery yang ditentukan implementasi backend.

## 7.7 Tracking Resource

Endpoint: `GET /customer/orders/{order}/tracking` (section 9.7).

Response mengembalikan assignment aktif beserta riwayat lokasi (maksimal 10 titik, terbaru dulu).

```json
{
  "id": 501,
  "assignment_id": 501,
  "order_id": 1001,
  "courier_id": 20,
  "status": "ACTIVE",
  "assigned_at": "2026-09-30T08:00:00Z",
  "started_at": "2026-09-30T09:00:00Z",
  "completed_at": null,
  "courier": {
    "id": 20,
    "name": "Andi"
  },
  "locations": [
    {
      "latitude": -0.9480,
      "longitude": 100.4180,
      "accuracy_meters": 8.50,
      "recorded_at": "2026-09-30T09:15:20Z"
    },
    {
      "latitude": -0.9475,
      "longitude": 100.4178,
      "accuracy_meters": 9.20,
      "recorded_at": "2026-09-30T09:10:15Z"
    }
  ],
  "location": {
    "latitude": -0.9480,
    "longitude": 100.4180,
    "accuracy_meters": 8.50,
    "recorded_at": "2026-09-30T09:15:20Z"
  }
}
```

### Field Definitions

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| `id` | integer | No | Assignment ID (kanonik) |
| `assignment_id` | integer | No | Alias untuk `id` (kompatibilitas mundur) |
| `order_id` | integer | No | Order ID |
| `courier_id` | integer | No | Courier ID |
| `status` | string | No | Assignment status (`ASSIGNED`, `ACTIVE`, `COMPLETED`) |
| `assigned_at` | timestamp | Yes | Waktu assignment dibuat |
| `started_at` | timestamp | Yes | Waktu delivery dimulai |
| `completed_at` | timestamp | Yes | Waktu delivery selesai |
| `courier` | object | Yes | `{id, name}` courier yang ditugaskan |
| `locations` | array | No | Riwayat lokasi (terbaru dulu, maksimal 10) |
| `location` | object | Yes | Alias untuk `locations[0]` (kompatibilitas spec 7.7 lama) |

### Location Item Fields

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| `latitude` | decimal | No | Latitude (derajat) |
| `longitude` | decimal | No | Longitude (derajat) |
| `accuracy_meters` | decimal | Yes | Akurasi GPS (meter) |
| `recorded_at` | timestamp | No | Waktu rekaman (ISO 8601) |

### Behavior

- Jika tidak ada assignment aktif: response `200 OK` dengan `"data": null` dan pesan `"Tracking location is not available yet."`
- Jika assignment aktif tapi courier belum mengirim lokasi: `locations` kosong array `[]`, `location: null`
- `locations` diurutkan `recorded_at` descending (terbaru dulu), dibatasi maksimal 10 item
- `location` field adalah alias untuk `locations[0]` untuk kompatibilitas mundur dengan klien yang mengikuti spec 7.7 lama

---

# 8. AUTH API

## 8.1 Register Customer

### Endpoint

```http
POST /auth/register
```

### Authentication

Public.

### Role

Anonymous customer registration.

### Request

```json
{
  "name": "Budi",
  "phone": "081234567890",
  "email": "budi@example.com",
  "password": "secret-password",
  "password_confirmation": "secret-password"
}
```

### Validation

Backend wajib memvalidasi:

- required fields sesuai final account policy;
- name;
- phone uniqueness;
- email is required, well-formed, unique, dan maksimal 255 karakter;
- password strength;
- password confirmation.

`email` wajib, bukan opsional. Android mengirim field `email` pada setiap request
dan menolak email kosong atau salah bentuk sebelum memanggil backend.

### Authorization

Tidak membutuhkan Sanctum.

### Success

`201 Created`.

```json
{
  "data": {
    "user": {},
    "token": "sanctum-token"
  },
  "message": "Registration successful."
}
```

### Errors

- `422` validation;
- `409` duplicate identity jika implementation menggunakan conflict response;
- `500` internal error.

---

## 8.2 Login

### Endpoint

```http
POST /auth/login
```

### Authentication

Public.

### Request

```json
{
  "email": "budi@example.com",
  "password": "secret-password"
}
```

Identitas login adalah `email`. Nomor telepon bukan field login dan tidak
dikirim pada request ini; field `login` juga tidak ada di contract.

### Validation

Backend wajib memvalidasi credential dan account status. Aturan `email` pada
backend adalah `required`, format email, dan `max:255`, sehingga email kosong
atau salah bentuk ditolak dengan `422` sebelum kredensial diperiksa.

Android memvalidasi bentuk email secara lokal untuk menghindari perjalanan yang
pasti ditolak, tetapi backend tetap memvalidasi ulang.

### Success

`200 OK`.

```json
{
  "data": {
    "user": {},
    "token": "sanctum-token"
  },
  "message": "Login successful."
}
```

### Errors

- `422` format `email` atau `password` tidak valid;
- `401` untuk credential tidak valid.

---

## 8.3 Logout

### Endpoint

```http
POST /auth/logout
```

### Authentication

Sanctum.

### Success

`204 No Content`.

---

## 8.4 Current User

### Endpoint

```http
GET /auth/me
```

### Authentication

Sanctum.

### Success

`200 OK`.

User Resource dibungkus satu level di bawah `data`, bukan langsung di `data`:

```json
{
  "data": {
    "user": {}
  },
  "message": "Data pengguna berhasil dimuat."
}
```

Android membaca `response.data.user`. Bentuk `{"data": {...user langsung...}}`
tidak pernah dikirim untuk endpoint ini.

---

# 9. CUSTOMER API

## 9.1 Get Customer Profile

### Endpoint

```http
GET /customer/profile
```

### Role

```text
CUSTOMER
```

### Authorization

Hanya profile authenticated customer.

### Success

`200 OK`.

---

## 9.2 Update Customer Profile

### Endpoint

```http
PUT /customer/profile
```

### Role

```text
CUSTOMER
```

### Request

```json
{
  "name": "Budi Updated",
  "phone": "081234567890",
  "email": "budi@example.com",
  "default_address": "Alamat baru"
}
```

### Validation

Backend memvalidasi field yang diperbolehkan dan uniqueness identifier.

### Success

`200 OK`.

---

## 9.3 List Products

### Endpoint

```http
GET /products
```

### Role

```text
CUSTOMER
```

### Query

```text
?page=1&per_page=20
```

Optional search mengikuti implementation contract existing.

### Authorization

Customer hanya menerima product yang boleh ditampilkan oleh catalog policy.

### Success

`200 OK`.

---

## 9.4 Create Order

### Endpoint

```http
POST /customer/orders
```

### Role

```text
CUSTOMER
```

### Request

```json
{
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    }
  ],
  "delivery_location": {
    "latitude": -0.9471,
    "longitude": 100.4172,
    "address": "Alamat pengantaran"
  }
}
```

### Validation

Backend wajib:

- memvalidasi customer;
- memvalidasi product;
- memastikan product tersedia;
- memvalidasi quantity;
- menghitung unit price dari server;
- menghitung subtotal dari server;
- menghitung delivery fee sesuai business rule;
- menghitung total dari server;
- tidak mempercayai total dari client;
- membuat order dan item secara transactional.

### Initial Order State

```text
MENUNGGU_PEMBAYARAN
```

### Success

`201 Created`.

Order response harus menyertakan payment state jika payment record dibuat dalam transaction order sesuai implementation contract.

---

## 9.5 Customer Order History / List

### Endpoint

```http
GET /customer/orders
```

### Role

```text
CUSTOMER
```

### Query

```text
?page=1&per_page=20
```

### Authorization

Hanya order authenticated customer.

### Success

`200 OK`.

History berasal dari `orders` dan status history bila lifecycle detail diperlukan. Tidak ada tabel `order_history`.

---

## 9.6 Customer Order Detail

### Endpoint

```http
GET /customer/orders/{order}
```

### Role

```text
CUSTOMER
```

### Authorization

Order harus dimiliki authenticated customer.

### Success

`200 OK`.

---

## 9.7 Customer Tracking

### Endpoint

```http
GET /customer/orders/{order}/tracking
```

### Role

```text
CUSTOMER
```

### Authorization

Backend memvalidasi:

- order ownership;
- active delivery context;
- assignment yang valid.

### Success

`200 OK`.

Jika location belum tersedia:

```json
{
  "data": {
    "order_id": 1001,
    "assignment_id": 501,
    "status": "ACTIVE",
    "courier": {
      "id": 20,
      "name": "Andi"
    },
    "location": null
  },
  "message": "Tracking location is not available yet."
}
```

---

# 10. OWNER API

## 10.1 List Orders

### Endpoint

```http
GET /owner/orders
```

### Role

```text
OWNER
```

### Query

```text
?page=1&per_page=20
```

Filter status/payment dapat digunakan jika telah tersedia pada existing implementation.

### Authorization

Owner hanya melihat order dalam operational scope Berkah Water.

---

## 10.2 Owner Order Detail

### Endpoint

```http
GET /owner/orders/{order}
```

### Role

```text
OWNER
```

### Authorization

Order harus berada dalam scope owner.

### Success

`200 OK`.

Response dapat menyertakan:

- customer context yang diperlukan;
- items;
- amount;
- order status;
- payment status;
- assignment;
- timestamps.

---

## 10.3 Owner Order Status

### Endpoint

```http
PATCH /owner/orders/{order}/status
```

### Role

```text
OWNER
```

### Request

```json
{
  "status": "DIPROSES",
  "note": "Pesanan mulai diproses."
}
```

### Validation

- status harus valid next state;
- transition harus diizinkan untuk Owner;
- note optional;
- server tidak menerima arbitrary transition.

### Authorization

Owner hanya dapat mengubah transition yang menjadi responsibility Owner.

### Success

`200 OK`.

---

## 10.4 Courier Assignment

### Endpoint

```http
POST /owner/orders/{order}/assignment
```

### Role

```text
OWNER
```

### Request

```json
{
  "courier_id": 20
}
```

### Validation

Backend memvalidasi:

- courier exists;
- courier valid untuk scope Berkah Water;
- courier availability sesuai domain rule;
- order dapat ditugaskan;
- tidak terdapat active assignment conflict.

### Transaction Boundary

```text
validate order
→ validate courier
→ create assignment
→ update order status
→ create order status history
→ commit
```

### Success

`201 Created`.

---

# 11. PAYMENT API

Payment API merupakan bagian yang direbuild penuh berdasarkan payment architecture terbaru.

Payment architecture hanya menggunakan:

```text
QRIS
CASH
```

dan:

```text
PENDING
WAITING_VERIFICATION
PAID
```

Tidak ada payment gateway.

Tidak ada Midtrans.

Tidak ada provider transaction.

Tidak ada webhook provider.

Tidak ada dynamic QRIS.

Tidak ada `payment_transactions`.

---

## 11.1 Customer — Get Active QRIS

### Endpoint

```http
GET /customer/payment/qris
```

### Authentication

Sanctum.

### Role

```text
CUSTOMER
```

### Purpose

Mengambil QRIS static aktif Berkah Water yang digunakan customer untuk melakukan pembayaran QRIS.

### Authorization

Customer hanya memiliki akses read-only.

Customer tidak boleh:

- mengganti QRIS;
- menghapus QRIS;
- mengubah konfigurasi bisnis.

### Success

`200 OK`.

```json
{
  "data": {
    "qris_image": "/storage/qris/active-qris.png",
    "updated_at": "2026-09-30T09:00:00Z"
  },
  "message": "Active QRIS retrieved."
}
```

### Errors

`404` jika active QRIS belum dikonfigurasi.

---

## 11.2 Customer — Create / Select Payment Method

### Endpoint

```http
POST /customer/orders/{order}/payment
```

### Authentication

Sanctum.

### Role

```text
CUSTOMER
```

### Request

```json
{
  "payment_method": "QRIS"
}
```

atau:

```json
{
  "payment_method": "CASH"
}
```

### Validation

Backend wajib memvalidasi:

- order ownership;
- order state;
- payment existence;
- payment method hanya `QRIS` atau `CASH`;
- tidak ada duplicate business payment;
- payment belum `PAID`;
- amount berasal dari authoritative order total;
- QRIS configuration tersedia jika method `QRIS`.

### Behavior

Jika QRIS:

```text
payment_method = QRIS
payment_status = PENDING
```

Jika CASH:

```text
payment_method = CASH
payment_status = PENDING
```

Pemilihan method tidak berarti payment `PAID`.

### Success

`201 Created` ketika payment record baru dibuat.

Jika business payment sudah ada dan operation merupakan selection/update yang valid, implementasi dapat menggunakan `200 OK` sesuai existing resource semantics. Backend tidak boleh membuat payment record kedua.

### Response

```json
{
  "data": {
    "payment": {
      "id": 7001,
      "order_id": 1001,
      "payment_method": "QRIS",
      "payment_status": "PENDING",
      "amount": "16000.00",
      "proof": null,
      "verified_by": null,
      "verified_at": null
    }
  },
  "message": "Payment method selected."
}
```

---

## 11.3 Customer — Get Payment

### Endpoint

```http
GET /customer/orders/{order}/payment
```

### Authentication

Sanctum.

### Role

```text
CUSTOMER
```

### Authorization

Customer hanya boleh membaca payment order miliknya.

### Success

`200 OK`.

```json
{
  "data": {
    "id": 7001,
    "order_id": 1001,
    "payment_method": "QRIS",
    "payment_status": "WAITING_VERIFICATION",
    "amount": "16000.00",
    "proof": {
      "available": true
    },
    "verified_by": null,
    "verified_at": null,
    "created_at": "2026-09-30T10:00:00Z",
    "updated_at": "2026-09-30T10:05:00Z"
  },
  "message": "Payment retrieved."
}
```

---

## 11.4 Customer — Payment History

### Endpoint

```http
GET /customer/payments
```

### Authentication

Sanctum.

### Role

```text
CUSTOMER
```

### Query

```text
?page=1&per_page=20
```

Optional filter:

```text
?payment_method=QRIS
?payment_status=PAID
```

### Authorization

Collection harus dibatasi ke payment yang order-nya dimiliki authenticated customer.

### Success

`200 OK`.

```json
{
  "data": [
    {
      "id": 7001,
      "order_id": 1001,
      "payment_method": "QRIS",
      "payment_status": "PAID",
      "amount": "16000.00",
      "verified_at": "2026-09-30T10:15:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 1
  },
  "message": "Payment history retrieved."
}
```

---

## 11.5 Customer — Upload QRIS Payment Proof

### Endpoint

```http
POST /customer/orders/{order}/payment/proof
```

### Authentication

Sanctum.

### Role

```text
CUSTOMER
```

### Content-Type

```text
multipart/form-data
```

### Form Field

```text
proof_image=<image-file>
```

### Validation

Backend wajib memvalidasi:

- authenticated customer;
- order ownership;
- payment exists;
- `payment_method = QRIS`;
- current payment status = `PENDING`;
- order state memungkinkan payment proof;
- file exists;
- file type merupakan image yang diizinkan;
- MIME type;
- extension;
- maximum file size;
- file tidak kosong/corrupt;
- storage path aman;
- filename tidak berasal langsung dari input client;
- uploaded file tidak dapat menimpa arbitrary filesystem path.

Ukuran maksimum dan daftar MIME/image format harus menjadi configuration constant backend, bukan hard-coded pada Android.

### State Transition

```text
PENDING
   ↓
WAITING_VERIFICATION
```

### Transaction Boundary

```text
validate authentication
→ validate ownership
→ validate payment method
→ validate payment state
→ validate order state
→ validate file
→ store private proof
→ update payment.proof_image
→ update payment.payment_status = WAITING_VERIFICATION
→ commit
```

Jika persistence gagal, payment tidak boleh berubah menjadi `WAITING_VERIFICATION`.

### Success

`200 OK`.

```json
{
  "data": {
    "payment": {
      "id": 7001,
      "payment_method": "QRIS",
      "payment_status": "WAITING_VERIFICATION",
      "amount": "16000.00",
      "proof": {
        "available": true
      },
      "verified_by": null,
      "verified_at": null
    }
  },
  "message": "QRIS payment proof uploaded."
}
```

### Forbidden Cases

Request harus ditolak jika:

```text
payment_method = CASH
payment_status = WAITING_VERIFICATION
payment_status = PAID
order bukan milik customer
```

---

# 12. OWNER PAYMENT API

## 12.1 Owner — List Pending QRIS Verification

### Endpoint

```http
GET /owner/payments/pending
```

### Authentication

Sanctum.

### Role

```text
OWNER
```

### Purpose

Mengambil payment QRIS yang berada pada:

```text
WAITING_VERIFICATION
```

### Query

```text
?page=1&per_page=20
```

### Authorization

Owner hanya menerima payment dalam scope Berkah Water.

### Server Filter

Minimal:

```text
payment_method = QRIS
payment_status = WAITING_VERIFICATION
```

### Success

`200 OK`.

```json
{
  "data": [
    {
      "id": 7001,
      "order_id": 1001,
      "order_number": "ORD-20260930-0001",
      "customer": {
        "id": 10,
        "name": "Budi"
      },
      "payment_method": "QRIS",
      "payment_status": "WAITING_VERIFICATION",
      "amount": "16000.00",
      "proof": {
        "available": true
      },
      "created_at": "2026-09-30T10:00:00Z",
      "updated_at": "2026-09-30T10:05:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 1
  },
  "message": "Pending QRIS verification retrieved."
}
```

---

## 12.2 Owner — View Payment Proof

### Endpoint

```http
GET /owner/orders/{order}/payment/proof
```

### Authentication

Sanctum.

### Role

```text
OWNER
```

### Authorization

Backend wajib memvalidasi:

- owner authenticated;
- order berada dalam owner scope;
- payment exists;
- payment method `QRIS`;
- proof exists.

### Success

`200 OK`.

Response dapat berupa payment proof resource atau authorized file response sesuai implementation storage.

Conceptual resource:

```json
{
  "data": {
    "order_id": 1001,
    "payment_id": 7001,
    "payment_method": "QRIS",
    "payment_status": "WAITING_VERIFICATION",
    "proof": {
      "available": true,
      "url": "/api/v1/owner/orders/1001/payment/proof?download=1"
    }
  },
  "message": "Payment proof retrieved."
}
```

**Format `proof.url`:**
- URL absolut ke endpoint private file delivery: `/api/v1/owner/orders/{order}/payment/proof?download=1`
- Memerlukan header `Authorization: Bearer <token>` dengan role `OWNER`
- Query parameter `download=1` wajib untuk mengunduh file gambar (tanpa flag tersebut endpoint mengembalikan metadata JSON)
- URL bersifat private dan memerlukan otorisasi Owner; bukan public URL

Proof tidak boleh menjadi public URL tanpa authorization.

---

## 12.3 Owner — Approve / Reject QRIS

### Endpoint

```http
POST /owner/orders/{order}/payment-verification
```

Endpoint ini mempertahankan existing naming convention `payment-verification`.

### Authentication

Sanctum.

### Role

```text
OWNER
```

### Request — Approve

```json
{
  "action": "APPROVE"
}
```

### Request — Reject

```json
{
  "action": "REJECT",
  "note": "Bukti pembayaran tidak dapat diverifikasi."
}
```

### Allowed Actions

```text
APPROVE
REJECT
```

### Validation

Backend wajib memvalidasi:

- owner authentication;
- owner role;
- owner operational scope;
- order existence;
- payment existence;
- payment method = `QRIS`;
- current payment status = `WAITING_VERIFICATION`;
- proof exists;
- action valid;
- note optional untuk rejection;
- note length sesuai server limit.

### Approve Transition

```text
WAITING_VERIFICATION
        ↓
      APPROVE
        ↓
PAID
```

Saat `PAID`:

```text
verified_by = authenticated Owner user
verified_at = current server timestamp
```

### Reject Transition

```text
WAITING_VERIFICATION
        ↓
       REJECT
        ↓
PENDING
```

Saat reject:

```text
verified_by = NULL
verified_at = NULL
```

Payment tidak berubah menjadi `FAILED` atau `REJECTED`.

Customer dapat mengunggah proof baru setelah payment kembali `PENDING`.

### Transaction Boundary — Approve

```text
authorize owner
→ lock/read payment state
→ validate QRIS
→ validate proof
→ validate current state
→ update payment = PAID
→ set verified_by
→ set verified_at
→ persist related business event
→ commit
```

### Transaction Boundary — Reject

```text
authorize owner
→ lock/read payment state
→ validate QRIS
→ validate proof
→ validate current state
→ update payment = PENDING
→ clear verification fields
→ persist related business event
→ commit
```

### Success — Approve

`200 OK`.

```json
{
  "data": {
    "payment": {
      "id": 7001,
      "order_id": 1001,
      "payment_method": "QRIS",
      "payment_status": "PAID",
      "amount": "16000.00",
      "verified_by": 5,
      "verified_at": "2026-09-30T10:15:00Z"
    }
  },
  "message": "QRIS payment approved."
}
```

### Success — Reject

`200 OK`.

```json
{
  "data": {
    "payment": {
      "id": 7001,
      "order_id": 1001,
      "payment_method": "QRIS",
      "payment_status": "PENDING",
      "amount": "16000.00",
      "verified_by": null,
      "verified_at": null
    }
  },
  "message": "QRIS payment rejected and returned to pending."
}
```

### Conflict

Jika payment sudah `PAID`:

```text
409 Conflict
```

Backend tidak boleh menurunkan:

```text
PAID → PENDING
```

melalui normal verification workflow.

---

# 13. OWNER QRIS CONFIGURATION API

Active QRIS merupakan singleton configuration Berkah Water pada `business_settings`.

Tidak dibuat endpoint dynamic QRIS.

Tidak ada merchant/provider entity.

Tidak ada QRIS transaction endpoint.

## 13.1 Owner — Get Active QRIS

### Endpoint

```http
GET /owner/payment/qris
```

### Authentication

Sanctum.

### Role

```text
OWNER
```

### Authorization

Owner scope Berkah Water.

### Success

`200 OK`.

```json
{
  "data": {
    "qris_image": "/storage/qris/active-qris.png",
    "updated_at": "2026-09-30T09:00:00Z",
    "updated_by": 5
  },
  "message": "Active QRIS retrieved."
}
```

## 13.2 Owner — Replace Active QRIS

### Endpoint

```http
PUT /owner/payment/qris
```

### Authentication

Sanctum.

### Role

```text
OWNER
```

### Content-Type

```text
multipart/form-data
```

### Form Field

```text
qris_image=<image-file>
```

### Validation

Backend wajib memvalidasi:

- Owner authentication;
- Owner authorization;
- image file;
- MIME type;
- extension;
- maximum file size;
- file integrity;
- private storage path;
- safe generated filename;
- existing active configuration;
- update performed transactionally.

### Behavior

Endpoint mengganti QRIS aktif pada singleton `business_settings`.

Tidak membuat:

```text
qris_merchants
qris_histories
dynamic QRIS records
```

### Success

`200 OK`.

```json
{
  "data": {
    "qris_image": "/storage/qris/active-qris-new.png",
    "updated_at": "2026-09-30T11:00:00Z",
    "updated_by": 5
  },
  "message": "Active QRIS replaced."
}
```

### Security

Customer tidak memiliki access ke endpoint mutation ini.

Courier tidak memiliki access ke endpoint mutation ini.

---

# 14. COURIER PAYMENT API

## 14.1 Courier — Assigned CASH Payment / Order

### Endpoint

```http
GET /courier/orders/{order}
```

Endpoint ini mempertahankan existing courier order detail.

### Role

```text
COURIER
```

### Authorization

Backend wajib memvalidasi:

```text
authenticated courier
AND
order has active/valid assignment
AND
assignment.courier_id = authenticated courier
```

Payment data dapat disertakan dalam Courier Order Resource.

Untuk CASH:

```json
{
  "payment": {
    "id": 7001,
    "payment_method": "CASH",
    "payment_status": "PENDING",
    "amount": "16000.00"
  }
}
```

Courier tidak boleh menggunakan endpoint ini untuk membaca payment order yang tidak ditugaskan.

---

## 14.2 Courier — Confirm Cash Received

### Endpoint

```http
POST /courier/orders/{order}/payment-confirmation
```

### Authentication

Sanctum.

### Role

```text
COURIER
```

### Purpose

Mengonfirmasi bahwa customer telah menyerahkan pembayaran CASH kepada Courier yang ditugaskan.

### Request

Tidak membutuhkan nominal dari client.

```json
{}
```

Jika implementation membutuhkan confirmation note, field tersebut harus optional dan tidak boleh menjadi source of truth nominal.

### Validation

Backend wajib memvalidasi:

- authenticated courier;
- role `COURIER`;
- order exists;
- active courier assignment exists;
- assignment belongs to authenticated courier;
- payment exists;
- `payment_method = CASH`;
- `payment_status = PENDING`;
- order berada pada delivery context yang sah;
- payment belum `PAID`.

### Forbidden

Courier tidak boleh:

```text
CASH order courier lain
QRIS order
PAID payment
WAITING_VERIFICATION payment
```

### State Transition

```text
PENDING
   ↓
Assigned Courier confirms
   ↓
PAID
```

### Transaction Boundary

```text
authenticate courier
→ validate assignment ownership
→ lock/read payment state
→ validate CASH
→ validate PENDING
→ validate delivery context
→ update payment = PAID
→ verified_by = authenticated courier user
→ verified_at = server timestamp
→ commit
```

### Success

`200 OK`.

```json
{
  "data": {
    "payment": {
      "id": 7001,
      "order_id": 1001,
      "payment_method": "CASH",
      "payment_status": "PAID",
      "amount": "16000.00",
      "verified_by": 20,
      "verified_at": "2026-09-30T11:30:00Z"
    }
  },
  "message": "Cash payment confirmed."
}
```

### Conflict

Jika sudah `PAID`:

```text
409 Conflict
```

atau response idempotent hanya jika behavior tersebut secara eksplisit dipilih pada implementation contract. API tidak boleh membuat payment record baru.

---

# 15. COURIER DELIVERY API

## 15.1 Assigned Orders

### Endpoint

```http
GET /courier/orders
```

### Role

```text
COURIER
```

### Authorization

Hanya assignment courier authenticated.

### Query

```text
?page=1&per_page=20
```

---

## 15.2 Delivery Detail

### Endpoint

```http
GET /courier/orders/{order}
```

### Role

```text
COURIER
```

### Authorization

Order harus memiliki assignment yang dimiliki authenticated courier.

---

## 15.3 Delivery Status

### Endpoint

```http
PATCH /courier/orders/{order}/status
```

### Role

```text
COURIER
```

### Request

```json
{
  "status": "DALAM_PENGANTARAN"
}
```

### Validation

Backend memvalidasi:

- assignment ownership;
- current order state;
- valid next state;
- courier responsibility;
- delivery context.

Client tidak dapat mengirim arbitrary transition.

---

# 16. COURIER TRACKING API

## 16.1 GPS Location Update

### Endpoint

```http
POST /courier/orders/{order}/location
```

### Role

```text
COURIER
```

### Request

```json
{
  "latitude": -0.9480,
  "longitude": 100.4180,
  "accuracy_meters": 8.50,
  "recorded_at": "2026-09-30T11:35:20Z"
}
```

### Validation

Backend wajib memvalidasi:

- authenticated courier;
- assignment exists;
- assignment belongs to courier;
- order belongs to assignment;
- delivery is active;
- latitude range;
- longitude range;
- accuracy if supplied;
- timestamp format;
- timestamp sanity sesuai implementation policy.

### Authorization

Courier tidak boleh mengirim location menggunakan `courier_id` milik courier lain.

Courier hanya mengirim location berdasarkan order/assignment yang sedang menjadi tanggung jawabnya.

### Success

`201 Created`.

---

## 16.2 Customer Tracking

### Endpoint

```http
GET /customer/orders/{order}/tracking
```

Sudah didefinisikan pada Customer API.

Tracking tetap delivery-bound dan customer hanya dapat melihat order miliknya.

---

# 17. NOTIFICATION API

Payment rebuild tidak mengubah endpoint notification existing.

## 17.1 Register FCM Device Token

### Endpoint

```http
POST /notifications/device-token
```

### Authentication

Sanctum.

### Role

```text
CUSTOMER | OWNER | COURIER
```

### Request

```json
{
  "token": "fcm-device-token",
  "platform": "ANDROID"
}
```

### Authorization

Token hanya boleh diregistrasikan untuk authenticated user.

## 17.2 List Notifications

### Endpoint

```http
GET /notifications
```

### Authentication

Sanctum.

User hanya menerima notification miliknya.

## 17.3 Mark Notification Read

### Endpoint

```http
PATCH /notifications/{notification}/read
```

### Authentication

Sanctum.

Notification harus dimiliki authenticated user.

---

# 18. Payment and Order Synchronization

Payment API tidak boleh mengarang order state baru.

## 18.1 QRIS

Canonical payment:

```text
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

QRIS `PAID` hanya terjadi setelah Owner memverifikasi proof.

## 18.2 CASH

```text
PENDING
   ↓
Courier receives cash
   ↓
Assigned Courier confirms
   ↓
PAID
```

CASH dapat tetap `PENDING` ketika order sudah masuk processing/delivery workflow sesuai rebuilt workflow.

## 18.3 Payment Does Not Automatically Define All Order States

API tidak boleh menerapkan rule global:

```text
payment must be PAID before every order transition
```

Karena CASH secara eksplisit dapat tetap `PENDING` ketika order diproses dan dikirim.

QRIS memiliki payment prerequisite yang berbeda dari CASH.

Transition order lainnya harus mengikuti order domain policy dan workflow.

---

# 19. Payment File Security

Proof QRIS dan QRIS configuration merupakan file yang harus diperlakukan sebagai protected business data.

## 19.1 Storage

Backend harus menggunakan generated storage path/name.

Client filename tidak boleh menjadi direct filesystem path.

## 19.2 Validation

Minimal validation:

```text
file exists
MIME allowed
extension allowed
size allowed
content valid
storage path controlled
```

## 19.3 Access

Payment proof tidak boleh menjadi public resource tanpa authorization.

Customer hanya boleh mengakses proof milik payment/order sendiri.

Owner hanya boleh mengakses proof dalam operational scope.

Courier tidak memiliki akses ke QRIS proof verification resource.

## 19.4 Proof Mutation

Proof QRIS:

```text
PENDING
   ↓
upload proof
   ↓
WAITING_VERIFICATION
```

Setelah:

```text
PAID
```

proof tidak boleh diganti melalui normal customer upload workflow.

Setelah rejection:

```text
WAITING_VERIFICATION
   ↓
PENDING
```

customer boleh upload proof baru.

---

# 20. Payment Database Mapping

Active payment database:

```text
orders
    │
    │ 1 : 1
    ▼
payments
```

`payments`:

```text
id
order_id
payment_method
payment_status
amount
proof_image
verified_by
verified_at
created_at
updated_at
```

Database tidak memiliki active:

```text
payment_transactions
provider_name
provider_reference
provider_transaction_id
transaction_id
transaction_status
transaction_type
raw_payload
webhook_id
provider_event_id
provider_callback
idempotency_key
paid_at
```

## 20.1 Payment Constraints

Backend harus menjaga:

```text
UNIQUE(payments.order_id)
```

dan valid domain values:

```text
payment_method:
QRIS | CASH

payment_status:
PENDING | WAITING_VERIFICATION | PAID
```

## 20.2 Verification Fields

Untuk non-PAID:

```text
verified_by = NULL
verified_at = NULL
```

Untuk PAID:

```text
verified_by != NULL
verified_at != NULL
```

QRIS PAID:

```text
verified_by = Owner
```

CASH PAID:

```text
verified_by = Assigned Courier
```

---

# 21. Concurrency and Consistency

Payment mutation harus dijalankan dengan transaction dan state validation yang aman terhadap concurrent request.

Contoh QRIS:

```text
Owner A approve
Owner B reject
```

Backend harus memastikan hanya transition berdasarkan current authoritative state yang valid yang dapat commit.

Contoh Cash:

```text
Courier double taps "Uang Diterima"
```

Backend tidak boleh menghasilkan dua payment record.

Karena relationship:

```text
one order → one payment
```

dan:

```text
payment status → server controlled
```

request kedua setelah `PAID` harus ditolak atau diperlakukan idempotently sesuai implementation contract yang dipilih.

---

# 22. Idempotency

## 22.1 Payment Record

Tidak boleh ada duplicate business payment untuk satu order.

Constraint:

```text
UNIQUE(order_id)
```

## 22.2 QRIS Proof Upload

Upload baru hanya boleh dilakukan ketika:

```text
payment_method = QRIS
payment_status = PENDING
```

Upload ketika `WAITING_VERIFICATION` atau `PAID` harus ditolak.

## 22.3 QRIS Verification

Approve/reject hanya berlaku ketika:

```text
payment_status = WAITING_VERIFICATION
```

## 22.4 Cash Confirmation

Cash confirmation hanya berlaku ketika:

```text
payment_method = CASH
payment_status = PENDING
assignment belongs to courier
```

---

# 23. Error Contract for Payment

## 23.1 Unauthenticated

```http
401 Unauthorized
```

```json
{
  "message": "Unauthenticated."
}
```

## 23.2 Wrong Role

```http
403 Forbidden
```

```json
{
  "message": "You are not authorized to perform this action."
}
```

## 23.3 Resource Not Owned

```http
404 Not Found
```

```json
{
  "message": "Resource not found."
}
```

## 23.4 Invalid Payment State

```http
409 Conflict
```

```json
{
  "message": "Payment state does not allow this operation."
}
```

## 23.5 Invalid Payment Method

```http
422 Unprocessable Entity
```

```json
{
  "message": "Validation failed.",
  "errors": {
    "payment_method": [
      "The selected payment method is invalid."
    ]
  }
}
```

## 23.6 Invalid File

```http
422 Unprocessable Entity
```

```json
{
  "message": "Validation failed.",
  "errors": {
    "proof_image": [
      "The uploaded file is invalid."
    ]
  }
}
```

## 23.7 Invalid Courier Assignment

```http
403 Forbidden
```

or `404 Not Found` when resource hiding is preferred.

---

# 24. Payment Authorization Matrix

| Operation | Customer | Owner | Courier |
|---|---:|---:|---:|
| View active QRIS | Yes | Yes | No |
| Select QRIS | Yes, own order | No | No |
| Select CASH | Yes, own order | No | No |
| View own payment | Yes | Operational scope | Assigned order |
| View payment history | Yes, own | Operational scope | Assigned order |
| Upload QRIS proof | Yes, own order | No | No |
| List pending QRIS verification | No | Yes | No |
| View QRIS proof | Own proof | Yes, operational scope | No |
| Approve QRIS | No | Yes | No |
| Reject QRIS | No | Yes | No |
| Get active QRIS configuration | No mutation | Yes | No |
| Replace active QRIS | No | Yes | No |
| View assigned CASH payment | No | Operational view | Yes, assigned order |
| Confirm CASH | No | No | Yes, assigned order |
| Send GPS location | No | No | Yes, assigned order |

---

# 25. Retrofit Contract

Setiap endpoint Android harus memiliki:

```text
HTTP Method
Path
Request DTO
Response DTO
Error mapping
```

Recommended payment DTO naming:

```text
PaymentDto
CreatePaymentRequestDto
PaymentHistoryItemDto
ActiveQrisDto
UploadQrisProofResponseDto
OwnerPendingPaymentDto
PaymentVerificationRequestDto
PaymentVerificationResponseDto
ReplaceActiveQrisResponseDto
CashPaymentConfirmationResponseDto
```

Recommended endpoint mapping:

```text
GET  /customer/payment/qris
POST /customer/orders/{order}/payment
GET  /customer/orders/{order}/payment
GET  /customer/payments
POST /customer/orders/{order}/payment/proof

GET  /owner/payments/pending
GET  /owner/orders/{order}/payment/proof
POST /owner/orders/{order}/payment-verification
GET  /owner/payment/qris
PUT  /owner/payment/qris

GET  /courier/orders/{order}
POST /courier/orders/{order}/payment-confirmation
```

Android tidak membuat payment business state berdasarkan local UI state.

---

# 26. Backend Implementation Mapping

Laravel implementation harus memiliki boundary yang jelas.

Recommended components:

```text
routes/api.php
        ↓
middleware auth:sanctum
        ↓
role middleware / Policy
        ↓
Form Request
        ↓
Controller
        ↓
Payment Service / Domain Service
        ↓
Transaction
        ↓
Eloquent Model
        ↓
API Resource
```

Payment service bertanggung jawab atas:

```text
select method
upload proof
approve QRIS
reject QRIS
confirm CASH
read payment
read payment history
read active QRIS
replace active QRIS
```

Controller tidak boleh menjadi tempat seluruh business rule kompleks.

---

# 27. Payment Business Invariants

Backend wajib mempertahankan invariant berikut.

## QRIS

```text
payment_method = QRIS
AND
payment_status = WAITING_VERIFICATION
→ proof_image IS NOT NULL
```

```text
payment_method = QRIS
AND
payment_status = PAID
→ proof_image IS NOT NULL
AND
verified_by IS OWNER
AND
verified_at IS NOT NULL
```

## CASH

```text
payment_method = CASH
→ proof_image IS NULL
```

```text
payment_method = CASH
AND
payment_status = PAID
→ verified_by IS ASSIGNED COURIER
AND
verified_at IS NOT NULL
```

## Payment Record

```text
one order → one payment
```

## Payment Method

```text
QRIS | CASH
```

## Payment Status

```text
PENDING | WAITING_VERIFICATION | PAID
```

---

# 28. Forbidden Legacy API

Seluruh endpoint berikut dihapus dari active API contract:

```http
POST /payments/midtrans/webhook
```

Tidak boleh ada lagi endpoint:

```text
Midtrans
payment gateway
provider callback
provider webhook
provider transaction
dynamic QRIS
```

Juga tidak boleh ada request/response field:

```text
provider_name
provider_reference
provider_transaction_id
transaction_id
transaction_status
transaction_type
payment_url
raw_payload
webhook_id
provider_event_id
provider_callback
idempotency_key
```

Jangan membuat compatibility endpoint hanya untuk mempertahankan architecture lama.

Jika implementation backend masih memiliki route/controller/service/provider integration legacy, komponen tersebut harus dianggap legacy dan diselaraskan sebelum implementation dinyatakan complete.

---

# 29. Non-Payment API Preservation

Endpoint non-payment existing tetap dipertahankan:

## AUTH

```http
POST /auth/register
POST /auth/login
POST /auth/logout
GET  /auth/me
```

## CUSTOMER

```http
GET  /customer/profile
PUT  /customer/profile
GET  /products
POST /customer/orders
GET  /customer/orders
GET  /customer/orders/{order}
GET  /customer/orders/{order}/tracking
```

## OWNER

```http
GET   /owner/orders
GET   /owner/orders/{order}
POST  /owner/orders/{order}/assignment
PATCH /owner/orders/{order}/status
```

## COURIER

```http
GET   /courier/orders
GET   /courier/orders/{order}
PATCH /courier/orders/{order}/status
POST  /courier/orders/{order}/location
```

## NOTIFICATION

```http
POST  /notifications/device-token
GET   /notifications
PATCH /notifications/{notification}/read
```

Payment endpoint baru/updated harus mengikuti naming style existing resource dan tidak mengubah endpoint non-payment tanpa alasan contract yang disetujui.

---

# 30. Final Endpoint Catalog

## AUTH

```text
POST /auth/register
POST /auth/login
POST /auth/logout
GET  /auth/me
```

## CUSTOMER

```text
GET  /customer/profile
PUT  /customer/profile
GET  /products

POST /customer/orders
GET  /customer/orders
GET  /customer/orders/{order}
GET  /customer/orders/{order}/tracking

GET  /customer/payment/qris
POST /customer/orders/{order}/payment
GET  /customer/orders/{order}/payment
GET  /customer/payments
POST /customer/orders/{order}/payment/proof
```

## OWNER

```text
GET   /owner/orders
GET   /owner/orders/{order}
PATCH /owner/orders/{order}/status
POST  /owner/orders/{order}/assignment

GET   /owner/payments/pending
GET   /owner/orders/{order}/payment/proof
POST  /owner/orders/{order}/payment-verification

GET   /owner/payment/qris
PUT   /owner/payment/qris
```

## COURIER

Backend dan Android menggunakan arsitektur **assignment-centric**. Endpoint order-centric pada draft specification lama tidak diimplementasikan.

```text
GET    /courier/assignments
GET    /courier/assignments/{assignment}
POST   /courier/assignments/{assignment}/start
POST   /courier/assignments/{assignment}/location
POST   /courier/assignments/{assignment}/cash/confirm
POST   /courier/assignments/{assignment}/complete
GET    /dashboard/courier
```

### Courier Assignment Endpoints Detail

#### GET /courier/assignments
- **Auth**: `role:COURIER`
- **Response**: `EnvelopeDto<List<CourierAssignmentDto>>` (array penuh, **tanpa paginasi/meta**)
- **Deskripsi**: Mengembalikan seluruh assignment milik kurir terautentikasi, terbaru dulu.

#### GET /courier/assignments/{assignment}
- **Auth**: `role:COURIER` + ownership (404 jika bukan miliknya)
- **Response**: `EnvelopeDto<CourierAssignmentDto>`
- **Deskripsi**: Detail satu assignment termasuk order, payment, dan latest_location.

#### POST /courier/assignments/{assignment}/start
- **Auth**: `role:COURIER` + ownership
- **Validasi**: assignment status = `ASSIGNED` DAN order_status = `DITUGASKAN`; kurir tidak boleh punya assignment `ACTIVE` lain
- **Transisi**: assignment `ASSIGNED` → `ACTIVE`, order `DITUGASKAN` → `DALAM_PENGANTARAN`
- **Response**: `EnvelopeDto<CourierAssignmentDto>`
- **Notifikasi**: `DELIVERY_STARTED` ke customer

#### POST /courier/assignments/{assignment}/location
- **Auth**: `role:COURIER` + ownership
- **Validasi**: assignment status = `ACTIVE` DAN order_status = `DALAM_PENGANTARAN`
- **Body**: `latitude` (required, -90..90), `longitude` (required, -180..180), `accuracy_meters` (required, 0..10000), `recorded_at` (optional, ISO 8601), `idempotency_key` (optional, UUID)
- **Validasi tambahan**: kecepatan ≤ 150 km/jam, timestamp harus monotonik naik
- **Response**: `{ "accepted": true, "location": CourierLocationResource }`
- **Notifikasi**: `TRACKING_AVAILABLE` ke customer (sekali per assignment)

#### POST /courier/assignments/{assignment}/cash/confirm
- **Auth**: `role:COURIER` + ownership
- **Validasi**: assignment `ACTIVE`, order `DALAM_PENGANTARAN`, payment method = `CASH`, payment status = `PENDING`
- **Body**: **tidak ada** (empty request)
- **Transisi**: payment `PENDING` → `PAID`, `verified_by` = courier user, `verified_at` = server timestamp
- **Response**: `EnvelopeDto<CourierCashReceiptDto>` (berisi `payment` resource)
- **Notifikasi**: `PAYMENT_CASH_CONFIRMED` ke customer

#### POST /courier/assignments/{assignment}/complete
- **Auth**: `role:COURIER` + ownership
- **Validasi**: assignment `ACTIVE`, order `DALAM_PENGANTARAN`, payment `PAID` (QRIS atau CASH)
- **Transisi**: assignment `ACTIVE` → `COMPLETED`, order `DALAM_PENGANTARAN` → `SELESAI`
- **Response**: `EnvelopeDto<CourierAssignmentDto>`
- **Notifikasi**: `ORDER_COMPLETED` ke customer, courier, dan owner

#### GET /dashboard/courier
- **Auth**: `role:COURIER` + `throttle:20,1`
- **Response**: `EnvelopeDto<CourierDashboardDto>`
- **Deskripsi**: Ringkasan assignment hari ini, assignment aktif, dan statistik.

## NOTIFICATION

```text
POST  /notifications/device-token
GET   /notifications
PATCH /notifications/{notification}/read
```

## PRESERVED ENDPOINTS

Endpoint berikut **tidak ada di katalog kanonik** (section 30) tetapi **aktif dipakai oleh klien Android** dan **dipertahankan untuk kompatibilitas mundur**. Menghapusnya akan memutus aplikasi yang berjalan.

| Method | URI | Role | Controller | Alasan Preserved |
|--------|-----|------|------------|------------------|
| POST | `/auth/logout-all` | Sanctum | AuthController@logoutAllDevices | Session management (multi-device logout) |
| POST | `/auth/refresh-token` | Sanctum | AuthController@refreshToken | Token rotation tanpa re-login |
| GET | `/profile` | Sanctum | ProfileController@show | Profile non-role-specific (Owner/Courier) |
| PUT | `/profile` | Sanctum | ProfileController@update | Profile update non-role-specific |
| GET | `/products/{product}` | Sanctum | ProductController@show | Product detail (Customer) |
| GET | `/customer/payment/qris/image` | CUSTOMER,OWNER | PaymentController@activeQrisImage | Private file delivery QRIS image |
| POST | `/notifications/read-all` | Sanctum | NotificationController@readAll | Bulk mark-read |
| GET | `/dashboard/customer` | CUSTOMER | DashboardController@customer | Dashboard summary |
| GET | `/dashboard/owner` | OWNER | DashboardController@owner | Dashboard summary |
| GET | `/dashboard/courier` | COURIER | DashboardController@courier | Dashboard summary |
| GET | `/owner/products` | OWNER | ProductController@ownerIndex | Owner product management |
| POST | `/owner/products` | OWNER | ProductController@store | Owner product management |
| PUT | `/owner/products/{product}` | OWNER | ProductController@update | Owner product management |
| DELETE | `/owner/products/{product}` | OWNER | ProductController@destroy | Owner product management |
| GET | `/owner/couriers` | OWNER | OwnerController@couriers | Daftar kurir untuk assignment |
| POST | `/customer/orders/{order}/cancel` | CUSTOMER | OrderController@cancel | Cancel order (Pilihan A: hanya MENUNGGU_PEMBAYARAN) |
| POST | `/courier/orders/{order}/payment-confirmation` | COURIER | CourierController@confirmOrderPayment | Legacy CASH confirm (order-centric) |

> **Catatan**: Endpoint `POST /courier/orders/{order}/payment-confirmation` memiliki fungsionalitas sama dengan `POST /courier/assignments/{assignment}/cash/confirm` (endpoint kanonik assignment-centric). Keduanya mengembalikan payload `data.payment` yang identik. Endpoint lama dipertahankan karena mungkin masih dipakai klien legacy; **tidak dihapus tanpa keputusan contract eksplisit**.

## REMOVED

```text
POST /payments/midtrans/webhook
```

Tidak ada pengganti webhook.

---

# 31. Endpoint Responsibility Summary

```text
CUSTOMER
│
├── Create/select payment
├── Get own payment
├── Get own payment history
├── Get active QRIS
└── Upload QRIS proof
        │
        ▼
WAITING_VERIFICATION
        │
        ▼
OWNER
│
├── List pending QRIS
├── View QRIS proof
├── Approve
├── Reject
├── Get active QRIS
└── Replace active QRIS

CASH
│
└── COURIER
      ├── View assigned CASH payment
      └── Confirm "Uang Diterima"

COURIER (Assignment-centric)
├── List assignments
├── View assignment detail
├── Start delivery
├── Post location
├── Confirm cash
├── Complete delivery
└── Dashboard
```

---

# 32. Payment Sequence

## 32.1 QRIS

```text
Customer
   ↓
POST /customer/orders/{order}/payment
   payment_method = QRIS
   ↓
Laravel
   ↓
Payment PENDING
   ↓
GET /customer/payment/qris
   ↓
Customer pays externally
   ↓
POST /customer/orders/{order}/payment/proof
   ↓
Laravel validates ownership + file + state
   ↓
WAITING_VERIFICATION
   ↓
Owner opens pending queue
   ↓
GET /owner/payments/pending
   ↓
GET /owner/orders/{order}/payment/proof
   ↓
POST /owner/orders/{order}/payment-verification
   action = APPROVE
   ↓
PAID
```

Reject:

```text
Owner
   ↓
POST /owner/orders/{order}/payment-verification
   action = REJECT
   ↓
PENDING
   ↓
Customer uploads new proof
```

## 32.2 CASH

```text
Customer
   ↓
POST /customer/orders/{order}/payment
   payment_method = CASH
   ↓
PENDING
   ↓
Order processed
   ↓
Courier assigned
   ↓
Courier delivers
   ↓
Customer pays cash
   ↓
POST /courier/assignments/{assignment}/cash/confirm
   ↓
Backend validates assignment + payment state
   ↓
PAID
```

> **Catatan**: Endpoint lama `POST /courier/orders/{order}/payment-confirmation` dipertahankan sebagai `preserved` untuk kompatibilitas mundur, tetapi endpoint kanonik baru adalah assignment-centric.

---

# 33. Security Checklist

```text
[ ] Sanctum required for private endpoints
[ ] Role authorization server-side
[ ] Customer ownership enforced
[ ] Owner operational scope enforced
[ ] Courier assignment enforced
[ ] Payment method validated
[ ] Payment status validated
[ ] Order state validated
[ ] Actor authorization validated
[ ] QRIS proof file validated
[ ] QRIS proof stored privately
[ ] QRIS proof cannot be replaced after PAID
[ ] CASH cannot enter WAITING_VERIFICATION
[ ] QRIS cannot be confirmed by Courier
[ ] CASH cannot be confirmed by Owner
[ ] Customer cannot approve own QRIS
[ ] Customer cannot replace active QRIS
[ ] Courier cannot confirm another courier's order
[ ] PAID cannot be reverted through normal payment flow
[ ] Amount calculated server-side
[ ] One order has one payment
[ ] No provider dependency
[ ] No payment gateway
[ ] No provider webhook
[ ] No dynamic QRIS
[ ] No payment_transactions
[ ] No provider fields in API resources
```

---

# 34. Testing Contract

Minimal API tests for payment:

```text
API-PAY-001 Customer selects QRIS
API-PAY-002 Customer selects CASH
API-PAY-003 Customer cannot select invalid method
API-PAY-004 Customer cannot select payment for another customer's order
API-PAY-005 Customer retrieves own payment
API-PAY-006 Customer retrieves payment history
API-PAY-007 Customer retrieves active QRIS
API-PAY-008 Customer uploads valid QRIS proof
API-PAY-009 Customer cannot upload proof for CASH
API-PAY-010 Customer cannot upload proof while WAITING_VERIFICATION
API-PAY-011 Customer cannot upload proof after PAID

API-PAY-012 Owner lists pending QRIS
API-PAY-013 Owner views QRIS proof
API-PAY-014 Owner approves QRIS
API-PAY-015 Owner rejects QRIS
API-PAY-016 Reject returns WAITING_VERIFICATION → PENDING
API-PAY-017 Owner cannot approve CASH
API-PAY-018 Owner retrieves active QRIS
API-PAY-019 Owner replaces active QRIS
API-PAY-020 Non-owner cannot replace QRIS

API-PAY-021 Courier sees assigned CASH payment
API-PAY-022 Courier confirms assigned CASH
API-PAY-023 Courier cannot confirm another courier's CASH
API-PAY-024 Courier cannot confirm QRIS
API-PAY-025 PAID payment cannot be confirmed again as a normal transition

API-PAY-026 No Midtrans endpoint exists
API-PAY-027 No payment webhook exists
API-PAY-028 No provider field is returned
API-PAY-029 No payment_transactions dependency exists
API-PAY-030 Payment amount always follows authoritative order amount
```

---

# 35. Android Implementation Checklist

```text
[ ] Auth API
[ ] Customer profile API
[ ] Product API
[ ] Order API
[ ] Customer tracking API
[ ] Owner order API
[ ] Courier assignment API
[ ] Courier tracking API
[ ] Notification API

[ ] Active QRIS API
[ ] Create/select payment API
[ ] Get payment API
[ ] Payment history API
[ ] Upload QRIS proof API
[ ] Owner pending payment API
[ ] Owner payment proof API
[ ] Owner approve/reject API
[ ] Owner active QRIS API
[ ] Owner replace QRIS API
[ ] Courier CASH confirmation API
```

Retrofit layer must not contain payment business rules that contradict backend.

---

# 36. Laravel Implementation Checklist

```text
[ ] routes/api.php updated
[ ] Sanctum middleware
[ ] Role middleware/policies
[ ] Customer ownership policy
[ ] Owner scope policy
[ ] Courier assignment policy

[ ] Payment Form Requests
[ ] Payment Controller
[ ] Payment Service
[ ] QRIS configuration service
[ ] Payment API Resources
[ ] Proof file validation
[ ] Private proof storage
[ ] Transaction boundaries
[ ] State transition validation
[ ] Concurrency protection
[ ] Payment history query
[ ] Pending QRIS query
[ ] Cash confirmation validation

[ ] Remove Midtrans routes
[ ] Remove Midtrans controllers
[ ] Remove provider services
[ ] Remove webhook handling
[ ] Remove provider transaction model
[ ] Remove payment_transactions dependency
[ ] Remove provider fields from API resources
```

---

# 37. Database/API Consistency Checklist

```text
[✓] orders 1 : 1 payments
[✓] payments.order_id unique
[✓] payment_method = QRIS | CASH
[✓] payment_status = PENDING | WAITING_VERIFICATION | PAID
[✓] proof_image nullable
[✓] verified_by nullable
[✓] verified_at nullable
[✓] QRIS proof supports WAITING_VERIFICATION
[✓] QRIS PAID requires verification actor
[✓] CASH proof_image remains NULL
[✓] CASH PAID requires assigned courier confirmation
[✓] business_settings stores active QRIS
[✓] one active QRIS configuration
[✓] no payment_transactions
[✓] no provider transaction fields
```

---

# 38. Final Architectural Principle

KYŪSUI API mengikuti:

```text
Android Kotlin
      ↓
Retrofit + OkHttp
      ↓
HTTPS / REST / JSON
      ↓
Laravel 13
      ↓
Sanctum Authentication
      ↓
Form Request Validation
      ↓
Policy / Authorization
      ↓
Application / Domain Service
      ↓
MySQL 8.x
```

Payment:

```text
QRIS
PENDING
   ↓
WAITING_VERIFICATION
   ↓
PAID

CASH
PENDING
   ↓
Assigned Courier Confirmation
   ↓
PAID
```

Server selalu menjadi authority.

Client mengirim request.

Server melakukan:

```text
Authenticate
   ↓
Authorize
   ↓
Validate ownership / assignment
   ↓
Validate payment method
   ↓
Validate current state
   ↓
Validate order state
   ↓
Validate file jika diperlukan
   ↓
Execute allowed transition
   ↓
Persist transactionally
   ↓
Return authoritative state
```

Tidak ada payment gateway.

Tidak ada Midtrans.

Tidak ada provider webhook.

Tidak ada dynamic QRIS.

Tidak ada provider transaction.

Tidak ada `payment_transactions`.

API ini menjadi kontrak utama antara Android Developer dan Backend Developer untuk domain payment KYŪSUI.
