# KYŪSUI — DATABASE FINALIZATION

**Project:** KYŪSUI  
**Study Case:** Berkah Water  
**Document:** `13_KYUSUI_DATABASE_FINALIZATION.md`  
**Status:** FINAL — DATABASE PAYMENT VALIDATED  
**Database:** MySQL 8.x  
**Backend:** Laravel 13 / PHP 8.3+  
**Platform:** Android Native / Kotlin / Jetpack Compose

---

# 1. Purpose

Dokumen ini merupakan final decision register untuk database KYŪSUI sebelum implementasi Laravel Migration.

Dokumen ini tidak berisi:

- SQL migration;
- Laravel migration code;
- model code;
- controller code;
- repository code;
- implementation code.

Fokus finalisasi ini adalah memastikan database konsisten dengan payment architecture terbaru:

```text
Order
  ↓
Payment
  ├── QRIS
  └── CASH
```

Payment architecture tidak lagi menggunakan payment gateway. Karena itu database tidak boleh mempertahankan struktur transaksi provider yang tidak mempunyai kebutuhan bisnis aktif.

---

# 2. Source Documents

Finalisasi ini menggunakan:

1. `05_KYUSUI_DATABASE_SCHEMA.md`
2. `08_KYUSUI_PAYMENT_SPECIFICATION.md`
3. `04_KYUSUI_SYSTEM_WORKFLOW.md`

Untuk implementasi database, schema rebuilt terbaru menjadi baseline struktur tabel. Payment specification rebuilt menjadi authority domain payment, sedangkan workflow rebuilt menjadi authority alur pembayaran.

Keputusan payment yang digunakan pada dokumen ini adalah:

```text
payment_method:
QRIS
CASH

payment_status:
PENDING
WAITING_VERIFICATION
PAID
```

QRIS menggunakan bukti pembayaran dan verifikasi Owner. CASH dikonfirmasi oleh Courier yang ditugaskan pada order.

---

# 3. Final Database Decision

## 3.1 Database Status

```text
DATABASE PAYMENT DESIGN
        ↓
FINAL
        ↓
READY AS DATABASE CONTRACT
```

Artinya, database contract payment telah dapat digunakan sebagai dasar implementasi migration.

Catatan:

```text
READY AS DATABASE CONTRACT
≠
migration SQL sudah dibuat
```

Dokumen ini tetap hanya menetapkan desain dan constraint.

---

# 4. Final Payment Architecture

Payment domain aktif hanya terdiri dari:

```text
orders
    │
    │ 1 : 1
    ▼
payments
```

Konfigurasi QRIS berada pada:

```text
business_settings
        │
        └── singleton configuration
                │
                └── active QRIS
```

Tidak ada:

```text
payment_transactions
```

Tidak ada payment provider dependency.

Tidak ada tabel transaksi eksternal.

Tidak ada webhook payment provider.

Tidak ada provider transaction reference.

---

# 5. Payment Transactions Final Decision

## 5.1 Decision

`payment_transactions` dihapus dari active database architecture.

Alasannya:

KYŪSUI tidak lagi mempunyai kebutuhan bisnis untuk menyimpan:

- payment gateway transaction;
- provider transaction;
- provider callback;
- provider webhook;
- provider event history;
- provider reconciliation;
- retry transaction berbasis provider;
- provider idempotency key.

Payment sekarang merupakan satu business record yang menyimpan state authoritative.

Model final:

```text
payments
├── id
├── order_id
├── payment_method
├── payment_status
├── amount
├── proof_image
├── verified_by
├── verified_at
├── created_at
└── updated_at
```

## 5.2 Tidak Ada Active Reference

Tidak boleh ada FK, relationship, index, field, atau terminology active schema yang mengarah ke:

```text
payment_transactions
```

Jika masih ditemukan referensi tersebut pada dokumen implementation lain, referensi tersebut dianggap legacy dan harus diselaraskan sebelum migration dibuat.

---

# 6. Payment Provider Dependency

Database final tidak mempunyai dependency terhadap payment provider.

Tidak boleh ada active column:

```text
provider_name
provider_reference
provider_transaction_id
provider_id
transaction_id
transaction_status
transaction_type
raw_payload
webhook_id
provider_event_id
provider_callback
idempotency_key
```

Database tidak menyimpan credential atau secret pembayaran.

Payment state ditentukan oleh workflow internal KYŪSUI.

---

# 7. Payment Method

## 7.1 Canonical Values

Hanya:

```text
QRIS
CASH
```

## 7.2 Forbidden Values

Tidak digunakan sebagai payment method:

```text
DIGITAL
MIDTRANS
PAYMENT_GATEWAY
TRANSFER
CARD
EWALLET
```

Metode pembayaran tidak dibuat sebagai master table karena hanya terdapat dua metode yang sudah dikunci oleh business requirement.

## 7.3 Database Rule

`payments.payment_method`:

```text
NOT NULL
```

Nilai harus termasuk:

```text
QRIS
CASH
```

Backend wajib melakukan validation sebelum record dibuat atau diperbarui.

---

# 8. Payment Status

## 8.1 Canonical Values

Hanya:

```text
PENDING
WAITING_VERIFICATION
PAID
```

## 8.2 Status Meaning

### PENDING

Payment belum berhasil dikonfirmasi.

QRIS:

```text
belum upload proof
atau
proof sebelumnya ditolak
```

CASH:

```text
customer belum membayar
atau
courier belum mengonfirmasi uang diterima
```

### WAITING_VERIFICATION

Hanya digunakan untuk QRIS.

Maknanya:

```text
Customer sudah upload proof
        ↓
Proof menunggu pemeriksaan Owner
```

CASH tidak boleh berada pada status ini.

### PAID

Payment telah dikonfirmasi melalui workflow yang sah.

QRIS:

```text
Owner verifies proof
        ↓
PAID
```

CASH:

```text
Assigned Courier confirms cash
        ↓
Backend validates assignment
        ↓
PAID
```

## 8.3 Forbidden Status

Status berikut tidak termasuk active payment model:

```text
PROCESSING
CONFIRMED
FAILED
EXPIRED
REJECTED
```

Rejection QRIS bukan status payment baru.

```text
WAITING_VERIFICATION
        ↓
Owner rejects
        ↓
PENDING
```

---

# 9. Final `payments` Table

## 9.1 Purpose

`payments` adalah satu-satunya business payment record untuk satu order.

## 9.2 Columns

| Column | Datatype | PK | FK | Nullable | Default | Unique | Index |
|---|---|---:|---|---:|---|---:|---|
| `id` | BIGINT UNSIGNED | Yes | - | No | AUTO_INCREMENT | Yes | PK |
| `order_id` | BIGINT UNSIGNED | No | `orders.id` | No | - | Yes | UNIQUE |
| `payment_method` | VARCHAR(20) | No | - | No | - | No | INDEX |
| `payment_status` | VARCHAR(30) | No | - | No | `PENDING` | No | INDEX |
| `amount` | DECIMAL(15,2) | No | - | No | - | No | - |
| `proof_image` | VARCHAR(500) | No | - | Yes | NULL | No | - |
| `verified_by` | BIGINT UNSIGNED | No | `users.id` | Yes | NULL | No | INDEX |
| `verified_at` | TIMESTAMP | No | - | Yes | NULL | No | INDEX |
| `created_at` | TIMESTAMP | No | - | No | CURRENT_TIMESTAMP | No | - |
| `updated_at` | TIMESTAMP | No | - | No | CURRENT_TIMESTAMP | No | - |

## 9.3 Fields Deliberately Removed

Tidak ada:

```text
provider_name
provider_reference
provider_transaction_id
transaction_id
transaction_status
transaction_type
raw_payload
idempotency_key
paid_at
payment_transactions
```

`verified_at` menjadi timestamp authoritative ketika payment berubah menjadi `PAID`.

---

# 10. One Order → One Payment

## 10.1 Final Relationship

```text
orders 1 ───── 1 payments
```

Setiap order mempunyai paling banyak satu business payment record.

Constraint database:

```text
payments.order_id
    ↓
UNIQUE
```

Dengan demikian:

```text
order #1001
    ↓
payment #7001
```

dan tidak boleh:

```text
order #1001
    ├── payment #7001
    └── payment #7002
```

## 10.2 Retry / Re-upload

Retry atau upload ulang proof QRIS tidak membuat payment baru.

Contoh:

```text
Payment #7001
    PENDING
       ↓
WAITING_VERIFICATION
       ↓
PENDING
       ↓
WAITING_VERIFICATION
       ↓
PAID
```

Semua lifecycle tetap berada pada satu row `payments`.

---

# 11. Payment Amount Integrity

`payments.amount` harus sama dengan nominal order authoritative:

```text
payments.amount
        =
orders.total_amount
```

Nominal berasal dari backend:

```text
order_items
    ↓
subtotal_amount
    ↓
delivery_fee
    ↓
total_amount
    ↓
payments.amount
```

Android tidak menjadi source of truth nominal payment.

Nominal menggunakan:

```text
DECIMAL(15,2)
```

Tidak menggunakan:

```text
FLOAT
DOUBLE
```

---

# 12. QRIS Payment Data Rules

## 12.1 QRIS Method

Jika:

```text
payment_method = QRIS
```

maka status yang valid:

```text
PENDING
WAITING_VERIFICATION
PAID
```

## 12.2 Proof Image

`proof_image` digunakan untuk menyimpan reference/path bukti pembayaran QRIS.

Storage harus bersifat private.

Customer hanya dapat mengakses proof milik order sendiri.

Owner dapat mengakses proof yang berada dalam scope operasional Berkah Water.

## 12.3 QRIS Lifecycle

```text
PENDING
   ↓
Customer uploads proof
   ↓
WAITING_VERIFICATION
   ↓
Owner verifies
   ↓
PAID
```

Jika ditolak:

```text
WAITING_VERIFICATION
   ↓
Owner rejects
   ↓
PENDING
   ↓
Customer uploads replacement proof
   ↓
WAITING_VERIFICATION
```

## 12.4 QRIS Proof Invariant

Pada saat:

```text
payment_status = WAITING_VERIFICATION
```

maka:

```text
proof_image IS NOT NULL
```

Pada saat:

```text
payment_status = PAID
AND
payment_method = QRIS
```

maka:

```text
proof_image IS NOT NULL
verified_by IS NOT NULL
verified_at IS NOT NULL
```

---

# 13. CASH Payment Data Rules

## 13.1 CASH Method

Jika:

```text
payment_method = CASH
```

maka status yang valid:

```text
PENDING
PAID
```

`WAITING_VERIFICATION` tidak valid untuk CASH.

## 13.2 Proof Image

CASH tidak menggunakan bukti foto.

Invariant:

```text
payment_method = CASH
        ↓
proof_image = NULL
```

Tidak boleh ada upload proof untuk Cash.

## 13.3 CASH Lifecycle

```text
PENDING
   ↓
Customer pays Courier
   ↓
Assigned Courier selects "Uang Diterima"
   ↓
Backend validates assignment
   ↓
PAID
```

CASH dapat tetap `PENDING` ketika order sedang diproses atau dikirim.

---

# 14. `verified_by` Final Rule

## 14.1 Purpose

`verified_by` menyimpan actor yang secara sah menyebabkan payment menjadi `PAID`.

FK:

```text
payments.verified_by
        ↓
users.id
```

## 14.2 QRIS

Untuk QRIS:

```text
verified_by = user.id milik Owner
```

Backend wajib memvalidasi:

```text
authenticated user
        ↓
role = OWNER
        ↓
authorized for Berkah Water
        ↓
payment.method = QRIS
        ↓
payment.status = WAITING_VERIFICATION
        ↓
verify proof
        ↓
PAID
```

## 14.3 CASH

Untuk CASH:

```text
verified_by = user.id milik Assigned Courier
```

Backend wajib memvalidasi:

```text
authenticated user
        ↓
role = COURIER
        ↓
active assignment exists
        ↓
assignment belongs to authenticated courier
        ↓
payment.method = CASH
        ↓
payment.status = PENDING
        ↓
confirm cash
        ↓
PAID
```

## 14.4 Nullable Rule

`verified_by` harus nullable karena payment pada:

```text
PENDING
WAITING_VERIFICATION
```

belum mempunyai verification actor final.

Namun:

```text
payment_status = PAID
```

wajib mempunyai:

```text
verified_by IS NOT NULL
```

---

# 15. `verified_at` Final Rule

`verified_at` menyimpan waktu ketika payment secara sah menjadi `PAID`.

## 15.1 PAID

Jika:

```text
payment_status = PAID
```

maka:

```text
verified_at IS NOT NULL
```

## 15.2 Non-PAID

Jika:

```text
payment_status = PENDING
```

atau:

```text
payment_status = WAITING_VERIFICATION
```

maka:

```text
verified_at IS NULL
```

## 15.3 Rejection

Ketika QRIS:

```text
WAITING_VERIFICATION → PENDING
```

maka payment tidak lagi berada pada verified state.

Karena itu state aktif harus kembali menjadi:

```text
verified_by = NULL
verified_at = NULL
```

Kemudian customer dapat mengirim proof baru.

---

# 16. Payment State Integrity Matrix

| Method | Status | `proof_image` | `verified_by` | `verified_at` |
|---|---|---|---|---|
| QRIS | PENDING | NULL atau proof lama yang belum lagi aktif untuk verification* | NULL | NULL |
| QRIS | WAITING_VERIFICATION | NOT NULL | NULL | NULL |
| QRIS | PAID | NOT NULL | Owner | NOT NULL |
| CASH | PENDING | NULL | NULL | NULL |
| CASH | PAID | NULL | Assigned Courier | NOT NULL |

`*` Untuk implementasi final, ketika proof ditolak dan payment kembali ke `PENDING`, proof yang ditolak tidak boleh dianggap sebagai proof aktif. Jika sistem tidak menyimpan history proof terpisah, nilai `proof_image` sebaiknya dikosongkan pada rejection agar invariant database tetap sederhana:

```text
QRIS PENDING
→ proof_image = NULL
```

Keputusan ini menjaga `proof_image` merepresentasikan proof yang sedang diajukan, bukan bukti lama yang sudah ditolak.

---

# 17. Payment Transition Matrix

## QRIS

Allowed:

```text
PENDING → WAITING_VERIFICATION
WAITING_VERIFICATION → PAID
WAITING_VERIFICATION → PENDING
```

Conditions:

```text
PENDING → WAITING_VERIFICATION
= Customer + valid proof

WAITING_VERIFICATION → PAID
= Owner + valid verification

WAITING_VERIFICATION → PENDING
= Owner + rejection
```

## CASH

Allowed:

```text
PENDING → PAID
```

Condition:

```text
Assigned Courier + valid assignment
```

## Forbidden

```text
PAID → PENDING
PAID → WAITING_VERIFICATION

CASH → WAITING_VERIFICATION

QRIS PENDING → PAID without Owner verification

Customer → PAID

Customer → WAITING_VERIFICATION without proof

Courier → PAID for QRIS

Courier → PAID for another courier's order

Owner → PAID for CASH
```

---

# 18. Payment Relationship Integrity

## Order

```text
orders.id
```

adalah parent payment.

## Payment

```text
payments.order_id
```

wajib menunjuk ke order valid.

Tidak boleh ada:

```text
payment tanpa order
```

## FK

```text
payments.order_id
    → orders.id
```

Recommended behavior:

```text
ON DELETE RESTRICT
ON UPDATE CASCADE
```

Payment tidak boleh hilang hanya karena parent order dihapus secara tidak sengaja.

---

# 19. `verified_by` FK Integrity

FK:

```text
payments.verified_by
    → users.id
```

Recommended:

```text
ON DELETE SET NULL
ON UPDATE CASCADE
```

Alasan:

- actor adalah identity user;
- payment record harus dipertahankan;
- user tidak boleh menghapus histori financial secara cascade;
- soft delete user tidak menghilangkan histori;
- hard delete identity, jika benar-benar diperbolehkan oleh infrastructure policy, tidak boleh menyebabkan orphan FK.

Business rule tetap menyatakan bahwa setiap `PAID` payment harus mempunyai actor valid pada saat verification dilakukan.

---

# 20. Orphan Reference Audit

Tidak boleh terdapat:

```text
payments tanpa orders
payments.verified_by mengarah ke user yang tidak ada
business_settings.updated_by mengarah ke user yang tidak ada
```

Tidak boleh terdapat FK menuju:

```text
payment_transactions
provider transaction tables
payment gateway tables
merchant provider tables
```

Active database harus dapat berdiri tanpa entity payment provider eksternal.

---

# 21. Business Settings — Active QRIS

## 21.1 Purpose

`business_settings` digunakan untuk konfigurasi bisnis level Berkah Water.

Karena KYŪSUI hanya mempunyai satu depot, tidak diperlukan:

```text
depots
merchant_accounts
qris_merchants
qris_accounts
```

## 21.2 Singleton Model

Konfigurasi menggunakan satu logical record:

```text
business_settings
        │
        └── id = 1
```

Struktur:

```text
business_settings
├── id
├── qris_image
├── updated_by
├── created_at
└── updated_at
```

## 21.3 Active QRIS

`qris_image` merepresentasikan static QRIS aktif Berkah Water.

Pada satu waktu:

```text
ONE CONFIGURATION SLOT
        ↓
ONE ACTIVE QRIS
```

Tidak boleh ada:

```text
QRIS A active
QRIS B active
QRIS C active
```

secara bersamaan.

## 21.4 Constraint Interpretation

Keunikan active QRIS tidak dilakukan dengan:

```text
UNIQUE(qris_image)
```

karena image path bukan identity bisnis.

Keunikan diperoleh melalui:

```text
singleton business_settings record
```

dengan:

```text
id = 1
```

Jadi database hanya mempunyai satu tempat untuk menyimpan QRIS aktif.

## 21.5 QRIS Availability

Jika aplikasi mewajibkan QRIS selalu tersedia sebelum customer memilih metode QRIS, konfigurasi awal harus menyediakan `qris_image`.

Jika sistem belum dikonfigurasi, `qris_image` dapat berada pada:

```text
NULL
```

yang berarti:

```text
QRIS belum dikonfigurasi
```

dan bukan berarti terdapat lebih dari satu QRIS.

---

# 22. `business_settings.updated_by`

FK:

```text
business_settings.updated_by
    → users.id
```

Purpose:

```text
audit actor
```

Actor yang valid untuk penggantian QRIS:

```text
OWNER
```

Recommended FK:

```text
ON DELETE SET NULL
ON UPDATE CASCADE
```

`updated_by` bukan payment verification actor.

Jangan menggunakan:

```text
business_settings.updated_by
```

untuk mengisi:

```text
payments.verified_by
```

Keduanya memiliki makna bisnis berbeda.

---

# 23. QRIS Access Relationship

Customer:

```text
READ
```

Owner:

```text
READ
REPLACE
```

Courier:

```text
NO PAYMENT CONFIGURATION ACCESS REQUIRED
```

Database hanya menyimpan configuration state.

Authorization tetap menjadi tanggung jawab backend.

---

# 24. Foreign Key Matrix — Final

| Child Table | FK | Parent | ON DELETE | ON UPDATE |
|---|---|---|---|---|
| `users` | `role_id` | `roles.id` | RESTRICT | CASCADE |
| `customers` | `user_id` | `users.id` | RESTRICT | CASCADE |
| `owners` | `user_id` | `users.id` | RESTRICT | CASCADE |
| `couriers` | `user_id` | `users.id` | RESTRICT | CASCADE |
| `orders` | `customer_id` | `customers.id` | RESTRICT | CASCADE |
| `order_items` | `order_id` | `orders.id` | CASCADE | CASCADE |
| `order_items` | `product_id` | `products.id` | RESTRICT | CASCADE |
| `order_status_histories` | `order_id` | `orders.id` | CASCADE | CASCADE |
| `order_status_histories` | `changed_by_user_id` | `users.id` | SET NULL | CASCADE |
| `payments` | `order_id` | `orders.id` | RESTRICT | CASCADE |
| `payments` | `verified_by` | `users.id` | SET NULL | CASCADE |
| `business_settings` | `updated_by` | `users.id` | SET NULL | CASCADE |
| `courier_assignments` | `order_id` | `orders.id` | RESTRICT | CASCADE |
| `courier_assignments` | `courier_id` | `couriers.id` | RESTRICT | CASCADE |
| `courier_locations` | `courier_assignment_id` | `courier_assignments.id` | RESTRICT | CASCADE |
| `courier_locations` | `courier_id` | `couriers.id` | RESTRICT | CASCADE |
| `notifications` | `user_id` | `users.id` | CASCADE | CASCADE |

Tidak ada FK ke payment provider entity.

Tidak ada FK ke `payment_transactions`.

---

# 25. Index Strategy — Final

## `payments`

Wajib:

```text
UNIQUE(order_id)
INDEX(payment_method)
INDEX(payment_status)
INDEX(verified_by)
INDEX(verified_at)
```

Rationale:

```text
UNIQUE(order_id)
    → one order one payment

INDEX(payment_method)
    → QRIS/CASH filtering

INDEX(payment_status)
    → pending / verification queue

INDEX(verified_by)
    → actor audit/query

INDEX(verified_at)
    → payment verification chronology
```

Tidak ada index terhadap field provider karena field tersebut tidak ada.

## `business_settings`

```text
PRIMARY KEY(id)
INDEX(updated_by)
```

`qris_image` tidak memerlukan unique index.

---

# 26. Nullable / Non-Nullable Final Audit

## `payments`

```text
id                  NOT NULL
order_id            NOT NULL
payment_method      NOT NULL
payment_status      NOT NULL
amount              NOT NULL
proof_image         NULLABLE
verified_by         NULLABLE
verified_at         NULLABLE
created_at          NOT NULL
updated_at          NOT NULL
```

Alasan nullable:

```text
proof_image
    → tidak digunakan CASH

verified_by
    → payment belum PAID

verified_at
    → payment belum PAID
```

## `business_settings`

```text
id          NOT NULL
qris_image  NULLABLE pada kondisi belum dikonfigurasi
updated_by  NULLABLE
created_at  NOT NULL
updated_at  NOT NULL
```

Jika project memutuskan QRIS wajib tersedia sejak bootstrap, `qris_image` dapat diperlakukan sebagai NOT NULL pada implementation migration. Keputusan tersebut tidak mengubah singleton rule.

---

# 27. Database-Level vs Application-Level Rules

Tidak semua business invariant dapat diekspresikan hanya melalui FK dan UNIQUE.

## Database dapat menjamin

```text
payment memiliki order
one order → one payment
verified_by menunjuk ke user valid
business_settings memiliki satu singleton record
```

## Backend harus menjamin

```text
QRIS → Owner verification
CASH → Assigned Courier confirmation

CASH → proof_image NULL

WAITING_VERIFICATION → QRIS only

PAID → verified_by NOT NULL
PAID → verified_at NOT NULL

PENDING/WAITING_VERIFICATION
→ verified_by NULL
→ verified_at NULL

QRIS WAITING_VERIFICATION
→ proof_image NOT NULL

PAID QRIS
→ proof_image NOT NULL
```

Backend juga harus memastikan actor benar-benar memiliki role dan authorization yang sesuai.

---

# 28. Payment Concurrency

Payment state bersifat concurrency-sensitive.

Backend harus mencegah request lama menimpa state baru.

Contoh:

```text
Owner verifies QRIS
        ↓
PAID
        ↓
old customer request arrives
        ↓
must NOT change payment back to PENDING
```

Untuk payment yang sudah:

```text
PAID
```

normal payment workflow tidak boleh menurunkannya kembali.

One business payment:

```text
one authoritative current state
```

---

# 29. Transaction Boundaries

## Create Order

Logical transaction:

```text
Create order
    ↓
Create order_items
    ↓
Create payment
    ↓
Create initial order status history
    ↓
COMMIT
```

Payment tidak boleh dibuat sebagai orphan record.

## QRIS Proof Upload

```text
Validate customer ownership
    ↓
Validate payment = QRIS
    ↓
Validate status = PENDING
    ↓
Store proof
    ↓
WAITING_VERIFICATION
    ↓
COMMIT
```

## QRIS Verification

```text
Validate Owner
    ↓
Validate QRIS
    ↓
Validate WAITING_VERIFICATION
    ↓
Validate proof exists
    ↓
PAID
    ↓
verified_by = Owner
    ↓
verified_at = now
    ↓
COMMIT
```

## QRIS Rejection

```text
Validate Owner
    ↓
Validate QRIS
    ↓
Validate WAITING_VERIFICATION
    ↓
PENDING
    ↓
Clear/revoke active rejected proof reference
    ↓
verified_by = NULL
    ↓
verified_at = NULL
    ↓
COMMIT
```

## CASH Confirmation

```text
Validate Courier
    ↓
Validate active assignment
    ↓
Validate assignment belongs to Courier
    ↓
Validate CASH
    ↓
Validate PENDING
    ↓
PAID
    ↓
verified_by = Courier
    ↓
verified_at = now
    ↓
COMMIT
```

## QRIS Configuration Replacement

```text
Validate Owner
    ↓
Validate singleton business_settings
    ↓
Replace qris_image
    ↓
updated_by = Owner
    ↓
COMMIT
```

---

# 30. Eloquent Relationship Mapping

Konseptual relationship:

```text
Role
  hasMany User

User
  belongsTo Role
  hasOne Customer
  hasOne Owner
  hasOne Courier
  hasMany Notifications
  hasMany OrderStatusHistories
  hasMany Payments through verified_by
  hasMany BusinessSettings through updated_by

Customer
  belongsTo User
  hasMany Orders

Owner
  belongsTo User

Courier
  belongsTo User
  hasMany CourierAssignments
  hasMany CourierLocations

Product
  hasMany OrderItems

Order
  belongsTo Customer
  hasMany OrderItems
  hasMany OrderStatusHistories
  hasOne Payment
  hasMany CourierAssignments

OrderItem
  belongsTo Order
  belongsTo Product

OrderStatusHistory
  belongsTo Order
  belongsTo User

Payment
  belongsTo Order
  belongsTo User through verified_by

BusinessSettings
  belongsTo User through updated_by

CourierAssignment
  belongsTo Order
  belongsTo Courier
  hasMany CourierLocations

CourierLocation
  belongsTo CourierAssignment
  belongsTo Courier

Notification
  belongsTo User
```

`business_settings` tetap diperlakukan sebagai singleton configuration resource, bukan collection multi-depot.

---

# 31. Terminology Audit

## Active Terminology

```text
payment
payments
payment_method
payment_status
QRIS
CASH
PENDING
WAITING_VERIFICATION
PAID
proof_image
verified_by
verified_at
business_settings
qris_image
updated_by
```

## Removed Terminology

Istilah berikut tidak boleh menjadi bagian active payment/database model:

```text
payment_transactions
provider_name
provider_reference
provider_transaction_id
provider_id
transaction_id
transaction_status
transaction_type
raw_payload
webhook_id
provider_event_id
idempotency_key
PROCESSING
CONFIRMED
FAILED
EXPIRED
DIGITAL
PAYMENT_GATEWAY
```

Istilah legacy boleh disebut dalam dokumen audit hanya untuk menjelaskan bahwa konsep tersebut sudah dihapus. Istilah tersebut tidak boleh muncul sebagai active column, table, FK, relationship, enum, atau dependency.

---

# 32. Deliberately Not Included

## `payment_transactions`

Tidak dibuat.

Alasan:

```text
No payment gateway
No provider transaction
No provider webhook
No provider reconciliation
```

## Provider Payment Fields

Tidak dibuat:

```text
provider_name
provider_reference
provider_transaction_id
transaction_id
raw_payload
idempotency_key
```

## `payment_methods`

Tidak dibuat karena:

```text
QRIS
CASH
```

sudah cukup sebagai finite domain value.

## `depots`

Tidak dibuat karena KYŪSUI hanya menangani:

```text
Berkah Water
```

## `qris_merchants`

Tidak dibuat karena tidak ada multi-merchant requirement.

## `qris_histories`

Tidak dibuat karena versioning QRIS belum menjadi business requirement.

## `delivery`

Tidak dibuat sebagai payment table atau separate payment entity.

Delivery context direpresentasikan oleh:

```text
courier_assignments
```

## `order_history`

Tidak dibuat.

History order berasal dari:

```text
orders
+
order_status_histories
```

---

# 33. Active Database Tables

Final active database:

```text
1.  roles
2.  users
3.  customers
4.  owners
5.  couriers
6.  products
7.  orders
8.  order_items
9.  order_status_histories
10. payments
11. business_settings
12. courier_assignments
13. courier_locations
14. notifications
```

Tidak ada table ke-15 bernama:

```text
payment_transactions
```

---

# 34. Final Relationship Model

```text
roles
  │
  └──< users
          ├── 0..1 customers
          ├── 0..1 owners
          └── 0..1 couriers

customers ──< orders ──< order_items >── products

orders ──< order_status_histories

orders ──|| payments

orders ──< courier_assignments >── couriers

courier_assignments ──< courier_locations
couriers ──< courier_locations

users ──< notifications

business_settings
  └── singleton active QRIS configuration
```

Payment:

```text
ORDER
  │
  │ 1 : 1
  ▼
PAYMENT
```

QRIS configuration:

```text
BUSINESS_SETTINGS
       │
       │ singleton
       ▼
ACTIVE QRIS
       │
       ▼
CUSTOMER VIEW
```

---

# 35. Final Payment Flow

## QRIS

```text
Order
  ↓
Payment PENDING
  ↓
Customer views active QRIS
  ↓
Customer pays externally
  ↓
Customer uploads proof
  ↓
WAITING_VERIFICATION
  ↓
Owner reviews proof
  ├── Reject → PENDING
  │             ↓
  │        upload new proof
  │
  └── Verify → PAID
```

Final QRIS invariants:

```text
QRIS
+ proof
+ Owner verification
= PAID
```

## CASH

```text
Order
  ↓
Payment PENDING
  ↓
Order processed
  ↓
Courier delivers
  ↓
Customer pays cash
  ↓
Assigned Courier confirms
  ↓
Backend validates assignment
  ↓
PAID
```

Final CASH invariants:

```text
CASH
+ Assigned Courier confirmation
+ valid assignment
= PAID
```

---

# 36. Final Integrity Checklist

```text
[✓] payment_transactions removed from active architecture
[✓] No payment provider dependency
[✓] No provider field
[✓] No provider transaction field
[✓] No provider webhook dependency
[✓] payment_method = QRIS / CASH only
[✓] payment_status = PENDING / WAITING_VERIFICATION / PAID only
[✓] One order → one payment
[✓] payments.order_id is UNIQUE
[✓] Payment amount follows orders.total_amount
[✓] QRIS uses proof_image
[✓] CASH proof_image = NULL
[✓] QRIS WAITING_VERIFICATION requires proof
[✓] QRIS PAID requires proof
[✓] QRIS PAID verified_by = Owner
[✓] CASH PAID verified_by = Assigned Courier
[✓] PAID requires verified_by
[✓] PAID requires verified_at
[✓] Non-PAID states keep verified_by NULL
[✓] Non-PAID states keep verified_at NULL
[✓] QRIS rejection returns to PENDING
[✓] CASH never enters WAITING_VERIFICATION
[✓] PAID is not reverted by normal payment workflow
[✓] payment.order_id → orders.id
[✓] payment.verified_by → users.id
[✓] FK delete behavior protects payment history
[✓] No orphan payment reference
[✓] No FK to removed payment entities
[✓] Active QRIS represented by singleton business_settings
[✓] Only one active QRIS configuration slot exists
[✓] qris_image is not treated as a unique identity
[✓] business_settings.updated_by → users.id
[✓] Owner controls QRIS replacement
[✓] Customer only reads active QRIS
[✓] No multi-depot payment structure
[✓] No multi-merchant payment structure
[✓] No SQL migration included
[✓] No implementation code included
```

---

# 37. Final Decision Summary

| Area | Final Decision |
|---|---|
| Payment table | `payments` |
| Payment transaction table | Removed |
| Payment gateway | None |
| Provider dependency | None |
| Payment method | `QRIS`, `CASH` |
| Payment status | `PENDING`, `WAITING_VERIFICATION`, `PAID` |
| QRIS proof | `proof_image` |
| CASH proof | `NULL` |
| QRIS verification actor | Owner |
| CASH confirmation actor | Assigned Courier |
| Verification actor field | `verified_by` |
| Verification timestamp | `verified_at` |
| Order-payment cardinality | 1:1 |
| Payment FK | `payments.order_id → orders.id` |
| Actor FK | `payments.verified_by → users.id` |
| Active QRIS | Singleton `business_settings` |
| Active QRIS count | One configuration slot |
| QRIS provider | None |
| Payment webhook | None |
| Payment transaction history | None |
| Payment migration | Not included in this document |

---

# 38. Final Database Authority Statement

Setelah finalisasi ini, payment database KYŪSUI harus dipahami sebagai:

```text
ONE ORDER
    ↓
ONE PAYMENT
    ↓
┌───────────────┬───────────────────┐
│     QRIS      │       CASH        │
├───────────────┼───────────────────┤
│ PENDING       │ PENDING           │
│ WAITING_      │       ↓           │
│ VERIFICATION  │ Courier confirms  │
│       ↓       │       ↓           │
│ Owner verifies│      PAID         │
│       ↓       │                   │
│     PAID      │                   │
└───────────────┴───────────────────┘
```

Database authority:

```text
MySQL
  ↓
payments
  ↓
authoritative payment state
```

Business authority:

```text
Laravel Backend
  ↓
Authentication
  ↓
Authorization
  ↓
Payment transition validation
  ↓
Persistence
```

Android:

```text
Request
  ↓
Display
  ↓
Refresh
```

Android bukan source of truth payment.

Final payment database architecture:

```text
NO PAYMENT GATEWAY
NO PROVIDER
NO PROVIDER TRANSACTION
NO PAYMENT TRANSACTIONS
NO WEBHOOK PAYMENT

QRIS
CASH

PENDING
WAITING_VERIFICATION
PAID

ONE ORDER
ONE PAYMENT

ONE ACTIVE QRIS CONFIGURATION
```

**Status: FINALIZED**
