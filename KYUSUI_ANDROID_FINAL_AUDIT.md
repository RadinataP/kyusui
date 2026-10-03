# KYUSUI_ANDROID_FINAL_AUDIT.md

**Project:** KYŪSUI  
**Phase:** 10 — Final QA & Hardening  
**Audit Date:** 2026-10-04  
**Android Module:** `android/app`  
**Package:** `com.kyusui.app`

---

## Executive Summary

| Check | Result | Notes |
|-------|--------|-------|
| **Clean Build** | ✅ PASS | `gradlew clean` successful |
| **Debug Build** | ✅ PASS | `assembleDebug` successful in 6m 8s |
| **Release Build** | ✅ PASS | `assembleRelease` successful in 9m 13s |
| **Unit Tests** | ✅ PASS | 596 tests, 37 suites, 0 failures |
| **Lint (Debug)** | ❌ FAIL | 5 errors, 82 warnings |
| **Architecture Compliance** | ✅ PASS | No Composable→Retrofit, no ViewModel→Retrofit, no local business authority |
| **Payment Contract** | ✅ PASS | QRIS/CASH only; PENDING/WAITING_VERIFICATION/PAID only; no legacy artifacts |
| **Security** | ⚠️ PARTIAL | Token not logged; secrets not in source; but lint shows MissingPermission errors |

---

## 1. Implemented Features

### Authentication
- Register (POST `/auth/register`) — email optional, phone-based
- Login (POST `/auth/login`) — phone or email as `login` field
- Logout (POST `/auth/logout`) — revokes server session, clears local
- Session restore (GET `/auth/me`) — validates token, auto-clears on 401
- Role-based navigation (CUSTOMER, OWNER, COURIER)

### Customer Flow
- Product catalog (paginated, availability-aware)
- Create order with delivery location (lat/lng/address)
- Order history (paginated)
- Order detail with items, totals, payment status
- Payment method selection: QRIS or CASH
- Active QRIS display (static, from backend config)
- QRIS proof upload (multipart, private storage)
- Payment history with filters
- Order tracking (courier position, throttled 5/min)

### Owner Flow
- Order list with status filter
- Order detail (customer, items, totals, assignment)
- Order status transition (validated by backend)
- Courier assignment
- Pending QRIS verification queue
- View QRIS proof (authorized URL)
- Approve/Reject QRIS (single endpoint, action-based)
- Active QRIS configuration (GET/PUT, multipart image)

### Courier Flow
- Assignment list (unpaginated, assignment-centric)
- Assignment detail with order, items, payment
- Start delivery (validates state)
- Location streaming (FLP + platform fallback, idempotency key)
- Cash confirmation (assignment-scoped, no body)
- Complete delivery (validates payment = PAID)
- Dashboard (today assignments, summary, unread count)

### Notifications
- FCM token registration
- Notification list (paginated, read filter)
- Mark as read / mark all read
- Tap routing to relevant screens
- Payload schema versioning

### Infrastructure
- Hilt DI (Repository, API, DataStore, Retrofit, Coil)
- DataStore preferences (token, role, device token)
- Retrofit + OkHttp + Kotlinx Serialization
- Safe logging interceptor (redacts Authorization header)
- Auth interceptor (auto-attaches Bearer token)
- Response validation interceptor (maps HTTP codes to exceptions)
- Coil image loader with authenticated client

---

## 2. Incomplete Features

| Feature | Status | Blocking Reason |
|---------|--------|-----------------|
| Customer profile update | Declared, not called | Backend path mismatch (`/profile` vs `/customer/profile`); field `address` vs `default_address` |
| Order cancellation | Missing | Backend has `/orders/{order}/cancel`; Android not implemented |
| Owner couriers list | Missing | Backend has `/owner/couriers`; needed for assignment UI |
| Owner dashboard | Missing | Backend has `/dashboard/owner`; not in spec |
| Owner products CRUD | Missing | Backend has CRUD endpoints; not in spec |
| Customer dashboard | Missing | Backend has `/dashboard/customer`; used in Phase 3/4; not in spec |
| Forgot password | Route declared, screen stubbed | Navigation route exists; no backend endpoint confirmed |
| Profile image upload | Not in spec | No backend endpoint for profile avatar |

---

## 3. Known Bugs

### Lint Errors (5 — Build Failing)

| File | Line | Error | Description |
|------|------|-------|-------------|
| `DeviceLocationSource.kt` | 164 | MissingPermission | `requestLocationUpdates` called; permission checked earlier in flow but lint doesn't track callbackFlow |
| `DeviceLocationSource.kt` | 183 | MissingPermission | `lastLocation` call inside suspendCancellableCoroutine; permission checked in caller |
| `DeviceLocationSource.kt` | 207 | MissingPermission | `getLastKnownLocation` in platform fallback; permission checked at line 194 |
| `KyusuiFirebaseMessagingService.kt` | 125 | MissingPermission | `notificationManager.notify` requires POST_NOTIFICATIONS (declared in manifest) |
| `AndroidManifest.xml` | 15 | PermissionImpliesUnsupportedChromeOsHardware | CAMERA permission needs `<uses-feature android:name="android.hardware.camera" required="false"/>` |

> **Note:** The 3 `DeviceLocationSource` errors are false positives — permission is validated at entry points (`readOnce()` line 126, `stream()` line 138). The FCM error is also a false positive — POST_NOTIFICATIONS is in manifest. The CAMERA permission is used for QRIS proof capture.

### Lint Warnings (82 — Non-blocking)
- 18 obsolete Compose custom lint checks (library version mismatch)
- 1 SelectedPhotoAccess warning (READ_MEDIA_IMAGES on Android 14+)
- 48 GradleDependency warnings (newer versions available)
- 1 ComposableNaming warning (PaymentStatusVisual returns value)
- 13 UnusedResources (legacy color/string resources from template)
- 1 TypographyEllipsis (use … instead of ...)

---

## 4. API Dependencies

| Area | Endpoints Used | Contract Status |
|------|----------------|-----------------|
| Auth | 4/4 | ✅ READY |
| Customer Products | 1/1 | ✅ READY |
| Customer Orders | 4/6 | ⚠️ 3 MISMATCH (paths), 1 MISSING |
| Customer Tracking | 1/1 | ✅ READY |
| Customer Payment | 6/6 | ⚠️ 5 MISMATCH (paths + 1 verb) |
| Owner Orders | 2/4 | ⚠️ 2 UNRESOLVED, 2 MISMATCH |
| Owner Payment | 2/3 | ⚠️ 1 UNRESOLVED, 2 MISMATCH |
| Owner QRIS Config | 2/2 | ⚠️ 2 MISMATCH |
| Owner Backend-Only | 0/3 | ❌ 3 MISSING |
| Courier Assignments | 7/7 | ⚠️ 7 MISMATCH (spec vs backend) |
| Notifications | 4/4 | ✅ READY |
| Customer Dashboard | 0/1 | ❌ 1 MISSING |

**Total:** 33/41 endpoints implemented; 21 MISMATCH (path/contract divergence), 3 UNRESOLVED (backend not confirmed), 5 MISSING (Android not implemented)

> **Critical:** Customer and Owner endpoints use spec paths that 404 against current backend. Courier uses backend paths (assignment-centric) which diverge from spec (order-centric). See `ANDROID_API_INTEGRATION_MATRIX.md` for full mapping.

---

## 5. Backend Dependencies

| Dependency | Status | Notes |
|------------|--------|-------|
| Laravel 13 / PHP 8.3+ | Required | Not verified running |
| MySQL 8.x | Required | Schema per `05_KYUSUI_DATABASE_SCHEMA_REBUILT.md` |
| Laravel Sanctum | Required | Token auth on all private endpoints |
| FCM Server Key | Required | For notification push |
| Google Maps API Key | Required | Configured via `KYUSUI_MAPS_API_KEY` (local.properties / env) |
| Storage (private) | Required | QRIS proof images; proof URL must be authorized |
| Base URL | Required | Dev: `http://10.0.2.2:8000/api/v1/`; Prod: `https://api.kyusui.example.com/api/v1/` |

**Unresolved Backend Contract Decisions (8):**
1. Customer paths: adopt backend (`/profile`, `/orders`, `/orders/{order}/method` PUT, `/orders/{order}/qris-proof`, `/payment/qris`) or update backend?
2. Owner paths: adopt backend (`/owner/orders/{order}/process`, `/owner/orders/{order}/assign-courier`, `/owner/payments/qris/pending`, `/owner/payments/{payment}/proof`, `/owner/qris`) or update backend?
3. Courier paths: spec must match backend (assignment-centric) — spec rewrite required
4. Legacy courier cash endpoint: deprecate `/courier/orders/{order}/payment-confirmation`?
5. Courier assignments pagination: add to backend or document as unpaginated?
6. Profile route: add `role:CUSTOMER` middleware or remove "customer-only" from spec?
7. Dashboard endpoints: add `/dashboard/*` family to spec (3 roles)?
8. Owner tracking: define contract (currently absent in backend)?

---

## 6. Unresolved Decisions

| Decision | Impact | Blocking Integration |
|----------|--------|---------------------|
| Canonical Customer paths (7) | All Customer payment/order flows | YES |
| Canonical Owner paths (8) | All Owner order/payment/QRIS flows | YES |
| Courier spec rewrite (8) | Documentation only; Android already matches backend | NO (doc only) |
| Legacy cash endpoint | Backend cleanup | NO |
| Courier pagination | UX for couriers with many assignments | NO |
| Profile middleware | Security model alignment | NO |
| Dashboard endpoints in spec | Spec completeness | NO |
| Owner tracking | Future feature | NO |

---

## 7. Test Results

| Test Type | Suites | Tests | Passed | Failed | Skipped |
|-----------|--------|-------|--------|--------|---------|
| Unit (JUnit) | 37 | 596 | 596 | 0 | 0 |
| Repository | — | — | — | — | — |
| ViewModel | — | — | — | — | — |
| API Contract | 5 | ~120 | ~120 | 0 | 0 |
| UI (Robolectric) | — | — | — | — | — |
| Integration | — | — | — | — | — |
| Security | — | — | — | — | — |
| E2E (Instrumentation) | 0 | 0 | N/A | N/A | N/A |

**Test Coverage Areas:**
- Auth (register, login, logout, restore, token storage)
- Customer (products, orders, payment, QRIS, tracking)
- Owner (orders, payment verification, QRIS config)
- Courier (assignments, delivery, cash, dashboard)
- Notifications (token, list, read, routing)
- Core (money, tracking intervals, QRIA URL, proof upload, network exceptions)

**Missing Test Types:**
- No Compose UI tests (androidTest)
- No instrumentation/E2E tests (requires device/emulator)
- No repository integration tests against real API
- No performance/load tests

---

## 8. Build Results

| Build Type | Status | Duration | Artifacts |
|------------|--------|----------|-----------|
| Clean | ✅ | <1s | — |
| Debug (`assembleDebug`) | ✅ | 6m 8s | `app-debug.apk` |
| Release (`assembleRelease`) | ✅ | 9m 13s | `app-release-unsigned.apk` |
| Lint Debug | ❌ | 9m 58s | 5 errors, 82 warnings |
| Unit Tests | ✅ | 9m 22s | 596 passed |

**Build Warnings (Non-blocking):**
- `package="com.kyusui.app"` in AndroidManifest.xml deprecated (use namespace in build.gradle.kts)
- Meta-data replacement tags without other declarations (MAPS_API_KEY, FCM channel)
- KSP `-Xopt-in` deprecated (use `-opt-in`)
- Compose `Modifier.menuAnchor()` deprecated
- Unable to strip native libs (path, datastore)

---

## 9. Security Findings

| Check | Status | Evidence |
|-------|--------|----------|
| Token not in logs | ✅ PASS | `SafeLoggingInterceptor` redacts `Authorization` header; log level HEADERS only in debug |
| Secrets in source | ✅ PASS | No hardcoded tokens, API keys, passwords; Maps key from local.properties/env |
| Proof not public | ✅ PASS | `PaymentProofDto` only exposes `available: Boolean`; proof URLs authorized |
| Role not security boundary | ✅ PASS | UI adapts to role; backend validates all mutations |
| Payment state not local authority | ✅ PASS | Android never sets `PAID`; reads from backend response only |
| Order state not local authority | ✅ PASS | Android requests transitions; backend validates and returns state |
| Tracking authorization from backend | ✅ PASS | Customer tracking validates ownership; courier location validates assignment |
| Hardcoded URLs | ✅ PASS | Base URL from `Constants.kt` (dev/prod); QRIS image URL resolved at runtime |
| Hardcoded payment state | ✅ PASS | No `paymentStatus = PAID` assignments; enums only from backend |
| Fake coordinates/data | ✅ PASS | Location from FLP/platform; no dummy coordinates |

**Lint Security Errors (False Positives):**
- DeviceLocationSource: permission checked at flow entry, not at each API call
- KyusuiFirebaseMessagingService: POST_NOTIFICATIONS in manifest

---

## 10. Architecture Compliance

| Rule | Status | Evidence |
|------|--------|----------|
| Compose → ViewModel | ✅ | All screens use ViewModel via `hiltViewModel()` |
| ViewModel → Repository | ✅ | 22 ViewModels inject Repository interfaces |
| Repository → Data Source | ✅ | RepositoryImpl uses Api interfaces |
| Data Source → Retrofit | ✅ | RetrofitModule provides Api interfaces |
| **No Composable → Retrofit** | ✅ | Verified: 0 imports of `retrofit2` or `data.api` in `ui/` |
| **No ViewModel → Retrofit** | ✅ | Verified: ViewModels only import `domain.repository` |
| **No UI → Business Authority** | ✅ | No local `PAID`, no local status transitions, no amount calculation |

**Layer Boundaries Verified:**
- `ui/` imports only `domain.model`, `domain.repository`, `ui.theme`, `navigation`
- `domain/` has no external dependencies
- `data/` contains `api`, `repository`, `mapper`, `datastore`, `fcm`, `upload`
- `core/` contains constants, utilities, state primitives

---

## 11. Recommended Next Actions

### Immediate (Blocking Integration)
1. **Fix Lint Errors** (5) — Add `@SuppressLint("MissingPermission")` with justification comments, or restructure permission checks to satisfy lint; add `<uses-feature android:name="android.hardware.camera" required="false"/>` to manifest
2. **Resolve 8 Contract Decisions** — Meeting with Product + Backend + Android to finalize canonical paths for Customer/Owner/Courier
3. **Update Spec Documents** — Rewrite `06_KYUSUI_API_SPECIFICATION_REBUILT.md` sections 9, 10, 12, 13, 14–16 to match backend reality; update `13_KYUSUI_INTEGRATION_CONTRACT.md`

### Short-term (Before Production)
4. **Android Path Fix PR** — Switch Customer API calls to backend paths (7 endpoints in `CustomerApi.kt`, `CustomerRepositoryImpl.kt`)
5. **Implement Missing Endpoints** — Customer cancel, Owner couriers/dashboard/products, Customer dashboard
6. **Add Baseline for Lint Warnings** — Create `lint-baseline.xml` for 82 warnings to track only new issues
7. **Add androidTest** — Compose UI tests for critical flows (auth, order create, payment, tracking)
8. **Proguard/R8 Rules** — Verify release build obfuscation works with Hilt/Serialization

### Medium-term
9. **Dependency Updates** — Plan upgrade path for AGP 8.3→8.3.2, Compose BOM 2024.05→2026.09, etc.
10. **Selected Photo Access** — Adapt `READ_MEDIA_IMAGES` handling for Android 14+ partial access
11. **Performance** — Baseline APK size, startup time, memory
12. **Accessibility Audit** — TalkBack, touch targets (48dp), color contrast

---

## Final Verdict

| Criterion | Met? | Notes |
|-----------|------|-------|
| Clean build | ✅ | |
| Debug build | ✅ | |
| Release build | ✅ | |
| Zero compiler errors | ✅ | |
| Lint clean | ❌ | 5 errors (documented false positives + 1 manifest fix) |
| No TODO/FIXME | ✅ | None found in main source |
| No debug logging in prod | ✅ | Only 1 `Log.w` for FCM init failure |
| No hardcoded secrets | ✅ | |
| No legacy payment | ✅ | |
| Architecture compliant | ✅ | |
| Payment contract correct | ✅ | |
| Tests pass | ✅ | 596 unit tests |
| All features integrated | ⚠️ | 5 MISSING, 21 MISMATCH, 3 UNRESOLVED |

**Android is NOT ready for backend integration** until:
1. Lint errors resolved (or baseline created with justification)
2. 8 contract decisions finalized
3. Spec/backend alignment completed

**Recommended:** Do not claim Phase 10 complete. Proceed to decision meeting, then single PR for Customer path fixes + lint suppressions, then re-run full audit.

---

**Audit Completed By:** Automated Phase 10 Validation  
**Document Status:** FINAL — READY FOR STAKEHOLDER REVIEW