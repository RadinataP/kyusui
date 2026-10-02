# 11_KYUSUI_TESTING_SPECIFICATION.md

**Project:** KYŪSUI  
**Study Case:** Berkah Water  
**Document:** `11_KYUSUI_TESTING_SPECIFICATION.md`  
**Status:** REBUILT — PAYMENT ARCHITECTURE SYNCHRONIZED  
**Role:** Senior QA Architect / Test Architect  
**Platform:** Android Native  
**Backend:** Laravel 13 / PHP 8.3+  
**Database:** MySQL 8.x  
**Authentication:** Laravel Sanctum  
**Android:** Kotlin + Jetpack Compose + MVVM + ViewModel + StateFlow  
**Network:** Retrofit + OkHttp  
**Payment:** QRIS + CASH  
**Tracking:** Google Maps SDK + Fused Location Provider  
**Notification:** Firebase Cloud Messaging  

---

# 1. Purpose

Dokumen ini mendefinisikan strategi pengujian, test level, test environment, test data, test scenario, test case, acceptance criteria, security testing, integration testing, end-to-end testing, regression testing, dan release gate untuk KYŪSUI.

Testing bertujuan memastikan bahwa implementasi KYŪSUI:

1. memenuhi business requirement yang telah dikunci;
2. mengikuti system workflow yang telah disetujui;
3. mengikuti API contract;
4. mengikuti database contract;
5. menerapkan authentication dan authorization di backend;
6. menjaga backend sebagai business authority;
7. menjaga MySQL sebagai persistent source of truth;
8. menerapkan payment state transition yang benar;
9. menerapkan ownership dan courier assignment secara benar;
10. menjaga integritas data pembayaran;
11. menangani QRIS static dengan proof dan verifikasi Owner;
12. menangani CASH dengan konfirmasi Assigned Courier;
13. menjaga QRIS configuration dan replacement behavior;
14. mencegah unauthorized payment operation;
15. menangani loading, validation, network failure, server error, dan conflict;
16. menjaga notification berdasarkan committed business event;
17. menjaga tracking hanya pada delivery context yang sah;
18. menjaga konsistensi antara Android, API, backend, dan database;
19. dapat menyelesaikan business journey dari authentication sampai history;
20. mencegah regresi terhadap feature yang telah dinyatakan selesai.

Testing tidak boleh menciptakan business rule baru.

Apabila sebuah requirement belum dikunci oleh dokumen authority, test tidak boleh mengubah asumsi tersebut menjadi requirement. Test harus menandai kondisi tersebut sebagai `UNRESOLVED DECISION`.

---

# 2. Testing Authority

Testing harus mengikuti hierarchy specification KYŪSUI.

```text
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
        ↓
09_KYUSUI_TRACKING_SPECIFICATION.md
        ↓
10_KYUSUI_NOTIFICATION_SPECIFICATION.md
        ↓
11_KYUSUI_TESTING_SPECIFICATION.md
```

Untuk domain payment, `08_KYUSUI_PAYMENT_SPECIFICATION.md` menjadi authority utama.

Payment architecture yang harus diuji:

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

QRIS:

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

CASH:

```text
PENDING
    ↓
Assigned Courier confirms "Uang Diterima"
    ↓
PAID
```

`PAID` adalah terminal payment state.

Tidak boleh ada transition normal:

```text
PAID → PENDING
PAID → WAITING_VERIFICATION
```

Payment architecture tidak menggunakan payment gateway, provider transaction, provider webhook, dynamic QRIS, atau automatic provider verification.

---

# 3. System Under Test

## 3.1 Android

```text
Kotlin
Jetpack Compose
Material 3
MVVM
ViewModel
StateFlow
Repository
Navigation Compose
Retrofit
OkHttp
DataStore
Google Maps SDK
Fused Location Provider
Firebase Cloud Messaging
```

Android bertanggung jawab terhadap:

```text
UI rendering
user interaction
local UI validation
ViewModel state
repository interaction
API consumption
session handling
FCM handling
location permission
tracking presentation
```

Android bukan authority untuk:

```text
payment status
order status
payment amount
ownership
courier assignment
QRIS configuration
payment verification
Cash confirmation authorization
```

## 3.2 Backend

```text
Laravel 13
PHP 8.3+
REST API
Laravel Sanctum
Validation
Authorization / Policy
Business Services
Database Transactions
Notification Service
```

Backend harus menjadi security boundary.

## 3.3 Database

```text
MySQL 8.x
InnoDB
utf8mb4
Foreign Keys
Indexes
Transactional Consistency
```

Payment relationship:

```text
orders 1 ───── 1 payments
```

Tidak ada `payment_transactions` pada active architecture.

---

# 4. QA Objectives

Testing dibagi menjadi:

```text
Functional Correctness
Business Workflow Correctness
API Contract Correctness
Database Integrity
Authentication
Authorization
Ownership
Assignment
Payment State Correctness
QRIS Configuration
QRIS Proof Handling
Cash Confirmation
Android State Handling
UI Correctness
Tracking Security
Notification Correctness
Error Handling
Concurrency
Integration
Security
End-to-End
Regression
Acceptance
```

---

# 5. Test Levels

Testing menggunakan test pyramid.

```text
                 E2E
                /   \
          Integration
             /     \
        API / Repository
          /         \
    ViewModel / UI
         /           \
          Unit Tests
```

Prioritas:

```text
Unit
↓
Repository
↓
ViewModel
↓
API
↓
Authentication
↓
Authorization
↓
Feature Functional
↓
UI
↓
Integration
↓
Security
↓
E2E
↓
Regression
```

Unit test memiliki volume terbesar.

E2E test memiliki volume lebih kecil tetapi mencakup business-critical journey.

Security test berjalan lintas seluruh level.

---

# 6. Test Environment

## 6.1 Local Development

Digunakan untuk:

```text
Unit Test
Repository Test
ViewModel Test
API Test
UI Test
Integration Test
```

## 6.2 QA Environment

Environment QA harus menyediakan:

```text
Isolated MySQL database
Test accounts
Test products
Test orders
Test QRIS configuration
Test payment proof files
Test Owner
Test Customer
Test Courier
Android emulator/device
Controlled location simulation
FCM test configuration
```

## 6.3 Production

Production testing hanya diperbolehkan untuk:

```text
Safe smoke test
Read-only verification
Monitoring
Non-destructive validation
```

Jangan melakukan destructive testing pada production.

Jangan menggunakan data pembayaran nyata sebagai test evidence.

---

# 7. Test Account Matrix

Minimal test account:

| ID | Role | Purpose |
|---|---|---|
| TC-001 | CUSTOMER | Customer utama |
| TC-002 | CUSTOMER | Ownership isolation |
| OW-001 | OWNER | Owner utama |
| OW-002 | OWNER | Operational-scope testing bila diperlukan |
| CR-001 | COURIER | Courier utama |
| CR-002 | COURIER | Cross-courier authorization |

Test account harus mempunyai credential yang berbeda.

Tidak boleh menggunakan satu account untuk seluruh role.

---

# 8. Test Data

## 8.1 Customer

```text
Customer A
Customer B
```

## 8.2 Owner

```text
Owner A
```

## 8.3 Courier

```text
Courier A
Courier B
```

## 8.4 Product

Minimal:

```text
Product aktif
Product tidak tersedia
Product dengan harga berbeda
```

## 8.5 Payment

Minimal:

```text
QRIS PENDING
QRIS WAITING_VERIFICATION
QRIS PAID

CASH PENDING
CASH PAID
```

## 8.6 QRIS Configuration

Minimal:

```text
QRIS-A = active QRIS lama
QRIS-B = QRIS baru
```

Test replacement harus dapat membedakan QRIS-A dan QRIS-B secara jelas.

## 8.7 Payment Proof

Minimal:

```text
Valid JPG
Valid JPEG
Valid PNG
File > 5 MB
Invalid file type
Corrupt image
Missing file
```

---

# 9. Test Case Convention

Format ID:

```text
UT-xxx       Unit Test
REP-xxx      Repository Test
VM-xxx       ViewModel Test
API-xxx      API Test
AUTH-xxx     Authentication
AUTHZ-xxx    Authorization
ORD-xxx      Order
PAY-xxx      QRIS Payment
CASH-xxx     Cash Payment
QRIS-xxx     QRIS Configuration
GPS-xxx      GPS
TRK-xxx      Tracking
NOTIF-xxx    Notification
UI-xxx       UI
INT-xxx      Integration
E2E-xxx      End-to-End
SEC-xxx      Security
ERR-xxx      Error Handling
ACC-xxx      Acceptance
REG-xxx      Regression
```

Severity:

```text
BLOCKER
CRITICAL
MAJOR
MINOR
TRIVIAL
```

Priority:

```text
P0 = Release blocker
P1 = High
P2 = Medium
P3 = Low
```

---

# 10. Entry Criteria

Feature testing dapat dimulai apabila:

```text
Requirement tersedia
API contract tersedia jika diperlukan
Database contract tersedia jika diperlukan
Build berhasil
Environment tersedia
Test account tersedia
Test data tersedia
Dependency utama tersedia
No blocking compile error
```

Untuk payment testing tambahan:

```text
QRIS aktif tersedia
QRIS replacement data tersedia
Proof test files tersedia
Owner account tersedia
Courier assignment tersedia
Order test data tersedia
```

---

# 11. Exit Criteria

Feature dapat dinyatakan QA-complete apabila:

```text
Required test cases executed
Critical test cases PASS
Security tests PASS
Authorization tests PASS
Database integrity PASS
API contract PASS
UI state PASS
Integration PASS
E2E affected flow PASS
Regression PASS
No unresolved P0
No unresolved P1
Evidence stored
Build succeeds
```

---

# 12. Core Business Invariants

Invariant adalah aturan yang tidak boleh dilanggar implementasi.

## 12.1 Payment Methods

Hanya:

```text
QRIS
CASH
```

## 12.2 Payment Status

Hanya:

```text
PENDING
WAITING_VERIFICATION
PAID
```

## 12.3 QRIS

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

## 12.4 CASH

```text
PENDING
    ↓
PAID
```

Transition dilakukan melalui validasi Assigned Courier.

## 12.5 PAID

```text
PAID = terminal payment state
```

## 12.6 Payment Ownership

Customer hanya dapat melakukan payment action untuk order miliknya.

## 12.7 QRIS Verification

Hanya Owner yang dapat melakukan verification QRIS.

## 12.8 Cash Confirmation

Hanya Courier yang benar-benar assigned pada order tersebut yang dapat mengonfirmasi Cash.

## 12.9 QRIS Replacement

Customer tidak dapat mengganti QRIS.

Courier tidak dapat mengganti QRIS.

Owner dapat mengganti active QRIS.

## 12.10 Backend Authority

Android tidak boleh mengubah payment menjadi `PAID` hanya karena tombol berhasil ditekan.

---

# 13. Authentication Testing

## AUTH-001 — Valid Customer Login

Input:

```text
Customer valid credentials
```

Expected:

```text
HTTP success
Sanctum token diberikan
Customer session aktif
Role CUSTOMER diterima
```

## AUTH-002 — Invalid Password

Expected:

```text
Authentication rejected
No authenticated session
No business resource access
```

## AUTH-003 — Invalid Token

Expected:

```text
401 Unauthorized
```

## AUTH-004 — Missing Token

Expected:

```text
401 Unauthorized
```

## AUTH-005 — Expired/Invalid Session

Expected:

```text
Session rejected
Android kembali ke authentication flow sesuai implementation
```

---

# 14. Authorization Testing

## AUTHZ-001 — Customer Access Own Order

Expected:

```text
Allowed
```

## AUTHZ-002 — Customer Access Other Customer Order

Expected:

```text
Rejected
```

Resource dapat dikembalikan sebagai `404` jika API menerapkan resource hiding.

## AUTHZ-003 — Owner Operational Access

Expected:

```text
Owner dapat mengakses resource operasional Berkah Water
```

## AUTHZ-004 — Courier Own Assignment

Expected:

```text
Courier hanya dapat melihat assignment miliknya
```

## AUTHZ-005 — Courier Other Assignment

Expected:

```text
Rejected
```

---

# 15. Order Testing

## ORD-001 — Create Valid Order

Expected:

```text
Order berhasil dibuat
Order memiliki customer owner yang benar
Order total dihitung backend
Payment record dibuat
```

## ORD-002 — Invalid Quantity

Expected:

```text
Validation rejected
No invalid order created
```

## ORD-003 — Unavailable Product

Expected:

```text
Order rejected
No invalid order mutation
```

## ORD-004 — Payment Relationship

Expected:

```text
One order
↓
One business payment
```

## ORD-005 — Order History

Expected:

```text
Completed order muncul pada history
Payment information tetap konsisten
```

---

# 16. QRIS Active Configuration Testing

## QRIS-001 — Active QRIS Exists

Precondition:

```text
Owner has active QRIS
```

Expected:

```text
Customer dapat membaca active QRIS
```

## QRIS-002 — Active QRIS Display

Expected:

```text
QRIS image tampil
QRIS dapat dibaca
Nominal order tampil
Payment method = QRIS
```

## QRIS-003 — Customer Selects QRIS

Expected:

```text
Payment method = QRIS
Payment state = PENDING
```

Pemilihan QRIS tidak otomatis menghasilkan `PAID`.

## QRIS-004 — Customer Cannot Replace QRIS

Attempt:

```text
Customer calls QRIS replacement operation
```

Expected:

```text
403 / authorization rejection
Active QRIS tidak berubah
```

## QRIS-005 — Courier Cannot Replace QRIS

Expected:

```text
Rejected
Active QRIS tetap sama
```

## QRIS-006 — Owner Can Replace QRIS

Expected:

```text
Owner replacement succeeds
New QRIS becomes active
```

---

# 17. QRIS Replacement and Old/New Behavior

QRIS replacement adalah test penting karena active QRIS merupakan konfigurasi bisnis dan bukan transaksi provider.

## QRIS-007 — Old QRIS Before Replacement

Precondition:

```text
QRIS-A active
Customer opens payment screen
```

Expected:

```text
QRIS-A displayed
```

## QRIS-008 — Owner Replaces QRIS

Action:

```text
Owner replaces QRIS-A
with QRIS-B
```

Expected:

```text
QRIS-B becomes active
```

## QRIS-009 — New Customer Request After Replacement

Expected:

```text
GET active QRIS
→ QRIS-B
```

## QRIS-010 — Existing Customer Refreshes Payment Screen

Expected:

```text
Latest active QRIS is returned by backend
```

Android tidak boleh menganggap QRIS lama sebagai active configuration setelah backend menggantinya.

## QRIS-011 — Old QRIS Is No Longer Active

Expected:

```text
QRIS-A != active QRIS
QRIS-B = active QRIS
```

## QRIS-012 — Replacement Does Not Modify Existing Payment State

Precondition:

```text
Existing payment = PENDING
```

Owner replaces QRIS.

Expected:

```text
payment remains PENDING
```

QRIS replacement tidak boleh secara otomatis:

```text
→ WAITING_VERIFICATION
→ PAID
```

## QRIS-013 — Replacement Does Not Rewrite Historical Payment

Precondition:

```text
Existing QRIS payment
```

Expected:

```text
Payment record remains associated with its order
QRIS replacement does not create a new payment record
```

---

# 18. QRIS Payment State Testing

## PAY-001 — Initial QRIS State

Expected:

```text
QRIS selected
→ PENDING
```

## PAY-002 — QRIS Proof Upload

Precondition:

```text
Payment method = QRIS
Payment status = PENDING
Customer owns order
Valid proof
```

Expected:

```text
PENDING
↓
WAITING_VERIFICATION
```

## PAY-003 — QRIS Proof Upload Ownership

Customer A attempts to upload proof for Customer B.

Expected:

```text
Rejected
Payment B unchanged
```

## PAY-004 — Invalid Proof Type

Expected:

```text
Validation rejected
Payment remains PENDING
```

## PAY-005 — Proof Above Maximum Size

Expected:

```text
Validation rejected
Payment remains PENDING
```

## PAY-006 — Missing Proof

Expected:

```text
Validation rejected
Payment remains PENDING
```

## PAY-007 — Successful Proof Upload

Expected:

```text
Proof stored privately
Payment = WAITING_VERIFICATION
```

## PAY-008 — Proof Upload Notification

Expected:

```text
Owner receives QRIS verification notification
```

---

# 19. QRIS Owner Verification Testing

## PAY-009 — Owner Views Verification Queue

Expected:

```text
Owner dapat melihat QRIS payment
yang berstatus WAITING_VERIFICATION
```

## PAY-010 — Owner Opens Proof

Expected:

```text
Authorized Owner dapat melihat proof
```

## PAY-011 — Owner Approves Valid Proof

Precondition:

```text
payment = QRIS
payment_status = WAITING_VERIFICATION
proof exists
Owner authenticated
```

Expected:

```text
WAITING_VERIFICATION
↓
PAID
```

## PAY-012 — Approval Actor

Verify:

```text
verified_by = Owner user ID
```

## PAY-013 — Approval Timestamp

Verify:

```text
verified_at != NULL
```

Timestamp berasal dari backend.

## PAY-014 — Approval Notification

Expected:

```text
Customer receives PAYMENT_QRIS_APPROVED
```

## PAY-015 — Customer Cannot Verify

Expected:

```text
Rejected
payment remains WAITING_VERIFICATION
```

## PAY-016 — Courier Cannot Verify QRIS

Expected:

```text
Rejected
payment remains WAITING_VERIFICATION
```

---

# 20. QRIS Rejection Testing

## PAY-017 — Owner Rejects Proof

Precondition:

```text
WAITING_VERIFICATION
```

Expected:

```text
WAITING_VERIFICATION
↓
PENDING
```

## PAY-018 — Rejection Does Not Create Failure State

Expected:

```text
payment = PENDING
```

Tidak boleh dibuat payment status tambahan.

## PAY-019 — Rejection Notification

Expected:

```text
Customer receives PAYMENT_QRIS_REJECTED
```

## PAY-020 — Customer Can Re-upload

Precondition:

```text
payment = PENDING
previous proof rejected
```

Expected:

```text
Customer uploads new proof
↓
WAITING_VERIFICATION
```

## PAY-021 — Re-upload Does Not Create Second Payment

Expected:

```text
same payment record
new/current proof
```

---

# 21. QRIS Final State Testing

## PAY-022 — Paid Is Terminal

Precondition:

```text
payment = PAID
```

Attempt:

```text
upload new proof
reject payment
change payment back
```

Expected:

```text
Rejected
payment remains PAID
```

## PAY-023 — Customer Cannot Force PAID

Attempt:

```json
{
  "payment_status": "PAID"
}
```

Expected:

```text
Request rejected
Backend determines state
```

## PAY-024 — Owner Cannot Directly Mark QRIS Pending as Paid

Precondition:

```text
QRIS = PENDING
proof does not exist
```

Expected:

```text
Direct PAID transition rejected
```

Owner harus mengikuti verification workflow.

---

# 22. CASH Payment Testing

## CASH-001 — Select CASH

Expected:

```text
payment_method = CASH
payment_status = PENDING
```

## CASH-002 — CASH Has No Proof

Expected:

```text
No payment proof upload requirement
```

## CASH-003 — CASH Remains PENDING Before Delivery Payment

Expected:

```text
CASH
↓
PENDING
```

## CASH-004 — CASH Order May Continue

Expected:

```text
Order may continue through processing/delivery workflow
while payment remains PENDING
```

---

# 23. CASH Courier Confirmation Testing

## CASH-005 — Assigned Courier Receives Cash

Precondition:

```text
Courier A assigned to Order A
Order A payment = CASH/PENDING
```

Action:

```text
Courier A selects "Uang Diterima"
```

Expected:

```text
Backend validates assignment
Payment → PAID
```

## CASH-006 — Confirmation Actor

Verify:

```text
actor = assigned Courier
```

## CASH-007 — Confirmation Timestamp

Verify:

```text
confirmation timestamp generated by backend
```

Timestamp harus merepresentasikan waktu business event yang sebenarnya.

## CASH-008 — Assignment Validation

Precondition:

```text
Courier A → Order A
Courier B → Order B
```

Courier A attempts to confirm Order B.

Expected:

```text
Rejected
Payment B remains PENDING
```

## CASH-009 — Unauthorized Courier

Courier yang tidak mempunyai assignment aktif mencoba confirm.

Expected:

```text
Rejected
```

## CASH-010 — Owner Cannot Confirm Cash

Owner attempts:

```text
CASH → PAID
```

Expected:

```text
Rejected
Payment remains PENDING
```

## CASH-011 — Customer Cannot Confirm Cash

Customer attempts to mark own payment as paid.

Expected:

```text
Rejected
Payment remains PENDING
```

## CASH-012 — Courier Cannot Confirm QRIS

Courier attempts Cash confirmation against QRIS payment.

Expected:

```text
Rejected
```

## CASH-013 — Duplicate Cash Confirmation

Courier sends confirmation twice.

Expected:

```text
First request:
PENDING → PAID

Second request:
Rejected safely / idempotent success according to API contract

No duplicate business mutation
No second payment record
```

---

# 24. Cash Actor and Audit Integrity

Setiap Cash confirmation harus dapat ditelusuri terhadap:

```text
payment
order
courier
assignment
timestamp
```

Minimal validation:

```text
Authenticated user
        ↓
Role = COURIER
        ↓
Assignment exists
        ↓
Assignment belongs to authenticated Courier
        ↓
Order belongs to assignment
        ↓
Payment belongs to order
        ↓
Payment method = CASH
        ↓
Payment status = PENDING
        ↓
Transition allowed
```

---

# 25. Payment Authorization Matrix Test

| Action | Customer | Owner | Assigned Courier |
|---|---:|---:|---:|
| View active QRIS | PASS | PASS | sesuai scope |
| Replace QRIS | DENY | PASS | DENY |
| Select QRIS own order | PASS | sesuai workflow | DENY |
| Upload QRIS proof own order | PASS | DENY | DENY |
| Re-upload rejected QRIS proof | PASS | DENY | DENY |
| View QRIS proof for verification | DENY | PASS | DENY |
| Approve QRIS | DENY | PASS | DENY |
| Reject QRIS | DENY | PASS | DENY |
| Select CASH own order | PASS | sesuai workflow | DENY |
| Confirm CASH | DENY | DENY | PASS, assigned only |
| Set arbitrary payment state | DENY | DENY | DENY |

Backend harus melakukan:

```text
Authentication
+
Role Authorization
+
Ownership
+
Assignment
+
Payment Method
+
Current Payment State
+
Allowed Transition
```

---

# 26. Security Testing

## SEC-001 — Customer Cannot Set PAID

Expected:

```text
Denied
```

## SEC-002 — Customer Cannot Verify QRIS

Expected:

```text
Denied
```

## SEC-003 — Customer Cannot Replace QRIS

Expected:

```text
Denied
```

## SEC-004 — Courier Cannot Verify QRIS

Expected:

```text
Denied
```

## SEC-005 — Owner Cannot Confirm CASH

Expected:

```text
Denied
```

## SEC-006 — Courier A Cannot Confirm Courier B Order

Expected:

```text
Denied
```

## SEC-007 — Customer A Cannot Upload Proof for Customer B

Expected:

```text
Denied
```

## SEC-008 — Customer A Cannot Read Customer B Payment

Expected:

```text
Denied / 404 according to resource hiding policy
```

## SEC-009 — Courier Cannot Access Unassigned Payment

Expected:

```text
Denied
```

## SEC-010 — Client Cannot Override State

Attempt:

```json
{
  "payment_status": "PAID"
}
```

Expected:

```text
Backend ignores/rejects arbitrary state mutation
```

## SEC-011 — QRIS Private Proof Protection

Expected:

```text
Unauthorized user cannot directly access private proof file
```

## SEC-012 — Token Exposure

Verify:

```text
No Sanctum token in production logs
No password in logs
No sensitive credentials in test evidence
```

---

# 27. Database Integrity Testing

## DB-001 — One Order One Payment

Expected:

```text
orders.id unique in payments.order_id
```

## DB-002 — Payment Cannot Exist Without Order

Expected:

```text
Foreign key prevents orphan payment
```

## DB-003 — Valid Payment Method

Allowed:

```text
QRIS
CASH
```

Invalid values must be rejected at application/database contract level.

## DB-004 — Valid Payment Status

Allowed:

```text
PENDING
WAITING_VERIFICATION
PAID
```

## DB-005 — QRIS Verification Metadata

After approval:

```text
verified_by != NULL
verified_at != NULL
```

## DB-006 — Cash Confirmation Integrity

Cash confirmation must preserve relationship:

```text
Order
↓
Payment
↓
Courier Assignment
↓
Courier Actor
```

## DB-007 — Payment Amount

Verify:

```text
payments.amount
=
backend-calculated order total
```

Android supplied amount must not become authoritative.

---

# 28. API Testing

## API-001 — Payment Method Values

Allowed:

```text
QRIS
CASH
```

## API-002 — Payment Status Values

Allowed:

```text
PENDING
WAITING_VERIFICATION
PAID
```

## API-003 — QRIS Proof Upload Contract

Verify:

```text
authenticated customer
own order
QRIS payment
PENDING status
valid file
```

## API-004 — QRIS Verification Contract

Verify:

```text
authenticated Owner
QRIS payment
WAITING_VERIFICATION
proof exists
```

## API-005 — QRIS Rejection Contract

Verify:

```text
authenticated Owner
QRIS payment
WAITING_VERIFICATION
```

Expected:

```text
PENDING
```

## API-006 — Cash Confirmation Contract

Verify:

```text
authenticated Courier
assigned order
CASH payment
PENDING status
```

Expected:

```text
PAID
```

## API-007 — Invalid Actor

Expected:

```text
403
```

## API-008 — Invalid State

Expected:

```text
409 / 422 according to API contract
```

## API-009 — Missing Resource

Expected:

```text
404
```

## API-010 — Validation Failure

Expected:

```text
422
```

---

# 29. Android ViewModel Testing

## VM-001 — QRIS Initial State

Expected:

```text
Loading
→ Success(PENDING)
```

## VM-002 — QRIS Proof Upload

Expected:

```text
Uploading
→ WAITING_VERIFICATION
```

State `PAID` tidak boleh dibuat oleh ViewModel secara lokal.

## VM-003 — QRIS Approval Refresh

Owner approval response:

```text
PAID
```

Expected UI:

```text
Payment = PAID
```

## VM-004 — QRIS Rejection

Expected:

```text
PENDING
Upload Proof action visible again
```

## VM-005 — Cash Initial State

Expected:

```text
PENDING
```

## VM-006 — Cash Confirmation

Courier sends action.

Expected:

```text
Request
→ Backend response
→ PAID
```

Tidak boleh:

```text
Button click
→ local PAID
```

---

# 30. UI Testing

## UI-001 — QRIS Display

Verify:

```text
Active QRIS displayed
Total displayed
Payment method displayed
PENDING displayed correctly
```

## UI-002 — QRIS Waiting Verification

Expected:

```text
Menunggu Verifikasi
```

## UI-003 — QRIS Rejected

Expected:

```text
Payment returns to PENDING
Upload Ulang Bukti action available
```

## UI-004 — QRIS Paid

Expected:

```text
Pembayaran Berhasil
```

## UI-005 — Cash Pending

Expected:

```text
Menunggu Pembayaran
```

## UI-006 — Cash Paid

Expected:

```text
Pembayaran Cash Berhasil
```

## UI-007 — Owner QRIS Verification

Expected:

```text
Proof visible
Approve action
Reject action
```

## UI-008 — Courier Cash Confirmation

Expected:

```text
Uang Diterima
```

Action only appears within relevant delivery context.

## UI-009 — Unauthorized UI

Even if route is manually opened:

```text
Backend authorization remains effective
```

UI visibility is not treated as security.

---

# 31. Repository Testing

Repository harus diuji terhadap:

```text
Successful GET
Successful POST
Successful PATCH/action
Multipart upload
401
403
404
409
422
500
Network timeout
Connection failure
Malformed response
```

Repository tidak boleh mengubah business state sendiri.

Repository hanya memetakan response menjadi domain/data result.

---

# 32. Error Handling Testing

## ERR-001 — Network Failure During QRIS Upload

Expected:

```text
Payment remains authoritative server state
UI displays retryable error
No fake PAID state
```

## ERR-002 — Network Failure During Owner Approval

Expected:

```text
UI does not assume PAID
Refresh can obtain actual backend state
```

## ERR-003 — Network Failure During Cash Confirmation

Expected:

```text
UI does not blindly assume PAID
Backend state determines result
```

## ERR-004 — Concurrent State Change

Example:

```text
Owner A verifies QRIS
while another request attempts rejection
```

Expected:

```text
Only valid state transition succeeds
No corrupted payment state
```

## ERR-005 — Duplicate Critical Action

Critical actions:

```text
Upload Proof
Approve
Reject
Replace QRIS
Uang Diterima
```

Expected:

```text
No duplicate business mutation
```

---

# 33. QRIS Replacement Concurrency Testing

## QRIS-014 — Two Replacement Requests

Owner sends two replacement requests.

Expected:

```text
System maintains one valid active QRIS
```

## QRIS-015 — Customer Reads QRIS During Replacement

Expected:

```text
Customer receives one authoritative active QRIS
```

## QRIS-016 — Replacement Does Not Change Payment Status

Expected:

```text
PENDING remains PENDING
WAITING_VERIFICATION remains WAITING_VERIFICATION
PAID remains PAID
```

QRIS configuration lifecycle harus dipisahkan dari payment lifecycle.

---

# 34. Notification Testing

Notification adalah transport/informational mechanism, bukan source of truth.

Event utama:

```text
ORDER_CREATED
ORDER_PROCESSED
PAYMENT_QRIS_PROOF_UPLOADED
PAYMENT_QRIS_APPROVED
PAYMENT_QRIS_REJECTED
PAYMENT_CASH_CONFIRMED
COURIER_ASSIGNED
DELIVERY_STARTED
TRACKING_AVAILABLE
ORDER_COMPLETED
```

## NOTIF-001 — QRIS Proof Uploaded

Expected:

```text
Payment = WAITING_VERIFICATION
Owner receives notification
```

## NOTIF-002 — QRIS Approved

Expected:

```text
Payment = PAID
Customer receives notification
```

## NOTIF-003 — QRIS Rejected

Expected:

```text
Payment = PENDING
Customer receives notification
```

## NOTIF-004 — Cash Confirmed

Expected:

```text
Payment = PAID
Customer receives notification
```

## NOTIF-005 — Unauthorized Recipient

Expected:

```text
Unauthorized user does not receive protected payment notification
```

## NOTIF-006 — Notification Is Not Source of Truth

Scenario:

```text
Notification received
↓
Open application
↓
GET current API state
```

Expected:

```text
UI displays current backend state
```

---

# 35. Tracking Security Testing

## TRK-001 — Customer Own Order Tracking

Expected:

```text
Allowed
```

## TRK-002 — Customer Other Order Tracking

Expected:

```text
Rejected
```

## TRK-003 — Courier Own Active Delivery Location

Expected:

```text
Allowed
```

## TRK-004 — Courier Other Delivery Location

Expected:

```text
Rejected
```

## TRK-005 — Location After Completion

Expected:

```text
No unauthorized active tracking
```

## TRK-006 — Tracking Notification

Expected:

```text
Notification contains minimum routing/context
Sensitive location is not unnecessarily embedded in push payload
```

---

# 36. Integration Testing

Integration tests harus menguji boundary:

```text
Android
↓
Retrofit
↓
Laravel API
↓
Business Service
↓
MySQL
↓
Notification
↓
Android
```

## INT-001 — QRIS Full Integration

```text
Customer selects QRIS
↓
Payment PENDING
↓
Customer uploads proof
↓
WAITING_VERIFICATION
↓
Owner receives notification
↓
Owner approves
↓
PAID
↓
Customer receives notification
↓
Customer refreshes API
↓
PAID displayed
```

## INT-002 — QRIS Rejection Integration

```text
PENDING
↓
Proof upload
↓
WAITING_VERIFICATION
↓
Owner rejects
↓
PENDING
↓
Customer re-upload
↓
WAITING_VERIFICATION
```

## INT-003 — CASH Full Integration

```text
Customer selects CASH
↓
PENDING
↓
Order processing
↓
Courier assigned
↓
Delivery
↓
Customer pays cash
↓
Assigned Courier confirms
↓
PAID
↓
Customer refresh
```

---

# 37. End-to-End Testing

## E2E-001 — Customer QRIS Journey

```text
Register
↓
Login
↓
Create Order
↓
Select QRIS
↓
View Active QRIS
↓
Make external payment
↓
Upload Proof
↓
WAITING_VERIFICATION
↓
Owner Approves
↓
PAID
↓
Order continues
↓
Delivery
↓
Tracking
↓
Completion
↓
History
```

Expected:

```text
No unauthorized transition
No payment duplication
No fake payment success
```

## E2E-002 — QRIS Rejection and Re-upload

```text
Create Order
↓
QRIS
↓
PENDING
↓
Upload Proof
↓
WAITING_VERIFICATION
↓
Owner Rejects
↓
PENDING
↓
Customer Uploads New Proof
↓
WAITING_VERIFICATION
↓
Owner Approves
↓
PAID
```

## E2E-003 — Customer CASH Journey

```text
Create Order
↓
Select CASH
↓
PENDING
↓
Order continues
↓
Courier assigned
↓
Delivery
↓
Customer pays cash
↓
Assigned Courier confirms
↓
PAID
↓
Completion
↓
History
```

## E2E-004 — QRIS Replacement Journey

```text
QRIS-A active
↓
Customer views QRIS-A
↓
Owner replaces QRIS
↓
QRIS-B active
↓
New request returns QRIS-B
↓
Existing payment state remains authoritative
```

## E2E-005 — Unauthorized Courier Journey

```text
Courier A
↓
Attempts to confirm Courier B order
↓
Backend rejects
↓
Payment remains PENDING
```

---

# 38. Security Regression Matrix

Setiap release harus mengulang minimal:

| Test | Expected |
|---|---|
| Customer → PAID | DENY |
| Customer → QRIS verification | DENY |
| Customer → QRIS replacement | DENY |
| Customer → other order | DENY |
| Courier → QRIS verification | DENY |
| Courier → QRIS replacement | DENY |
| Courier A → Courier B Cash confirmation | DENY |
| Owner → Cash confirmation | DENY |
| Unauthorized user → private proof | DENY |
| Arbitrary payment status mutation | DENY |
| PAID → PENDING | DENY |
| PAID → WAITING_VERIFICATION | DENY |

---

# 39. Test Evidence

Untuk setiap business-critical test, evidence minimal:

```text
Test Case ID
Environment
Build Version
API Version
Database State
Test Account
Input
Expected Result
Actual Result
Pass/Fail
Timestamp
Screenshot jika relevan
API Request jika aman
API Response jika aman
Relevant Log jika aman
Defect ID jika failed
```

Jangan menyimpan:

```text
Password plaintext
Sanctum token
Private credentials
Firebase server credentials
Private QRIS credentials
Sensitive production data
```

---

# 40. Defect Classification

## BLOCKER

Contoh:

```text
Application tidak dapat build
Authentication seluruh role gagal
Database corrupt
Payment dapat menjadi PAID tanpa workflow sah
Customer dapat mengubah payment menjadi PAID
Courier dapat confirm order Courier lain
Unauthorized user dapat mengakses seluruh payment
```

## CRITICAL

Contoh:

```text
Payment state salah
Payment record hilang
Duplicate payment mutation
QRIS ownership bypass
Cash assignment bypass
Private proof dapat diakses unauthorized user
```

## MAJOR

Contoh:

```text
Notification payment tidak dikirim
QRIS replacement tidak refresh
History payment salah
Android state tidak mengikuti API
Retry menyebabkan duplicate action
```

## MINOR

Contoh:

```text
Label salah
Empty state tidak optimal
Alignment
Non-critical UI issue
```

## TRIVIAL

Contoh:

```text
Typo
Spacing minor
Cosmetic issue tanpa dampak usability
```

---

# 41. Regression Strategy

Setiap perubahan pada payment harus memicu regression terhadap:

```text
Authentication
Authorization
Order
QRIS
CASH
QRIS Replacement
Payment API
Payment Database
Notification
Tracking
History
Android ViewModel
Android UI
```

Perubahan QRIS harus minimal menjalankan:

```text
QRIS active
QRIS display
QRIS selection
QRIS proof upload
QRIS verification
QRIS rejection
QRIS re-upload
QRIS replacement
Ownership
Authorization
```

Perubahan Courier Assignment harus minimal menjalankan:

```text
Assignment
Cash payment
Courier authorization
Cash confirmation
Timestamp
Tracking
Delivery
```

---

# 42. Definition of Done — QA

Feature KYŪSUI dianggap QA-complete apabila:

```text
[ ] Unit tests pass
[ ] Repository tests pass
[ ] ViewModel tests pass
[ ] API tests pass
[ ] Authentication tests pass
[ ] Authorization tests pass
[ ] Ownership tests pass
[ ] Assignment tests pass
[ ] Functional tests pass
[ ] QRIS tests pass if applicable
[ ] CASH tests pass if applicable
[ ] QRIS replacement tests pass if applicable
[ ] UI tests pass
[ ] Integration tests pass
[ ] Security tests pass
[ ] Error handling tests pass
[ ] Notification tests pass if applicable
[ ] Tracking tests pass if applicable
[ ] E2E affected workflow passes
[ ] Regression passes
[ ] No unresolved P0 defect
[ ] No unresolved P1 defect
[ ] Test evidence stored
[ ] Build succeeds
```

---

# 43. Release Gate

KYŪSUI tidak boleh dianggap ready hanya karena build berhasil.

Release gate:

```text
Build
+
Functional Correctness
+
API Contract
+
Database Integrity
+
Authentication
+
Authorization
+
Ownership
+
Assignment
+
QRIS
+
CASH
+
QRIS Replacement
+
Notification
+
Tracking Security
+
Error Handling
+
E2E
+
Security
+
Regression
```

Business-critical failure pada salah satu area tersebut harus memblokir release sampai diperbaiki atau secara eksplisit diterima sebagai known issue oleh pihak yang berwenang.

---

# 44. Required Test Execution Order

Untuk feature development:

```text
1. Unit
2. Repository
3. ViewModel
4. API
5. Authentication
6. Authorization
7. Ownership / Assignment
8. Feature Functional
9. UI
10. Integration
11. Security
12. E2E
13. Regression
```

Untuk release candidate:

```text
Build
↓
Unit
↓
API
↓
Authentication
↓
Authorization
↓
Ownership
↓
Assignment
↓
QRIS
↓
CASH
↓
QRIS Replacement
↓
Tracking
↓
Notification
↓
UI
↓
Integration
↓
Security
↓
E2E QRIS
↓
E2E CASH
↓
Regression
↓
Release Decision
```

---

# 45. Final Acceptance Flow

## 45.1 Common

```text
REGISTER
   ↓
LOGIN
   ↓
CREATE ORDER
   ↓
SELECT PAYMENT
```

## 45.2 QRIS

```text
SELECT QRIS
   ↓
PENDING
   ↓
DISPLAY ACTIVE QRIS
   ↓
CUSTOMER PAYS EXTERNALLY
   ↓
UPLOAD PROOF
   ↓
WAITING_VERIFICATION
   ↓
OWNER APPROVES
   ↓
PAID
   ↓
ORDER WORKFLOW
   ↓
COURIER
   ↓
DELIVERY
   ↓
TRACKING
   ↓
COMPLETION
   ↓
HISTORY
```

Rejection:

```text
WAITING_VERIFICATION
   ↓
OWNER REJECTS
   ↓
PENDING
   ↓
RE-UPLOAD
   ↓
WAITING_VERIFICATION
```

## 45.3 CASH

```text
SELECT CASH
   ↓
PENDING
   ↓
ORDER MAY CONTINUE
   ↓
COURIER ASSIGNMENT
   ↓
DELIVERY
   ↓
CUSTOMER PAYS CASH
   ↓
ASSIGNED COURIER
   ↓
"UANG DITERIMA"
   ↓
BACKEND VALIDATES ASSIGNMENT
   ↓
PAID
   ↓
COMPLETION
   ↓
HISTORY
```

---

# 46. Final Acceptance Criteria

KYŪSUI dinyatakan memenuhi testing baseline apabila:

1. Customer dapat register dan login.
2. Customer dapat membuat order yang valid.
3. Backend menghitung nominal order.
4. Setiap order mempunyai satu business payment.
5. Customer dapat memilih QRIS atau CASH.
6. QRIS menggunakan active static QRIS.
7. Customer dapat melihat active QRIS.
8. Customer tidak dapat mengganti QRIS.
9. Owner dapat mengganti active QRIS.
10. QRIS dimulai dari `PENDING`.
11. QRIS proof yang valid mengubah payment menjadi `WAITING_VERIFICATION`.
12. Owner dapat melihat proof QRIS yang menunggu verification.
13. Owner dapat approve QRIS.
14. Approval QRIS menghasilkan `PAID`.
15. Approval menyimpan actor Owner.
16. Approval menyimpan timestamp backend.
17. Owner dapat reject proof QRIS.
18. Rejection mengembalikan payment ke `PENDING`.
19. Customer dapat melakukan re-upload setelah rejection.
20. Re-upload tidak membuat payment record kedua.
21. `PAID` tidak dapat dikembalikan melalui workflow payment normal.
22. CASH dimulai dari `PENDING`.
23. Order CASH dapat melanjutkan workflow delivery ketika payment masih `PENDING`.
24. Hanya Assigned Courier yang dapat mengonfirmasi Cash.
25. Cash confirmation menghasilkan `PAID`.
26. Cash confirmation dapat ditelusuri terhadap Courier dan assignment.
27. Cash confirmation mempunyai timestamp backend.
28. Owner tidak dapat mengonfirmasi Cash.
29. Customer tidak dapat mengonfirmasi Cash untuk dirinya sendiri.
30. Courier yang tidak assigned tidak dapat mengonfirmasi Cash.
31. Courier A tidak dapat mengonfirmasi order Courier B.
32. Courier tidak dapat melakukan verification QRIS.
33. Customer tidak dapat melakukan verification QRIS.
34. Customer tidak dapat memaksa payment menjadi `PAID`.
35. Customer tidak dapat mengganti QRIS.
36. Active QRIS replacement tidak mengubah payment state existing secara otomatis.
37. QRIS lama tidak lagi menjadi active QRIS setelah replacement berhasil.
38. QRIS baru menjadi active QRIS untuk request berikutnya.
39. Backend menjadi authority seluruh payment state.
40. Android tidak membuat fake payment success.
41. Notification mengikuti business event yang berhasil committed.
42. Notification bukan source of truth.
43. Unauthorized access ditolak backend.
44. Payment state konsisten antara API dan MySQL.
45. Tracking hanya dapat diakses pada context yang sah.
46. Completed order tersimpan dan muncul pada history.
47. Critical duplicate action tidak menghasilkan duplicate business mutation.
48. Tidak ada P0/P1 defect yang belum diselesaikan atau diterima secara eksplisit.
49. Seluruh critical test evidence tersedia.
50. Release candidate melewati regression suite.

---

# 47. Forbidden Legacy Payment Tests

Test suite aktif **tidak boleh lagi** mempunyai test case yang menganggap komponen berikut sebagai bagian dari payment architecture:

```text
Midtrans
Payment Gateway
Provider Transaction
Provider Verification
Provider Webhook
Provider Callback
Dynamic QRIS
Automatic Provider Verification
Payment Provider ID
Provider Transaction ID
```

Test suite juga **tidak boleh** menggunakan payment status:

```text
PROCESSING
CONFIRMED
FAILED
EXPIRED
```

Rejection QRIS tidak direpresentasikan sebagai payment status baru.

Canonical behavior:

```text
WAITING_VERIFICATION
        ↓
Owner rejects
        ↓
PENDING
```

---

# 48. Payment Test Coverage Matrix

| Requirement | Test ID | Level |
|---|---|---|
| Active QRIS | QRIS-001 | API/UI/E2E |
| QRIS display | QRIS-002 | UI |
| QRIS selection | QRIS-003 | UI/API |
| QRIS PENDING | PAY-001 | API/VM |
| Proof upload | PAY-002 | API/Integration |
| WAITING_VERIFICATION | PAY-002 | API/VM/UI |
| Owner approve | PAY-011 | API/E2E |
| PAID | PAY-011 | API/DB/E2E |
| Owner reject | PAY-017 | API/E2E |
| PENDING kembali | PAY-017 | API/VM |
| Re-upload | PAY-020 | API/E2E |
| Ownership | PAY-003 | Security |
| Authorization | SEC-001–SEC-012 | Security |
| QRIS replacement | QRIS-006 | API/E2E |
| Old/new QRIS behavior | QRIS-007–QRIS-013 | Integration/E2E |
| CASH selection | CASH-001 | UI/API |
| CASH PENDING | CASH-001 | API/DB |
| Order delivery while pending | CASH-004 | E2E |
| Cash received | CASH-005 | E2E |
| Courier confirmation | CASH-005 | API/E2E |
| Cash PAID | CASH-005 | API/DB |
| Actor | CASH-006 | Security/DB |
| Timestamp | CASH-007 | DB/API |
| Assignment | CASH-008 | Security |
| Unauthorized Courier | CASH-009 | Security |
| Customer cannot PAID | SEC-001 | Security |
| Customer cannot verify | SEC-002 | Security |
| Customer cannot replace QRIS | SEC-003 | Security |
| Courier cannot verify QRIS | SEC-004 | Security |
| Owner cannot confirm Cash | SEC-005 | Security |
| Courier A cannot confirm Courier B | SEC-006 | Security |

---

# 49. Final QA Status

```text
Document:
11_KYUSUI_TESTING_SPECIFICATION.md

Status:
REBUILT — PAYMENT ARCHITECTURE SYNCHRONIZED

QA Role:
Senior QA Architect / Test Architect

Platform:
Android Native

Backend:
Laravel 13 / PHP 8.3+

Database:
MySQL 8.x

Authentication:
Laravel Sanctum

Payment:
QRIS + CASH

QRIS:
Static QRIS
Customer Proof
Owner Manual Verification

CASH:
Cash on Delivery
Assigned Courier Confirmation

Canonical Payment Status:
PENDING
WAITING_VERIFICATION
PAID

Payment State:
Server Controlled

Payment Record:
One Order → One Payment

QRIS Configuration:
One Active QRIS

QRIS Replacement:
Owner Only

QRIS Proof:
Customer Own Order Only

QRIS Verification:
Owner Only

CASH Confirmation:
Assigned Courier Only

Customer:
Cannot Set PAID
Cannot Verify QRIS
Cannot Replace QRIS

Courier:
Cannot Verify QRIS
Cannot Replace QRIS
Cannot Confirm Other Courier Order

Owner:
Can Verify QRIS
Can Reject QRIS
Can Replace Active QRIS
Cannot Confirm CASH

Payment Gateway:
NOT USED

Provider Transaction:
NOT USED

Provider Webhook:
NOT USED

Dynamic QRIS:
NOT USED

Automatic Payment Verification:
NOT USED

Legacy Payment Status:
NOT USED

Security:
DEFINED

Functional Testing:
DEFINED

API Testing:
DEFINED

Database Testing:
DEFINED

Android Testing:
DEFINED

Integration Testing:
DEFINED

E2E Testing:
DEFINED

Regression Testing:
DEFINED

Acceptance Testing:
DEFINED

Release Gate:
DEFINED
```

---

# 50. Source Documents

Testing specification ini menggunakan dan harus diselaraskan terhadap:

```text
04_KYUSUI_SYSTEM_WORKFLOW.md
05_KYUSUI_DATABASE_SCHEMA.md
06_KYUSUI_API_SPECIFICATION.md
07_KYUSUI_ANDROID_ARCHITECTURE.md
08_KYUSUI_PAYMENT_SPECIFICATION.md
```

Supporting dependencies:

```text
03_KYUSUI_UI_UX_SPECIFICATION.md
09_KYUSUI_TRACKING_SPECIFICATION.md
10_KYUSUI_NOTIFICATION_SPECIFICATION.md
12_KYUSUI_VISUAL_DESIGN_SPECIFICATION.md
13_KYUSUI_DATABASE_FINALIZATION.md
13_KYUSUI_INTEGRATION_CONTRACT.md
```

Payment specification menjadi authority utama untuk payment test behavior. Database schema menjadi authority untuk persistent payment relationship dan integrity. API specification menjadi authority untuk endpoint, HTTP behavior, authentication, authorization, dan request/response contract. Android architecture menjadi authority untuk Android responsibility boundary dan state handling.

---

# 51. QA Final Principle

```text
TEST WHAT THE SYSTEM IS ALLOWED TO DO
+
TEST WHAT THE SYSTEM MUST REFUSE TO DO
```

Untuk payment:

```text
QRIS

PENDING
   ↓
WAITING_VERIFICATION
   ↓
PAID

WAITING_VERIFICATION
   ↓
PENDING
```

dan:

```text
CASH

PENDING
   ↓
Assigned Courier
   ↓
PAID
```

Untuk security:

```text
Customer
   ✕ PAID
   ✕ QRIS Verification
   ✕ QRIS Replacement

Courier
   ✕ QRIS Verification
   ✕ QRIS Replacement
   ✕ Other Courier Confirmation

Owner
   ✓ QRIS Verification
   ✓ QRIS Rejection
   ✓ QRIS Replacement
   ✕ CASH Confirmation
```

Untuk architecture:

```text
Android
   ↓
Action
   ↓
Laravel
   ↓
Authentication
   ↓
Authorization
   ↓
Ownership / Assignment
   ↓
Business Validation
   ↓
MySQL
   ↓
Authoritative State
   ↓
Android
```

Tidak ada test yang boleh menganggap tombol Android sebagai bukti bahwa business state telah berhasil berubah.
