# KYŪSUI — BACKEND FREEZE BASELINE REPORT

**Project:** KYŪSUI  
**Study Case:** Berkah Water  
**Document:** `14_KYUSUI_BACKEND_FREEZE_REPORT.md`  
**Status:** READY_FOR_TEAM_APPROVAL  
**Prepared:** 2026-10-09  
**Prepared By:** Senior Software Architect / API Architect / Database Architect / Android Integration Architect / QA Architect  

---

## 1. PURPOSE & SCOPE

Dokumen ini menetapkan baseline kontrak backend KYŪSUI yang disetujui untuk dibekukan (freeze) setelah melewati serangkaian audit Phase 1–2.3.

**Tujuan freeze:**
- Mengunci kontrak API, skema database, dan aturan bisnis yang sudah disepakati.
- Menyediakan referensi tunggal (single source of truth) untuk tim Android dan Backend.
- Mencegah perubahan breaking tanpa proses persetujuan eksplisit.

**Scope freeze mencakup:**
- Semua endpoint canonical di bawah prefix `/api/v1`.
- Skema database (migration, model, relasi, FK, index).
- Enum status: `OrderStatus`, `PaymentStatus`, `PaymentMethod`, `AssignmentStatus`.
- Aturan otorisasi berbasis role (CUSTOMER, OWNER, COURIER).
- Alur pembayaran QRIS & CASH.
- Alur assignment kurir dan delivery.
- Tracking kontrak dasar.

**Di luar scope freeze (tidak dibekukan):**
- Tracking real-time (polling/WebSocket/ETA/geofencing).
- Interval tracking `UD-TRACK-01` / `UD-TRACK-02` (masih UNRESOLVED).
- Implementasi pembatalan order di Android (belum ada).
- Endpoint legacy yang dipertahankan untuk kompatibilitas.

---

## 2. BASELINE IDENTITY

| Item | Value |
|------|-------|
| **PROJECT_ROOT** | `G:\proyeksaya\kyusui` |
| **BRANCH** | `main` |
| **HEAD COMMIT** | `42f7528bf604c65802ffa655c9147286a4503e4d` |
| **COMMIT DATE** | 2026-10-04 05:14:19 +0700 |
| **COMMIT MESSAGE** | `complete android phase 10 audit...` |
| **WORKING TREE** | CLEAN (no uncommitted source changes) |
| **TAG** | (belum ada) |

> **CATATAN PENTING:** Commit `42f7528` hanya menambahkan file `KYUSUI_ANDROID_FINAL_AUDIT.md` (323 lines). Semua perubahan Phase 1.1, 2.1, 2.2, 2.3 **belum di-commit** dan berada di working tree. Baseline kode aktual adalah **HEAD + working tree changes**. Lihat `git diff HEAD --stat` untuk detail perubahan.

---

## 3. AUTHORITATIVE DOCUMENTS (VERSIONS)

| Document | Version / Status |
|----------|------------------|
| `00_KYUSUI_MASTER_SPECIFICATION.md` | Updated: Cancellation policy resolved |
| `01_KYUSUI_PROJECT_RULES_REBUILT.md` | Updated: Removed "kebijakan pembatalan" from Rule 19 |
| `04_KYUSUI_SYSTEM_WORKFLOW_REBUILT.md` | Updated: Cancellation state note |
| `06_KYUSUI_API_SPECIFICATION_REBUILT.md` | Updated: Tracking resource, cancel endpoint, preserved endpoints, payment proof URL |
| `09_KYUSUI_TRACKING_SPECIFICATION.md` | Updated: Tracking response examples |
| `13_KYUSUI_INTEGRATION_CONTRACT.md` | v2.0 FINAL — Payment Architecture Synchronized |
| `13_KYUSUI_DATABASE_FINALIZATION.md` | FINAL — Database Payment Validated |

---

## 4. CANONICAL ENDPOINT CATALOG

### AUTH
```
POST   /auth/register
POST   /auth/login
POST   /auth/logout
GET    /auth/me
```

### CUSTOMER
```
GET    /customer/profile
PUT    /customer/profile
GET    /products
POST   /customer/orders
GET    /customer/orders
GET    /customer/orders/{order}
GET    /customer/orders/{order}/tracking
GET    /customer/payment/qris
POST   /customer/orders/{order}/payment
GET    /customer/orders/{order}/payment
GET    /customer/payments
POST   /customer/orders/{order}/payment/proof
```

### CUSTOMER PRESERVED (not in canonical catalog)
```
POST   /customer/orders/{order}/cancel          # Pilihan A: hanya MENUNGGU_PEMBAYARAN
GET    /customer/payment/qris/image
GET    /dashboard/customer
```

### OWNER
```
GET    /owner/orders
GET    /owner/orders/{order}
PATCH  /owner/orders/{order}/status
POST   /owner/orders/{order}/assignment
GET    /owner/payments/pending
GET    /owner/orders/{order}/payment/proof
POST   /owner/orders/{order}/payment-verification
GET    /owner/payment/qris
PUT    /owner/payment/qris
```

### OWNER PRESERVED
```
GET    /owner/products
POST   /owner/products
PUT    /owner/products/{product}
DELETE /owner/products/{product}
GET    /owner/couriers
GET    /dashboard/owner
```

### COURIER (ASSIGNMENT-CENTRIC)
```
GET    /courier/assignments
GET    /courier/assignments/{assignment}
POST   /courier/assignments/{assignment}/start
POST   /courier/assignments/{assignment}/location
POST   /courier/assignments/{assignment}/cash/confirm
POST   /courier/assignments/{assignment}/complete
GET    /dashboard/courier
```

### COURIER LEGACY (PRESERVED)
```
POST   /couier/orders/{order}/payment-confirmation   # Legacy CASH confirm
```

### NOTIFICATION
```
POST   /notifications/device-token
GET    /notifications
PATCH  /notifications/{notification}/read
POST   /notifications/read-all    # Preserved
```

### REMOVED
```
POST   /payments/midtrans/webhook
```

---

## 5. BUSINESS DECISIONS FINALIZED

| Decision | Status | Detail |
|----------|--------|--------|
| **Payment Methods** | ✅ FINAL | `QRIS`, `CASH` only |
| **Payment Statuses** | ✅ FINAL | `PENDING`, `WAITING_VERIFICATION`, `PAID` |
| **QRIS Architecture** | ✅ FINAL | Static QRIS, manual Owner verification, no gateway |
| **CASH Flow** | ✅ FINAL | Courier confirms, backend validates assignment |
| **Cancel Order (Option A)** | ✅ FINAL | Only `MENUNGGU_PEMBAYARAN` → `DIBATALKAN`, else 409 |
| **Payment Terminal** | ✅ FINAL | `PAID` is terminal, no revert in normal flow |
| **One Order = One Payment** | ✅ FINAL | `payments.order_id` UNIQUE |
| **Tracking Contract** | ✅ FINAL | `TrackingResource` ↔ `CustomerTrackingDto` aligned |
| **Courier API** | ✅ FINAL | Assignment-centric (not order-centric) |

---

## 6. CONTRACT AUDIT SUMMARY

| Area | Status | Evidence |
|------|--------|----------|
| **Auth & Sanctum** | ✅ PASS | 401/403/404 codes correct |
| **Role Authorization** | ✅ PASS | RoleMiddleware enforces CUSTOMER/OWNER/COURIER |
| **Ownership Checks** | ✅ PASS | 404 for cross-customer access |
| **Customer Orders** | ✅ PASS | 148 backend tests pass |
| **Order Status Transitions** | ✅ PASS | Validated in tests |
| **Cancel Order (Option A)** | ✅ PASS | 5 new tests added |
| **QRIS Payment** | ✅ PASS | Upload proof → WAITING_VERIFICATION → Owner approve → PAID |
| **CASH Payment** | ✅ PASS | Courier confirms → PAID |
| **Owner Payment Verification** | ✅ PASS | Approve/Reject via single endpoint |
| **Courier Assignment** | ✅ PASS | Assignment-centric, exclusive active |
| **Courier Location Submit** | ✅ PASS | Throttle 120/min, validation 150km/h |
| **Customer Tracking** | ✅ PASS | TrackingResource ↔ CustomerTrackingDto aligned |
| **Courier Location Response** | ✅ PASS | CourierLocationResource (4 fields) for submit response |
| **Owner Payment Proof URL** | ✅ PASS | `/api/v1/owner/orders/{order}/payment/proof?download=1` |
| **Payment `verified_by`** | ✅ PASS | Flat integer (user ID), not nested object |
| **Notifications** | ✅ PASS | Payload matches spec 10 §19 |
| **Database Schema** | ✅ PASS | Migrations finalized, FKs correct |
| **Legacy Endpoints** | ✅ DOCUMENTED | All preserved endpoints cataloged |

---

## 7. TEST EVIDENCE (VERIFIED)

| Suite | Tests | Passed | Assertions | Command |
|-------|-------|--------|------------|---------|
| Backend PHPUnit | 148 | 148 | 843 | `php artisan test` |
| Android Unit Tests | 42 files | All passed | — | `gradlew.bat testDebugUnitTest` |
| TrackingApiContractTest | 10 | 10 | — | `gradlew.bat testDebugUnitTest --tests ...TrackingApiContractTest` |
| CourierApiContractTest | 20+ | All passed | — | `gradlew.bat testDebugUnitTest --tests ...CourierApiContractTest` |
| Android Compile | — | — | — | `gradlew.bat compileDebugKotlin` |

**All tests executed and verified on 2026-10-09.**

---

## 8. KNOWN ISSUES & NON-BLOCKERS

| Issue | Classification | Impact |
|-------|----------------|--------|
| `UD-TRACK-01` (courier location interval) | **NON-BLOCKER** | Client-side default 30s; backend no server limit; Android enforces 30s |
| `UD-TRACK-02` (customer refresh strategy) | **NON-BLOCKER** | Client-side 15s polling; backend throttle 5/min (12s min); Android enforces 12s min |
| Cancel order not in Android | Low | Backend ready; Android impl pending |
| Legacy courier endpoint retained | Low | Documented in PRESERVED table |
| `TrackingResource.location` alias | Low | Kept for backward compat; documented |

**No blockers for freeze.**

---

## 9. POST-FREEZE CHANGE RULES

| Rule | Requirement |
|------|-------------|
| **Contract Change** | Identify endpoint + consumers affected |
| **Breaking Change** | Requires team approval before implementation |
| **Backend→Android Impact** | Must update contract + tests simultaneously |
| **Documentation** | Must be updated with implementation |
| **Legacy Endpoint Removal** | Requires explicit team decision |
| **Testing** | Tests must be updated with implementation |
| **Documentation** | Must reflect implementation at all times |

---

## 10. FREEZE STATUS DECLARATION

**Status: `READY_FOR_TEAM_APPROVAL`**

> ✅ All critical contract mismatches resolved  
> ✅ All backend tests pass (148/148, 843 assertions)  
> ✅ Android contract tests pass (Tracking + Courier)  
> ✅ Android compilation successful  
> ✅ Documentation synchronized  
> ✅ No unresolved blockers  
> ✅ `SPECIFICATION_CONFLICT-001` resolved (Option A)  
> ✅ `SPECIFICATION_CONFLICT-001` documented as RESOLVED  
> ✅ `UD-TRACK-01` / `UD-TRACK-02` classified as non-blockers  
> ✅ Legacy endpoints documented  
> ✅ All tests pass (backend 148/148, Android contract tests pass)  

---

## 11. NEXT STEPS

1. **Team Review** — Review this baseline report
2. **Explicit Approval** — Team confirms `FROZEN` status
3. **Create Tag** — `git tag v1.0.0-freeze` (optional)
3. **Communicate to Android Team** — Share baseline contract
4. **Phase 3** — Begin implementation against frozen baseline (if approved)

---

## 12. APPROVAL REQUIRED

> **Baseline ini belum resmi `FROZEN`.**  
> Status `READY_FOR_TEAM_APPROVAL` berarti audit dan bukti mendukung pembekuan, dokumen baseline sudah disiapkan, dan keputusan tim masih diperlukan.  
>  
> **Status `FROZEN` hanya dapat diberikan setelah persetujuan eksplisit tim.**

---

**Document Version:** 1.0  
**Prepared:** 2026-10-09  
**Prepared By:** Senior Software Architect / API Architect / Database Architect / Android Integration Architect / QA Architect  
**Status:** `READY_FOR_TEAM_APPROVAL`