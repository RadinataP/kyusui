# 05_KYUSUI_DATABASE_SCHEMA.md

**Project:** KYŪSUI\
**Study Case:** Berkah Water\
**Document:** `05_KYUSUI_DATABASE_SCHEMA.md`\
**Status:** Rebuilt --- Payment Architecture Synchronized\
**Database:** MySQL 8.x\
**Backend:** Laravel 13 / PHP 8.3+\
**Authority:** `00_KYUSUI_MASTER_SPECIFICATION.md` →
`01_KYUSUI_PROJECT_RULES.md` → `02_KYUSUI_SYSTEM_ARCHITECTURE.md` →
`04_KYUSUI_SYSTEM_WORKFLOW.md` → `08_KYUSUI_PAYMENT_SPECIFICATION.md`

------------------------------------------------------------------------

## 1. Purpose

Dokumen ini mendefinisikan logical dan physical database schema KYŪSUI
sebagai dasar kontrak database untuk implementasi Laravel.

Dokumen ini **bukan migration**. Tidak ada source code migration
SQL/Laravel di dalam dokumen ini.

Target desain:

-   MySQL 8.x
-   InnoDB
-   `utf8mb4`
-   foreign key enforcement
-   referential integrity
-   transactional consistency
-   index sesuai pola query
-   historical financial data yang tidak dihapus secara sembarangan
-   ownership/authorization yang dapat divalidasi backend
-   tracking location yang dapat menyimpan histori
-   kompatibel dengan Eloquent/Laravel 13
-   payment sederhana tanpa payment gateway
-   satu depot: Berkah Water

------------------------------------------------------------------------

## 2. Source of Truth dan Authority

Database schema harus konsisten dengan:

1.  `00_KYUSUI_MASTER_SPECIFICATION.md`
2.  `02_KYUSUI_SYSTEM_ARCHITECTURE.md`
3.  `04_KYUSUI_SYSTEM_WORKFLOW.md`
4.  `08_KYUSUI_PAYMENT_SPECIFICATION.md`

Payment specification terbaru menjadi authority untuk domain payment.
Dokumen payment rebuilt menetapkan hanya `QRIS` dan `CASH`, tiga
canonical payment status, satu payment record per order, tidak ada
payment gateway, dan tidak ada `payment_transactions`.
fileciteturn0file17L21-L29 fileciteturn0file17L312-L350

Workflow rebuilt juga menetapkan QRIS menggunakan proof dan verifikasi
Owner, sedangkan CASH dikonfirmasi Courier yang ditugaskan.
fileciteturn0file18L106-L171 fileciteturn0file18L201-L238

------------------------------------------------------------------------

# 3. Global Database Conventions

## 3.1 Primary Key

Seluruh primary key internal menggunakan:

``` text
BIGINT UNSIGNED AUTO_INCREMENT
```

Laravel `id()` dapat digunakan untuk implementasi.

## 3.2 Foreign Key Naming

Gunakan:

``` text
<entity>_id
```

Contoh:

``` text
user_id
role_id
customer_id
order_id
product_id
payment_id
courier_id
courier_assignment_id
verified_by
```

## 3.3 Engine

Semua tabel menggunakan:

``` text
InnoDB
```

## 3.4 Character Set

Gunakan:

``` text
utf8mb4
```

Collation harus konsisten dengan environment MySQL/Laravel.

## 3.5 Monetary Data

Seluruh nilai uang menggunakan:

``` text
DECIMAL(15,2)
```

Jangan menggunakan `FLOAT` atau `DOUBLE` untuk nominal finansial.

## 3.6 Coordinates

Latitude/longitude:

``` text
DECIMAL(10,7)
```

Accuracy:

``` text
DECIMAL(8,2)
```

## 3.7 Timestamps

Tabel utama menggunakan:

``` text
created_at
updated_at
```

Timestamp bisnis tambahan hanya digunakan jika memiliki makna event yang
berbeda.

------------------------------------------------------------------------

# 4. Final Domain Model

KYŪSUI tetap single-depot. Tidak dibuat `depots` dan tidak menambahkan
`depot_id` ke tabel transaksi.

Relationship utama:

``` text
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
  └── singleton depot configuration
```

Payment architecture final:

``` text
ORDER
  │
  │ 1 : 1
  ▼
PAYMENT
```

Tidak ada:

``` text
PAYMENT
  │
  └── PAYMENT_TRANSACTIONS
```

Payment specification rebuilt secara eksplisit menyatakan bahwa active
payment architecture tidak memiliki `PaymentTransaction`.
fileciteturn0file17L228-L250

------------------------------------------------------------------------

# 5. Table: roles

## Purpose

Master role authorization.

## Columns

  Column         Datatype             PK   FK   Null Default               Unique Index
  -------------- ----------------- ----- ---- ------ ------------------- -------- --------
  id             BIGINT UNSIGNED     Yes   \-     No AUTO_INCREMENT           Yes PK
  name           VARCHAR(50)          No   \-     No \-                       Yes UNIQUE
  display_name   VARCHAR(100)         No   \-     No \-                        No \-
  created_at     TIMESTAMP            No   \-     No CURRENT_TIMESTAMP         No \-
  updated_at     TIMESTAMP            No   \-     No CURRENT_TIMESTAMP         No \-

Allowed values:

``` text
CUSTOMER
OWNER
COURIER
```

## Relationship

``` text
roles 1 ──── N users
```

## Delete Behavior

``` text
users.role_id
ON DELETE RESTRICT
ON UPDATE CASCADE
```

Role tidak boleh dihapus jika masih direferensikan.

------------------------------------------------------------------------

# 6. Table: users

## Purpose

Identity, authentication, dan account lifecycle.

## Columns

  Column              Datatype             PK         FK   Null Default               Unique Index
  ------------------- ----------------- ----- ---------- ------ ------------------- -------- --------
  id                  BIGINT UNSIGNED     Yes         \-     No AUTO_INCREMENT           Yes PK
  role_id             BIGINT UNSIGNED      No   roles.id     No \-                        No INDEX
  name                VARCHAR(150)         No         \-     No \-                        No INDEX
  email               VARCHAR(191)         No         \-    Yes NULL                   Yes\* UNIQUE
  phone               VARCHAR(30)          No         \-     No \-                       Yes UNIQUE
  password            VARCHAR(255)         No         \-     No \-                        No \-
  status              VARCHAR(30)          No         \-     No ACTIVE                    No INDEX
  email_verified_at   TIMESTAMP            No         \-    Yes NULL                      No \-
  last_login_at       TIMESTAMP            No         \-    Yes NULL                      No INDEX
  created_at          TIMESTAMP            No         \-     No CURRENT_TIMESTAMP         No \-
  updated_at          TIMESTAMP            No         \-     No CURRENT_TIMESTAMP         No \-
  deleted_at          TIMESTAMP            No         \-    Yes NULL                      No INDEX

`email` nullable tetapi unique jika memiliki nilai.

Recommended account status:

``` text
ACTIVE
INACTIVE
SUSPENDED
```

## Relationship

``` text
users 1 ──── 0..1 customers
users 1 ──── 0..1 owners
users 1 ──── 0..1 couriers
users 1 ──── N notifications
users 1 ──── N order_status_histories
```

## Delete Behavior

User menggunakan soft delete.

Referensi ke user yang dibutuhkan untuk histori menggunakan `RESTRICT`
atau `SET NULL` sesuai fungsi FK.

------------------------------------------------------------------------

# 7. Table: customers

## Purpose

Customer-specific profile dan default delivery information.

## Columns

  Column       Datatype             PK         FK   Null Default               Unique Index
  ------------ ----------------- ----- ---------- ------ ------------------- -------- --------
  id           BIGINT UNSIGNED     Yes         \-     No AUTO_INCREMENT           Yes PK
  user_id      BIGINT UNSIGNED      No   users.id     No \-                       Yes UNIQUE
  address      VARCHAR(500)        Yes         \-    Yes NULL                      No \-
  latitude     DECIMAL(10,7)       Yes         \-    Yes NULL                      No INDEX
  longitude    DECIMAL(10,7)       Yes         \-    Yes NULL                      No INDEX
  created_at   TIMESTAMP            No         \-     No CURRENT_TIMESTAMP         No \-
  updated_at   TIMESTAMP            No         \-     No CURRENT_TIMESTAMP         No \-
  deleted_at   TIMESTAMP            No         \-    Yes NULL                      No INDEX

Lokasi customer pada profile bersifat default/optional. Lokasi delivery
authoritative disimpan pada `orders`.

## Relationship

``` text
users 1 ──── 1 customers
customers 1 ──── N orders
```

## Delete Behavior

``` text
customers.user_id
ON DELETE RESTRICT
ON UPDATE CASCADE
```

------------------------------------------------------------------------

# 8. Table: owners

## Purpose

Owner-specific profile.

Tabel `owners` tidak menjadi tabel depot dan tidak menyimpan QRIS
depot-level. QRIS adalah konfigurasi bisnis Berkah Water, bukan atribut
individual owner.

## Columns

  Column       Datatype             PK         FK   Null Default               Unique Index
  ------------ ----------------- ----- ---------- ------ ------------------- -------- --------
  id           BIGINT UNSIGNED     Yes         \-     No AUTO_INCREMENT           Yes PK
  user_id      BIGINT UNSIGNED      No   users.id     No \-                       Yes UNIQUE
  created_at   TIMESTAMP            No         \-     No CURRENT_TIMESTAMP         No \-
  updated_at   TIMESTAMP            No         \-     No CURRENT_TIMESTAMP         No \-
  deleted_at   TIMESTAMP            No         \-    Yes NULL                      No INDEX

## Relationship

``` text
users 1 ──── 1 owners
```

------------------------------------------------------------------------

# 9. Table: couriers

## Purpose

Courier profile dan operational availability.

## Columns

  ------------------------------------------------------------------------------------------------------------
  Column                Datatype              PK         FK       Null Default                 Unique Index
  --------------------- ------------- ---------- ---------- ---------- ------------------- ---------- --------
  id                    BIGINT               Yes         \-         No AUTO_INCREMENT             Yes PK
                        UNSIGNED                                                                      

  user_id               BIGINT                No   users.id         No \-                         Yes UNIQUE
                        UNSIGNED                                                                      

  availability_status   VARCHAR(30)           No         \-         No AVAILABLE                   No INDEX

  created_at            TIMESTAMP             No         \-         No CURRENT_TIMESTAMP           No \-

  updated_at            TIMESTAMP             No         \-         No CURRENT_TIMESTAMP           No \-

  deleted_at            TIMESTAMP             No         \-        Yes NULL                        No INDEX
  ------------------------------------------------------------------------------------------------------------

Recommended values:

``` text
AVAILABLE
UNAVAILABLE
```

`availability_status` bukan delivery/payment status.

## Relationship

``` text
users 1 ──── 1 couriers
couriers 1 ──── N courier_assignments
couriers 1 ──── N courier_locations
```

------------------------------------------------------------------------

# 10. Table: products

## Purpose

Product/galon catalog.

## Columns

  -------------------------------------------------------------------------------------------------------------
  Column                Datatype                PK         FK       Null Default                 Unique Index
  --------------------- --------------- ---------- ---------- ---------- ------------------- ---------- -------
  id                    BIGINT UNSIGNED        Yes         \-         No AUTO_INCREMENT             Yes PK

  name                  VARCHAR(150)            No         \-         No \-                          No INDEX

  description           VARCHAR(500)           Yes         \-        Yes NULL                        No \-

  price                 DECIMAL(15,2)           No         \-         No \-                          No \-

  availability_status   VARCHAR(30)             No         \-         No AVAILABLE                   No INDEX

  created_at            TIMESTAMP               No         \-         No CURRENT_TIMESTAMP           No \-

  updated_at            TIMESTAMP               No         \-         No CURRENT_TIMESTAMP           No \-

  deleted_at            TIMESTAMP               No         \-        Yes NULL                        No INDEX
  -------------------------------------------------------------------------------------------------------------

Recommended availability:

``` text
AVAILABLE
UNAVAILABLE
```

Historical price order disimpan di `order_items.unit_price`.

## Relationship

``` text
products 1 ──── N order_items
```

------------------------------------------------------------------------

# 11. Table: orders

## Purpose

Core business transaction pemesanan.

## Columns

  -------------------------------------------------------------------------------------------------------------------
  Column               Datatype                PK             FK       Null Default                   Unique Index
  -------------------- --------------- ---------- -------------- ---------- --------------------- ---------- --------
  id                   BIGINT UNSIGNED        Yes             \-         No AUTO_INCREMENT               Yes PK

  order_number         VARCHAR(40)             No             \-         No \-                           Yes UNIQUE

  customer_id          BIGINT UNSIGNED         No   customers.id         No \-                            No INDEX

  delivery_address     VARCHAR(500)            No             \-         No \-                            No \-

  delivery_latitude    DECIMAL(10,7)           No             \-         No \-                            No INDEX

  delivery_longitude   DECIMAL(10,7)           No             \-         No \-                            No INDEX

  subtotal_amount      DECIMAL(15,2)           No             \-         No 0.00                          No \-

  delivery_fee         DECIMAL(15,2)           No             \-         No 0.00                          No \-

  total_amount         DECIMAL(15,2)           No             \-         No 0.00                          No \-

  order_status         VARCHAR(40)             No             \-         No MENUNGGU_PEMBAYARAN           No INDEX

  placed_at            TIMESTAMP               No             \-         No CURRENT_TIMESTAMP             No INDEX

  processed_at         TIMESTAMP               No             \-        Yes NULL                          No INDEX

  completed_at         TIMESTAMP               No             \-        Yes NULL                          No INDEX

  created_at           TIMESTAMP               No             \-         No CURRENT_TIMESTAMP             No \-

  updated_at           TIMESTAMP               No             \-         No CURRENT_TIMESTAMP             No \-

  deleted_at           TIMESTAMP               No             \-        Yes NULL                          No INDEX
  -------------------------------------------------------------------------------------------------------------------

Canonical order status:

``` text
MENUNGGU_PEMBAYARAN
MENUNGGU_DIPROSES
DIPROSES
DITUGASKAN
DALAM_PENGANTARAN
SELESAI
```

Payment architecture tidak mengubah struktur order non-payment.

## Relationship

``` text
customers 1 ──── N orders
orders 1 ──── N order_items
orders 1 ──── N order_status_histories
orders 1 ──── 1 payments
orders 1 ──── N courier_assignments
```

## Delete Behavior

``` text
orders.customer_id
ON DELETE RESTRICT
ON UPDATE CASCADE
```

Order menggunakan soft delete. Financial/delivery records tidak boleh
hilang karena hard delete order.

------------------------------------------------------------------------

# 12. Table: order_items

## Purpose

Snapshot product line ketika order dibuat.

## Columns

  ---------------------------------------------------------------------------------------------------------
  Column         Datatype                PK            FK       Null Default                 Unique Index
  -------------- --------------- ---------- ------------- ---------- ------------------- ---------- -------
  id             BIGINT UNSIGNED        Yes            \-         No AUTO_INCREMENT             Yes PK

  order_id       BIGINT UNSIGNED         No     orders.id         No \-                          No INDEX

  product_id     BIGINT UNSIGNED         No   products.id         No \-                          No INDEX

  product_name   VARCHAR(150)            No            \-         No \-                          No \-

  quantity       UNSIGNED INT            No            \-         No \-                          No \-

  unit_price     DECIMAL(15,2)           No            \-         No \-                          No \-

  line_total     DECIMAL(15,2)           No            \-         No \-                          No \-

  created_at     TIMESTAMP               No            \-         No CURRENT_TIMESTAMP           No \-

  updated_at     TIMESTAMP               No            \-         No CURRENT_TIMESTAMP           No \-
  ---------------------------------------------------------------------------------------------------------

Invariant:

``` text
line_total = quantity × unit_price
```

`product_name` dan `unit_price` merupakan snapshot historis.

## Delete Behavior

``` text
order_id
ON DELETE CASCADE
ON UPDATE CASCADE

product_id
ON DELETE RESTRICT
ON UPDATE CASCADE
```

------------------------------------------------------------------------

# 13. Table: order_status_histories

## Purpose

Audit trail perubahan status order.

## Columns

  ------------------------------------------------------------------------------------------------------------
  Column               Datatype               PK          FK       Null Default                 Unique Index
  -------------------- -------------- ---------- ----------- ---------- ------------------- ---------- -------
  id                   BIGINT                Yes          \-         No AUTO_INCREMENT             Yes PK
                       UNSIGNED                                                                        

  order_id             BIGINT                 No   orders.id         No \-                          No INDEX
                       UNSIGNED                                                                        

  from_status          VARCHAR(40)           Yes          \-        Yes NULL                        No \-

  to_status            VARCHAR(40)            No          \-         No \-                          No INDEX

  changed_by_user_id   BIGINT                Yes    users.id        Yes NULL                        No INDEX
                       UNSIGNED                                                                        

  note                 VARCHAR(500)          Yes          \-        Yes NULL                        No \-

  changed_at           TIMESTAMP              No          \-         No CURRENT_TIMESTAMP           No INDEX

  created_at           TIMESTAMP              No          \-         No CURRENT_TIMESTAMP           No \-
  ------------------------------------------------------------------------------------------------------------

`changed_by_user_id` dapat NULL untuk perubahan yang dilakukan oleh
system process.

## Relationship

``` text
orders 1 ──── N order_status_histories
users 1 ──── N order_status_histories
```

## Delete Behavior

``` text
order_id
ON DELETE CASCADE
ON UPDATE CASCADE

changed_by_user_id
ON DELETE SET NULL
ON UPDATE CASCADE
```

------------------------------------------------------------------------

# 14. Table: payments

## Purpose

Satu-satunya business payment record untuk setiap order.

Payment specification final menetapkan relationship Order--Payment 1:1
dan menjadikan `payments` sebagai source of truth payment.
fileciteturn0file17L266-L308

## Columns

  ----------------------------------------------------------------------------------------------------------
  Column           Datatype                PK          FK       Null Default                 Unique Index
  ---------------- --------------- ---------- ----------- ---------- ------------------- ---------- --------
  id               BIGINT UNSIGNED        Yes          \-         No AUTO_INCREMENT             Yes PK

  order_id         BIGINT UNSIGNED         No   orders.id         No \-                         Yes UNIQUE

  payment_method   VARCHAR(20)             No          \-         No \-                          No INDEX

  payment_status   VARCHAR(30)             No          \-         No PENDING                     No INDEX

  amount           DECIMAL(15,2)           No          \-         No 0.00                        No \-

  proof_image      VARCHAR(500)           Yes          \-        Yes NULL                        No \-

  verified_by      BIGINT UNSIGNED        Yes    users.id        Yes NULL                        No INDEX

  verified_at      TIMESTAMP              Yes          \-        Yes NULL                        No INDEX

  created_at       TIMESTAMP               No          \-         No CURRENT_TIMESTAMP           No \-

  updated_at       TIMESTAMP               No          \-         No CURRENT_TIMESTAMP           No \-
  ----------------------------------------------------------------------------------------------------------

Tidak ada:

``` text
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

Field provider dan transaction tersebut dihapus karena architecture baru
tidak menggunakan payment provider/gateway/webhook. Payment
specification rebuilt secara eksplisit menghapus provider transaction
dan webhook dari active architecture. fileciteturn0file17L104-L119

## Payment Method

Canonical values:

``` text
QRIS
CASH
```

Tidak digunakan:

``` text
DIGITAL
MIDTRANS
PAYMENT_GATEWAY
```

## Payment Status

Canonical values:

``` text
PENDING
WAITING_VERIFICATION
PAID
```

Tidak digunakan:

``` text
PROCESSING
CONFIRMED
FAILED
EXPIRED
```

Payment specification final menetapkan tiga status tersebut dan
menghapus status provider lama. fileciteturn0file17L312-L350

## QRIS Rules

``` text
payment_method = QRIS
```

mengizinkan:

``` text
PENDING
WAITING_VERIFICATION
PAID
```

Flow:

``` text
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

Jika proof ditolak:

``` text
WAITING_VERIFICATION
    ↓
Owner rejects
    ↓
PENDING
```

`proof_image` berisi reference/path bukti pembayaran QRIS.

## CASH Rules

``` text
payment_method = CASH
```

menggunakan:

``` text
proof_image = NULL
```

Flow:

``` text
PENDING
    ↓
Customer pays Courier
    ↓
Assigned Courier confirms
    ↓
PAID
```

Payment specification final menetapkan Courier sebagai actor konfirmasi
Cash dan bukan Owner. fileciteturn0file17L197-L210

## verified_by

`verified_by` mereferensikan:

``` text
users.id
```

Makna berdasarkan payment method:

``` text
QRIS → Owner user
CASH → Assigned Courier user
```

Backend harus memvalidasi role dan ownership/assignment sebelum menerima
perubahan status.

Database menyimpan actor sebagai user karena actor QRIS dan Cash berasal
dari tabel identity yang sama.

## Relationship

``` text
orders 1 ──── 1 payments
users 1 ──── N payments
```

## Delete Behavior

``` text
payments.order_id
ON DELETE RESTRICT
ON UPDATE CASCADE

payments.verified_by
ON DELETE SET NULL
ON UPDATE CASCADE
```

Payment tidak boleh cascade-delete karena merupakan financial/business
record.

Jika user actor dihapus/soft-deleted, histori payment tetap ada;
`verified_by` dapat menjadi NULL hanya untuk menjaga referential
integrity ketika hard deletion benar-benar dilakukan.

------------------------------------------------------------------------

# 15. Table: business_settings

## Purpose

Menyimpan konfigurasi bisnis level depot yang tidak tepat ditempatkan
pada `users`, `owners`, atau `orders`.

Audit terhadap schema lama menunjukkan tidak terdapat table
configuration/settings yang dapat digunakan. Schema lama hanya memiliki
`owners` sebagai profile owner dan tidak memiliki struktur business
configuration. Karena QRIS merupakan konfigurasi depot-level dan KYŪSUI
hanya memiliki satu depot, diperlukan satu struktur configuration
minimal.

Tabel ini **bukan** tabel multi-depot dan **bukan** tabel
multi-merchant.

## Singleton Design

Hanya satu record konfigurasi aktif yang digunakan:

``` text
id = 1
```

Konfigurasi tidak memiliki `depot_id`.

## Columns

  ---------------------------------------------------------------------------------------------------
  Column       Datatype               PK         FK       Null Default                 Unique Index
  ------------ -------------- ---------- ---------- ---------- ------------------- ---------- -------
  id           BIGINT                Yes         \-         No 1                          Yes PK
               UNSIGNED                                                                       

  qris_image   VARCHAR(500)          Yes         \-        Yes NULL                        No \-

  updated_by   BIGINT                Yes   users.id        Yes NULL                        No INDEX
               UNSIGNED                                                                       

  created_at   TIMESTAMP              No         \-         No CURRENT_TIMESTAMP           No \-

  updated_at   TIMESTAMP              No         \-         No CURRENT_TIMESTAMP           No \-
  ---------------------------------------------------------------------------------------------------

## QRIS Configuration Rules

`qris_image` adalah reference/path terhadap gambar static QRIS aktif
Berkah Water.

Business invariant:

``` text
business_settings.id = 1
```

dan:

``` text
business_settings.qris_image
```

merepresentasikan satu QRIS aktif.

Tidak ada:

``` text
qris_accounts
qris_merchants
qris_providers
depot_id
merchant_id
```

## Access Rules

Customer:

``` text
READ ONLY
```

Owner:

``` text
READ
REPLACE
```

Courier:

``` text
NO ACCESS REQUIRED
```

Backend:

``` text
AUTHORIZATION
VALIDATION
PERSISTENCE
```

## Replacement Semantics

Penggantian QRIS memperbarui:

``` text
business_settings.qris_image
```

Tidak membuat record QRIS baru.

Tidak membuat history table khusus QRIS karena kebutuhan bisnis hanya
memerlukan satu QRIS aktif dan tidak menetapkan versioning/history QRIS.

Jika audit history QRIS kelak menjadi requirement, itu harus menjadi
perubahan schema terkontrol.

## Relationship

``` text
users 1 ──── N business_settings
```

Secara business scope hanya satu configuration row.

## Delete Behavior

``` text
business_settings.updated_by
ON DELETE SET NULL
ON UPDATE CASCADE
```

`business_settings` tidak dihapus sebagai bagian dari lifecycle user.

------------------------------------------------------------------------

# 16. Table: courier_assignments

## Purpose

Assignment order kepada courier.

## Columns

  -------------------------------------------------------------------------------------------------------
  Column         Datatype              PK            FK       Null Default                 Unique Index
  -------------- ------------- ---------- ------------- ---------- ------------------- ---------- -------
  id             BIGINT               Yes            \-         No AUTO_INCREMENT             Yes PK
                 UNSIGNED                                                                         

  order_id       BIGINT                No     orders.id         No \-                          No INDEX
                 UNSIGNED                                                                         

  courier_id     BIGINT                No   couriers.id         No \-                          No INDEX
                 UNSIGNED                                                                         

  status         VARCHAR(30)           No            \-         No ASSIGNED                    No INDEX

  assigned_at    TIMESTAMP             No            \-         No CURRENT_TIMESTAMP           No INDEX

  started_at     TIMESTAMP            Yes            \-        Yes NULL                        No INDEX

  completed_at   TIMESTAMP            Yes            \-        Yes NULL                        No INDEX

  created_at     TIMESTAMP             No            \-         No CURRENT_TIMESTAMP           No \-

  updated_at     TIMESTAMP             No            \-         No CURRENT_TIMESTAMP           No \-

  deleted_at     TIMESTAMP             No            \-        Yes NULL                        No INDEX
  -------------------------------------------------------------------------------------------------------

Assignment status:

``` text
ASSIGNED
ACTIVE
COMPLETED
```

Tidak menambahkan `REJECTED` karena belum menjadi requirement.

## Business Invariant

Untuk satu order:

``` text
maximum one active assignment
```

Validasi active assignment dilakukan pada backend/service transaction
layer.

## Relationship

``` text
orders 1 ──── N courier_assignments
couriers 1 ──── N courier_assignments
```

## Delete Behavior

``` text
courier_assignments.order_id
ON DELETE RESTRICT
ON UPDATE CASCADE

courier_assignments.courier_id
ON DELETE RESTRICT
ON UPDATE CASCADE
```

------------------------------------------------------------------------

# 17. Table: courier_locations

## Purpose

Histori lokasi courier untuk delivery tracking.

## Columns

  -----------------------------------------------------------------------------------------------------------------------------
  Column                  Datatype                PK                       FK       Null Default                 Unique Index
  ----------------------- --------------- ---------- ------------------------ ---------- ------------------- ---------- -------
  id                      BIGINT UNSIGNED        Yes                       \-         No AUTO_INCREMENT             Yes PK

  courier_assignment_id   BIGINT UNSIGNED         No   courier_assignments.id         No \-                          No INDEX

  courier_id              BIGINT UNSIGNED         No              couriers.id         No \-                          No INDEX

  latitude                DECIMAL(10,7)           No                       \-         No \-                          No \-

  longitude               DECIMAL(10,7)           No                       \-         No \-                          No \-

  accuracy_meters         DECIMAL(8,2)           Yes                       \-        Yes NULL                        No \-

  recorded_at             TIMESTAMP               No                       \-         No CURRENT_TIMESTAMP           No INDEX

  created_at              TIMESTAMP               No                       \-         No CURRENT_TIMESTAMP           No \-
  -----------------------------------------------------------------------------------------------------------------------------

Required composite index:

``` text
(courier_assignment_id, recorded_at)
```

Latest location query:

``` text
WHERE courier_assignment_id = ?
ORDER BY recorded_at DESC
LIMIT 1
```

Backend wajib memvalidasi bahwa:

``` text
authenticated courier
=
courier_assignment.courier_id
```

dan assignment berada dalam delivery context yang valid.

## Relationship

``` text
courier_assignments 1 ──── N courier_locations
couriers 1 ──── N courier_locations
```

## Delete Behavior

``` text
courier_locations.courier_assignment_id
ON DELETE RESTRICT
ON UPDATE CASCADE

courier_locations.courier_id
ON DELETE RESTRICT
ON UPDATE CASCADE
```

------------------------------------------------------------------------

# 18. Table: notifications

## Purpose

Persistent notification record.

Notification bukan source of truth untuk payment/order/tracking.

## Columns

  ---------------------------------------------------------------------------------------------------
  Column       Datatype               PK         FK       Null Default                 Unique Index
  ------------ -------------- ---------- ---------- ---------- ------------------- ---------- -------
  id           BIGINT                Yes         \-         No AUTO_INCREMENT             Yes PK
               UNSIGNED                                                                       

  user_id      BIGINT                 No   users.id         No \-                          No INDEX
               UNSIGNED                                                                       

  type         VARCHAR(100)           No         \-         No \-                          No INDEX

  title        VARCHAR(150)           No         \-         No \-                          No \-

  body         VARCHAR(500)           No         \-         No \-                          No \-

  data         JSON                  Yes         \-        Yes NULL                        No \-

  read_at      TIMESTAMP             Yes         \-        Yes NULL                        No INDEX

  sent_at      TIMESTAMP             Yes         \-        Yes NULL                        No INDEX

  created_at   TIMESTAMP              No         \-         No CURRENT_TIMESTAMP           No INDEX

  updated_at   TIMESTAMP              No         \-         No CURRENT_TIMESTAMP           No \-
  ---------------------------------------------------------------------------------------------------

`data` hanya boleh berisi routing/context non-secret.

Contoh:

``` json
{
  "order_id": 123,
  "payment_id": 456
}
```

Jangan menyimpan:

``` text
password
token
payment secret
credential
private payment evidence
```

## Relationship

``` text
users 1 ──── N notifications
```

## Delete Behavior

``` text
notifications.user_id
ON DELETE CASCADE
ON UPDATE CASCADE
```

Notification bukan financial record.

------------------------------------------------------------------------

# 19. Foreign Key Matrix

  -------------------------------------------------------------------------------------------------------
  Child Table              FK                      Parent                   Delete         Update
  ------------------------ ----------------------- ------------------------ -------------- --------------
  users                    role_id                 roles.id                 RESTRICT       CASCADE

  customers                user_id                 users.id                 RESTRICT       CASCADE

  owners                   user_id                 users.id                 RESTRICT       CASCADE

  couriers                 user_id                 users.id                 RESTRICT       CASCADE

  orders                   customer_id             customers.id             RESTRICT       CASCADE

  order_items              order_id                orders.id                CASCADE        CASCADE

  order_items              product_id              products.id              RESTRICT       CASCADE

  order_status_histories   order_id                orders.id                CASCADE        CASCADE

  order_status_histories   changed_by_user_id      users.id                 SET NULL       CASCADE

  payments                 order_id                orders.id                RESTRICT       CASCADE

  payments                 verified_by             users.id                 SET NULL       CASCADE

  business_settings        updated_by              users.id                 SET NULL       CASCADE

  courier_assignments      order_id                orders.id                RESTRICT       CASCADE

  courier_assignments      courier_id              couriers.id              RESTRICT       CASCADE

  courier_locations        courier_assignment_id   courier_assignments.id   RESTRICT       CASCADE

  courier_locations        courier_id              couriers.id              RESTRICT       CASCADE

  notifications            user_id                 users.id                 CASCADE        CASCADE
  -------------------------------------------------------------------------------------------------------

Tidak ada FK menuju:

``` text
payment_transactions
provider tables
provider transaction tables
```

karena entity tersebut sudah dihapus dari active schema.

------------------------------------------------------------------------

# 20. Index Strategy

## users

``` text
UNIQUE(email)
UNIQUE(phone)
INDEX(role_id)
INDEX(status)
INDEX(last_login_at)
```

## customers

``` text
UNIQUE(user_id)
```

## owners

``` text
UNIQUE(user_id)
```

## couriers

``` text
UNIQUE(user_id)
INDEX(availability_status)
```

## products

``` text
INDEX(name)
INDEX(availability_status)
```

## orders

``` text
UNIQUE(order_number)
INDEX(customer_id)
INDEX(order_status)
INDEX(placed_at)
INDEX(completed_at)
INDEX(customer_id, order_status, placed_at)
```

## order_items

``` text
INDEX(order_id)
INDEX(product_id)
```

## order_status_histories

``` text
INDEX(order_id)
INDEX(order_id, changed_at)
INDEX(to_status)
INDEX(changed_by_user_id)
```

## payments

``` text
UNIQUE(order_id)
INDEX(payment_method)
INDEX(payment_status)
INDEX(verified_by)
INDEX(verified_at)
```

Tidak ada:

``` text
provider_reference
provider_transaction_id
transaction_id
idempotency_key
```

## business_settings

``` text
INDEX(updated_by)
```

Tidak diperlukan unique index pada `qris_image` karena value tersebut
bukan identity.

## courier_assignments

``` text
INDEX(order_id)
INDEX(courier_id)
INDEX(status)
INDEX(courier_id, status)
```

## courier_locations

``` text
INDEX(courier_assignment_id)
INDEX(courier_id)
INDEX(recorded_at)
INDEX(courier_assignment_id, recorded_at)
INDEX(courier_id, recorded_at)
```

## notifications

``` text
INDEX(user_id)
INDEX(user_id, read_at, created_at)
INDEX(type)
INDEX(sent_at)
```

------------------------------------------------------------------------

# 21. Soft Delete Policy

Soft delete:

``` text
users
customers
owners
couriers
products
orders
courier_assignments
```

Tidak menggunakan soft delete:

``` text
roles
order_items
order_status_histories
payments
business_settings
courier_locations
notifications
```

Alasan:

-   role adalah master/reference;
-   order items adalah child transactional data;
-   order status history adalah audit trail;
-   payment adalah financial/business record;
-   business settings adalah singleton configuration;
-   courier location adalah tracking history;
-   notification memiliki lifecycle retention sendiri.

------------------------------------------------------------------------

# 22. Timestamp Policy

## orders

``` text
placed_at
processed_at
completed_at
```

## payments

``` text
verified_at
```

Tidak menggunakan `paid_at` terpisah.

Alasan: payment architecture baru memiliki satu final state `PAID`, dan
`verified_at` menjadi timestamp ketika payment dikonfirmasi secara sah.

## business_settings

``` text
created_at
updated_at
```

`updated_at` menunjukkan waktu QRIS configuration terakhir diganti.

## courier_assignments

``` text
assigned_at
started_at
completed_at
```

## courier_locations

``` text
recorded_at
```

## notifications

``` text
sent_at
read_at
```

------------------------------------------------------------------------

# 23. Order Data Integrity

Backend harus menjamin:

``` text
subtotal_amount
=
SUM(order_items.line_total)
```

dan:

``` text
line_total
=
quantity × unit_price
```

dan:

``` text
total_amount
=
subtotal_amount + delivery_fee
```

Android bukan source of truth nominal order.

------------------------------------------------------------------------

# 24. Payment Data Integrity

Backend harus menjamin:

``` text
payments.amount
=
orders.total_amount
```

Payment tidak boleh dibuat tanpa order.

Karena:

``` text
orders.id
```

memiliki unique reference pada:

``` text
payments.order_id
```

maka satu order hanya dapat memiliki satu payment record.

## QRIS

Valid invariant:

``` text
payment_method = QRIS
AND payment_status = PENDING
→ proof_image may be NULL
```

``` text
payment_method = QRIS
AND payment_status = WAITING_VERIFICATION
→ proof_image IS NOT NULL
```

``` text
payment_method = QRIS
AND payment_status = PAID
→ proof_image IS NOT NULL
AND verified_by references Owner
AND verified_at IS NOT NULL
```

## CASH

Valid invariant:

``` text
payment_method = CASH
→ proof_image IS NULL
```

Jika:

``` text
payment_method = CASH
AND payment_status = PAID
```

maka:

``` text
verified_by
=
Assigned Courier user
```

dan:

``` text
verified_at IS NOT NULL
```

Backend wajib memvalidasi assignment Courier sebelum menerima perubahan
ke `PAID`.

------------------------------------------------------------------------

# 25. Payment and Order Synchronization

Tidak digunakan rule global:

``` text
order hanya dapat diproses jika payment = PAID
```

Aturan canonical:

## QRIS

``` text
Order
  ↓
Payment PENDING
  ↓
Upload proof
  ↓
WAITING_VERIFICATION
  ↓
Owner verifies
  ↓
PAID
  ↓
Order dapat masuk processing workflow
```

## CASH

``` text
Order
  ↓
Payment PENDING
  ↓
Order dapat diproses
  ↓
Courier delivery
  ↓
Customer pays cash
  ↓
Assigned Courier confirms
  ↓
Payment PAID
```

Payment specification rebuilt menetapkan CASH dapat tetap `PENDING`
ketika order diproses/dikirim. fileciteturn0file17L197-L210

------------------------------------------------------------------------

# 26. Payment Actor Integrity

## QRIS

``` text
Customer
  → upload proof

Owner
  → verify/reject proof

Backend
  → validates role and payment state
```

Customer tidak boleh mengubah:

``` text
WAITING_VERIFICATION → PAID
```

## CASH

``` text
Customer
  → menyerahkan uang

Assigned Courier
  → "Uang Diterima"

Backend
  → validates assignment
  → sets PAID
```

Owner tidak menjadi actor konfirmasi CASH pada architecture baru.
fileciteturn0file17L197-L210

------------------------------------------------------------------------

# 27. QRIS Configuration Integrity

Business configuration:

``` text
business_settings.id = 1
```

Only one active QRIS:

``` text
business_settings.qris_image
```

Customer:

``` text
READ ONLY
```

Owner:

``` text
READ + REPLACE
```

Courier:

``` text
NO PAYMENT CONFIGURATION ACCESS
```

Replacement:

``` text
old qris_image
      ↓
Owner replaces
      ↓
business_settings.qris_image = new reference
```

Tidak dibuat:

``` text
qris_histories
qris_merchants
qris_providers
depot_qris
```

karena requirement hanya membutuhkan satu static QRIS aktif.

------------------------------------------------------------------------

# 28. Payment Proof Integrity

`payments.proof_image` hanya digunakan untuk QRIS.

## QRIS

``` text
PENDING
↓
Customer uploads proof
↓
WAITING_VERIFICATION
```

Saat:

``` text
WAITING_VERIFICATION
```

proof tidak boleh diganti kecuali Owner menolak payment proof dan
payment kembali ke:

``` text
PENDING
```

Setelah:

``` text
PAID
```

proof tidak boleh diganti.

## CASH

``` text
proof_image = NULL
```

Tidak ada upload proof Cash.

Payment specification rebuilt menetapkan QRIS proof sebagai evidence
manual dan Cash tidak menggunakan proof image.
fileciteturn0file17L155-L169 fileciteturn0file17L197-L210

------------------------------------------------------------------------

# 29. Referential Integrity Rules

Tidak boleh terdapat:

``` text
payment tanpa order
order_item tanpa order
order_item tanpa product
order_status_history tanpa order
courier_assignment tanpa order
courier_assignment tanpa courier
courier_location tanpa assignment
courier_location tanpa courier
notification tanpa user
```

Payment actor:

``` text
payments.verified_by
```

harus selalu mengarah ke `users.id` ketika tidak NULL.

Business configuration actor:

``` text
business_settings.updated_by
```

harus mengarah ke `users.id` ketika tidak NULL.

Tidak boleh ada foreign key yang menunjuk ke tabel provider/payment
transaction yang sudah dihapus.

------------------------------------------------------------------------

# 30. Transaction Boundaries

## Create Order

Logical atomic operation:

``` text
Create order
    ↓
Create order_items
    ↓
Create payment
    ↓
Create initial order_status_history
    ↓
COMMIT
```

## QRIS Proof Upload

``` text
Validate customer ownership
    ↓
Validate payment method = QRIS
    ↓
Validate status = PENDING
    ↓
Store proof reference
    ↓
Update payment status = WAITING_VERIFICATION
    ↓
COMMIT
```

## QRIS Verification

``` text
Validate Owner authorization
    ↓
Validate payment method = QRIS
    ↓
Validate status = WAITING_VERIFICATION
    ↓
Set payment status = PAID
    ↓
Set verified_by = Owner user
    ↓
Set verified_at
    ↓
COMMIT
```

## QRIS Rejection

``` text
Validate Owner authorization
    ↓
Validate payment status = WAITING_VERIFICATION
    ↓
Set payment status = PENDING
    ↓
Keep payment record
    ↓
COMMIT
```

## Cash Confirmation

``` text
Validate authenticated Courier
    ↓
Find active assignment
    ↓
Validate assignment belongs to Courier
    ↓
Validate payment method = CASH
    ↓
Validate payment status = PENDING
    ↓
Set payment status = PAID
    ↓
Set verified_by = Courier user
    ↓
Set verified_at
    ↓
COMMIT
```

## QRIS Configuration Replacement

``` text
Validate Owner authorization
    ↓
Validate singleton configuration
    ↓
Replace qris_image reference
    ↓
Set updated_by
    ↓
COMMIT
```

------------------------------------------------------------------------

# 31. Idempotency

Payment architecture baru tidak menggunakan provider webhook.

Karena itu, provider-specific idempotency tidak disimpan di database.

Tidak ada:

``` text
provider idempotency key
provider transaction id
webhook id
```

Namun endpoint/action yang dapat diulang oleh client tetap harus
memiliki business-safe behavior.

Contoh:

``` text
QRIS verify
```

Jika payment sudah:

``` text
PAID
```

request duplicate tidak boleh membuat payment record baru.

Demikian juga:

``` text
Cash confirmation
```

jika sudah:

``` text
PAID
```

request duplicate tidak boleh menghasilkan payment kedua.

Idempotency pada level request dapat ditangani pada API/service layer
tanpa membuat `payment_transactions`.

------------------------------------------------------------------------

# 32. Tracking Integrity

Location record harus memiliki:

``` text
courier_assignment_id
courier_id
latitude
longitude
recorded_at
```

Backend wajib memastikan:

``` text
authenticated courier
=
assignment courier
```

dan:

``` text
assignment
→ belongs to requested order
```

dan delivery context masih aktif.

Customer hanya dapat membaca tracking untuk order miliknya.

------------------------------------------------------------------------

# 33. Notification Integrity

Notification dibuat setelah business event berhasil dipersist/commit.

Contoh payment:

``` text
Payment state changed
       ↓
DB COMMIT
       ↓
Notification record
       ↓
FCM
```

Notification tidak menentukan payment state.

Android tetap mengambil state terbaru dari API.

Payment notification yang relevan:

``` text
QRIS proof submitted
QRIS approved
QRIS rejected
CASH confirmed
```

Tidak ada:

``` text
Midtrans webhook notification
provider callback notification
```

------------------------------------------------------------------------

# 34. ERD Mermaid

``` mermaid
erDiagram

    ROLES ||--o{ USERS : "has"

    USERS ||--o| CUSTOMERS : "profile"
    USERS ||--o| OWNERS : "profile"
    USERS ||--o| COURIERS : "profile"

    CUSTOMERS ||--o{ ORDERS : "places"

    ORDERS ||--|{ ORDER_ITEMS : "contains"
    PRODUCTS ||--o{ ORDER_ITEMS : "referenced_by"

    ORDERS ||--o{ ORDER_STATUS_HISTORIES : "tracks"
    USERS o|--o{ ORDER_STATUS_HISTORIES : "changes"

    ORDERS ||--|| PAYMENTS : "has"
    USERS o|--o{ PAYMENTS : "verifies"

    USERS o|--o{ BUSINESS_SETTINGS : "updates"

    ORDERS ||--o{ COURIER_ASSIGNMENTS : "assigned"
    COURIERS ||--o{ COURIER_ASSIGNMENTS : "receives"

    COURIER_ASSIGNMENTS ||--o{ COURIER_LOCATIONS : "produces"
    COURIERS ||--o{ COURIER_LOCATIONS : "owns"

    USERS ||--o{ NOTIFICATIONS : "receives"

    ROLES {
        bigint id PK
        varchar name UK
        varchar display_name
        timestamp created_at
        timestamp updated_at
    }

    USERS {
        bigint id PK
        bigint role_id FK
        varchar name
        varchar email UK
        varchar phone UK
        varchar password
        varchar status
        timestamp email_verified_at
        timestamp last_login_at
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    CUSTOMERS {
        bigint id PK
        bigint user_id FK,UK
        varchar address
        decimal latitude
        decimal longitude
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    OWNERS {
        bigint id PK
        bigint user_id FK,UK
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    COURIERS {
        bigint id PK
        bigint user_id FK,UK
        varchar availability_status
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    PRODUCTS {
        bigint id PK
        varchar name
        varchar description
        decimal price
        varchar availability_status
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    ORDERS {
        bigint id PK
        varchar order_number UK
        bigint customer_id FK
        varchar delivery_address
        decimal delivery_latitude
        decimal delivery_longitude
        decimal subtotal_amount
        decimal delivery_fee
        decimal total_amount
        varchar order_status
        timestamp placed_at
        timestamp processed_at
        timestamp completed_at
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    ORDER_ITEMS {
        bigint id PK
        bigint order_id FK
        bigint product_id FK
        varchar product_name
        int quantity
        decimal unit_price
        decimal line_total
        timestamp created_at
        timestamp updated_at
    }

    ORDER_STATUS_HISTORIES {
        bigint id PK
        bigint order_id FK
        varchar from_status
        varchar to_status
        bigint changed_by_user_id FK
        varchar note
        timestamp changed_at
        timestamp created_at
    }

    PAYMENTS {
        bigint id PK
        bigint order_id FK,UK
        varchar payment_method
        varchar payment_status
        decimal amount
        varchar proof_image
        bigint verified_by FK
        timestamp verified_at
        timestamp created_at
        timestamp updated_at
    }

    BUSINESS_SETTINGS {
        bigint id PK
        varchar qris_image
        bigint updated_by FK
        timestamp created_at
        timestamp updated_at
    }

    COURIER_ASSIGNMENTS {
        bigint id PK
        bigint order_id FK
        bigint courier_id FK
        varchar status
        timestamp assigned_at
        timestamp started_at
        timestamp completed_at
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    COURIER_LOCATIONS {
        bigint id PK
        bigint courier_assignment_id FK
        bigint courier_id FK
        decimal latitude
        decimal longitude
        decimal accuracy_meters
        timestamp recorded_at
        timestamp created_at
    }

    NOTIFICATIONS {
        bigint id PK
        bigint user_id FK
        varchar type
        varchar title
        varchar body
        json data
        timestamp read_at
        timestamp sent_at
        timestamp created_at
        timestamp updated_at
    }
```

------------------------------------------------------------------------

# 35. Table Dependency Order

Dokumen ini bukan migration, tetapi dependency order logical adalah:

``` text
1. roles
2. users
3. customers
4. owners
5. couriers
6. products
7. orders
8. order_items
9. order_status_histories
10. payments
11. business_settings
12. courier_assignments
13. courier_locations
14. notifications
```

Tidak ada:

``` text
payment_transactions
```

------------------------------------------------------------------------

# 36. Eloquent Relationship Mapping

Konseptual mapping:

``` text
Role
  hasMany User

User
  belongsTo Role
  hasOne Customer
  hasOne Owner
  hasOne Courier
  hasMany Notifications
  hasMany OrderStatusHistories
  hasMany Payments
  hasMany BusinessSettings

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

`business_settings` secara business scope adalah singleton, sehingga
aplikasi harus memperlakukan record tersebut sebagai satu configuration
resource, bukan collection multi-depot.

------------------------------------------------------------------------

# 37. Deliberately Not Included

## depots

Tidak dibuat karena KYŪSUI hanya memiliki satu depot:

``` text
Berkah Water
```

## payment_transactions

Dihapus karena tidak ada payment gateway/provider/webhook pada
architecture baru. fileciteturn0file17L104-L119

## provider payment fields

Dihapus:

``` text
provider_name
provider_reference
provider_transaction_id
transaction_id
transaction_status
transaction_type
raw_payload
provider idempotency key
```

Semua field tersebut hanya relevan terhadap payment provider design
lama.

## payment_methods

Tidak dibuat karena hanya ada dua method:

``` text
QRIS
CASH
```

dan belum membutuhkan master data dinamis.

## order_statuses

Tidak dibuat karena status order bersifat finite dan dikontrol backend.

## order_history

Tidak dibuat. Order history berasal dari `orders`; audit perubahan
status berasal dari `order_status_histories`.

## delivery

Tidak dibuat sebagai table terpisah. `courier_assignments` menangani
relationship operational delivery.

## qris_merchants

Tidak dibuat karena tidak ada multi-merchant requirement.

## qris_histories

Tidak dibuat karena hanya satu QRIS aktif dan versioning QRIS belum
menjadi business requirement.

## customer_addresses

Tidak dibuat pada baseline. Order menyimpan delivery address/location
snapshot.

## device_tokens

Belum ditambahkan karena device-token model bukan bagian dari payment
schema dan tidak diperlukan untuk menjaga payment integrity.

------------------------------------------------------------------------

# 38. Payment Legacy Removal Audit

Schema baru **tidak boleh** memiliki terminology aktif berikut:

``` text
MIDTRANS
DIGITAL
PAYMENT_GATEWAY
provider_name
provider_reference
provider_transaction_id
transaction_id
transaction_status
transaction_type
raw_payload
idempotency_key
payment_transactions
PROCESSING
CONFIRMED
FAILED
EXPIRED
webhook payment
provider callback
provider reconciliation
provider expiry
```

Istilah tersebut hanya boleh muncul dalam dokumentasi migration/history
jika sedang menjelaskan desain legacy yang sudah dihapus.

Active schema hanya menggunakan:

``` text
payment_method:
QRIS
CASH

payment_status:
PENDING
WAITING_VERIFICATION
PAID
```

------------------------------------------------------------------------

# 39. Payment Schema Summary

Final `payments` table:

``` text
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

Payment method:

``` text
QRIS
CASH
```

Payment status:

``` text
PENDING
WAITING_VERIFICATION
PAID
```

Relationship:

``` text
orders 1 ──── 1 payments
```

QRIS:

``` text
proof_image → allowed
verified_by → Owner
```

CASH:

``` text
proof_image → NULL
verified_by → Assigned Courier
```

------------------------------------------------------------------------

# 40. QRIS Configuration Summary

Single-depot configuration:

``` text
business_settings
└── id = 1
    ├── qris_image
    ├── updated_by
    ├── created_at
    └── updated_at
```

Customer:

``` text
READ
```

Owner:

``` text
READ
REPLACE
```

Courier:

``` text
NO ACCESS
```

Tidak ada:

``` text
depot_id
merchant_id
provider_id
qris_provider
qris_transaction
qris_payment_transaction
```

------------------------------------------------------------------------

# 41. Final Integrity Checklist

``` text
[✓] Single depot retained
[✓] Existing non-payment domain retained
[✓] orders → payments is 1:1
[✓] payments is payment source of truth
[✓] payment_transactions removed
[✓] provider fields removed
[✓] provider idempotency key removed
[✓] QRIS retained
[✓] CASH retained
[✓] PENDING retained
[✓] WAITING_VERIFICATION retained
[✓] PAID retained
[✓] PROCESSING removed
[✓] CONFIRMED removed
[✓] FAILED removed
[✓] EXPIRED removed
[✓] QRIS proof_image supported
[✓] CASH proof_image = NULL
[✓] QRIS verified_by points to Owner user
[✓] CASH verified_by points to assigned Courier user
[✓] verified_at retained
[✓] QRIS configuration stored at depot/business scope
[✓] Only one active QRIS represented
[✓] Customer QRIS access is read-only at authorization layer
[✓] Owner can replace QRIS at authorization layer
[✓] No multi-depot structure
[✓] No multi-merchant structure
[✓] No payment gateway structure
[✓] No provider webhook structure
[✓] Foreign key matrix updated
[✓] No orphan FK to removed payment entities
[✓] No migration SQL included
[✓] No implementation code included
[✓] Non-payment domain kept stable
```

------------------------------------------------------------------------

# 42. Final Schema Summary

Active tables:

``` text
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

Core business chain:

``` text
USER
 ↓
CUSTOMER
 ↓
ORDER
 ↓
ORDER_ITEMS
 ↓
PAYMENT
```

Payment configuration:

``` text
BUSINESS_SETTINGS
 ↓
ACTIVE QRIS
 ↓
CUSTOMER VIEW
```

Delivery chain:

``` text
ORDER
 ↓
COURIER_ASSIGNMENT
 ↓
COURIER_LOCATIONS
```

Notification chain:

``` text
USER
 ↓
NOTIFICATIONS
```

Final payment architecture:

``` text
                  ┌───────────────┐
                  │    PAYMENT    │
                  └───────┬───────┘
                          │
             ┌────────────┴────────────┐
             │                         │
           QRIS                       CASH
             │                         │
          PENDING                   PENDING
             │                         │
       Upload proof              Delivery
             │                         │
 WAITING_VERIFICATION             Customer pays
             │                         │
       Owner verifies             Courier confirms
             │                         │
            PAID                       PAID
```

Final principle:

``` text
Backend = business authority
MySQL = persistent source of truth
payments = single payment record
QRIS = manual proof + Owner verification
CASH = Courier confirmation
business_settings = single active QRIS configuration
```

No payment gateway, provider transaction, webhook provider, or
`payment_transactions` exists in the active database architecture.
