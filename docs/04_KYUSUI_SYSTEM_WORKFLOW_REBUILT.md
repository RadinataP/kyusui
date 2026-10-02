# 04_KYUSUI_SYSTEM_WORKFLOW.md

**Project:** KYŪSUI\
**Study Case:** Berkah Water\
**Document:** `04_KYUSUI_SYSTEM_WORKFLOW.md`\
**Status:** System Workflow Baseline — Rebuilt
**Authority:** `00_KYUSUI_MASTER_SPECIFICATION.md` →
`01_KYUSUI_PROJECT_RULES.md` → `02_KYUSUI_SYSTEM_ARCHITECTURE.md` →
`03_KYUSUI_UI_UX_SPECIFICATION.md`

------------------------------------------------------------------------

## 1. Purpose

Dokumen ini mendefinisikan workflow fungsional dan aliran interaksi
KYŪSUI dari sudut pandang system analysis.

Dokumen mencakup:

1.  Use Case Diagram
2.  Activity Customer
3.  Activity Owner
4.  Activity Courier
5.  Activity Order
6.  Activity Payment QRIS
7.  Activity Payment Cash
8.  Activity Courier Assignment
9.  Activity Delivery
10. Activity Tracking
11. Activity Completion
12. Sequence Login
13. Sequence Order
14. Sequence Payment
15. Sequence Assignment
16. Sequence Tracking
17. Sequence Completion
18. State Order
19. State Payment
20. Context Diagram
21. DFD Level 0
22. DFD Level 1

Dokumen ini adalah dokumen analisis dan pemodelan. Tidak berisi source
code aplikasi.

------------------------------------------------------------------------

## 2. Baseline and Consistency Rules

### 2.1 Actors

Tiga aktor utama:

-   Customer / Pelanggan
-   Owner / Pemilik Depot
-   Courier / Pengantar Galon

Sistem eksternal yang berinteraksi secara teknis:

-   Firebase Cloud Messaging untuk push notification bila digunakan
-   Google Maps / Location Service sebagai pendukung lokasi dan
    visualisasi peta

### 2.2 Core Business Flow

``` text
Login
  ↓
Create Order
  ↓
Select Payment
  ↓
Payment follows QRIS or CASH workflow
  ↓
Owner Receives & Processes Order
  ↓
Courier Assignment
  ↓
Delivery
  ↓
Tracking
  ↓
Customer Receives Order
  ↓
Completion
  ↓
Order History
```

### 2.3 Order Status Baseline

Status yang digunakan pada workflow:

``` text
Menunggu Pembayaran
Menunggu Diproses
Diproses
Ditugaskan
Dalam Pengantaran
Selesai
```

Status tersebut mengikuti baseline UI/UX. fileciteturn1file8

### 2.4 Payment Status Baseline

Payment hanya menggunakan tiga canonical state:

```text
PENDING
WAITING_VERIFICATION
PAID
```

### PENDING

Payment record telah dibuat tetapi belum terdapat payment proof yang sedang menunggu verifikasi.

### WAITING_VERIFICATION

Customer telah mengunggah payment proof untuk QRIS dan proof tersebut menunggu pemeriksaan Owner.

### PAID

Payment telah dinyatakan berhasil berdasarkan workflow pembayaran yang sah.

Untuk QRIS:

```text
PENDING
   ↓
WAITING_VERIFICATION
   ↓
PAID
```

Untuk rejection:

```text
WAITING_VERIFICATION
   ↓
PENDING
   ↓
Customer uploads new proof
   ↓
WAITING_VERIFICATION
```

Untuk CASH:

```text
PENDING
   ↓
Customer pays Courier
   ↓
Courier confirms "Uang Diterima"
   ↓
PAID
```

Tidak digunakan:

```text
PROCESSING
FAILED
EXPIRED
CONFIRMED
```

`PAID` merupakan satu-satunya canonical success state untuk seluruh metode pembayaran.

## 2.5 Payment Method Baseline

Payment method:

```text
QRIS
CASH
```

Tidak digunakan:

```text
MIDTRANS
DIGITAL
PAYMENT_GATEWAY
```

QRIS merupakan Static QRIS milik Berkah Water yang ditampilkan aplikasi dari Active QRIS configuration pada `business_settings`.

Active QRIS merupakan QRIS yang saat ini ditetapkan Owner sebagai QRIS yang digunakan customer.

QRIS tidak menggunakan:

- payment gateway;
- provider webhook;
- dynamic QRIS;
- automatic payment verification.

## 2.6 QRIS Payment Rules

QRIS flow:

```text
Order
  ↓
Payment PENDING
  ↓
Customer obtains active QRIS
  ↓
Customer completes payment externally
  ↓
Customer uploads payment proof
  ↓
Payment WAITING_VERIFICATION
  ↓
Owner verifies proof
  ↓
PAID
```

Customer tidak dapat menetapkan sendiri:

```text
WAITING_VERIFICATION → PAID
```

Customer hanya dapat:

- melihat active QRIS;
- melakukan pembayaran;
- mengunggah payment proof;
- mengunggah ulang proof setelah rejection sesuai workflow.

Owner bertanggung jawab melakukan verifikasi payment proof QRIS.

Tidak ada automatic payment verification.

## 2.7 QRIS Rejection Rule

Jika Owner menolak payment proof:

```text
WAITING_VERIFICATION
        ↓
Owner rejects proof
        ↓
PENDING
        ↓
Customer may upload new proof
```

Rejection tidak membuat payment menjadi `FAILED`.

Payment tetap berada pada lifecycle yang memungkinkan customer memperbaiki proof.

## 2.8 CASH Payment Rules

Customer memilih:

```text
CASH
```

Payment dibuat:

```text
PENDING
```

Pemilihan CASH bukan bukti pembayaran.

Namun CASH tidak menghalangi order untuk masuk ke workflow pemrosesan.

```text
CASH selected
      ↓
Payment PENDING
      ↓
Order may be processed
```

Pada delivery:

```text
Courier delivers order
      ↓
Customer pays cash
      ↓
Courier confirms "Uang Diterima"
      ↓
Backend validates courier assignment
      ↓
Payment = PAID
```

Courier merupakan actor yang mengonfirmasi bahwa uang tunai telah diterima.

Owner tidak melakukan konfirmasi pembayaran Cash.

Customer tidak dapat mengonfirmasi pembayaran Cash untuk dirinya sendiri.

Courier hanya dapat mengonfirmasi pembayaran Cash untuk delivery assignment yang sah dan menjadi tanggung jawabnya.

## 2.9 Payment and Order Independence Rule

KYŪSUI tidak menggunakan rule global:

```text
Order hanya dapat diproses jika payment = PAID
```

Rule tersebut tidak berlaku.

Sebaliknya:

```text
QRIS:
Payment harus PAID sebelum order memasuki processing workflow.

CASH:
Payment dapat tetap PENDING ketika order diproses dan dikirim.
Payment menjadi PAID ketika customer membayar Courier dan Courier
mengonfirmasi "Uang Diterima".
```

Completion order tetap merupakan bagian dari delivery/order workflow.



# 3. Use Case Diagram

Use Case menggambarkan fungsi utama yang tersedia untuk tiga role dan
workflow payment QRIS/CASH.

``` mermaid
flowchart LR
    C[Customer]
    O[Owner / Pemilik Depot]
    D[Courier / Pengantar]

    subgraph K["KYŪSUI System"]
        UC1((Register))
        UC2((Login))
        UC3((Create Order))
        UC4((Set Delivery Location))
        UC5((Select Payment Method))
        UC6((View Static QRIS / Upload QRIS Proof))
        UC7((Review QRIS Proof))
        UC21((Confirm Cash: Uang Diterima))
        UC8((View Order Status))
        UC9((View Order History))
        UC10((Track Courier))
        UC11((Receive Order))
        UC12((Manage Orders))
        UC13((Process Order))
        UC14((Assign Courier))
        UC15((View Assigned Order))
        UC16((Start Delivery))
        UC17((Send Courier Location))
        UC18((Update Delivery Status))
        UC19((Complete Order))
        UC20((Logout))
    end

    C --> UC1
    C --> UC2
    C --> UC3
    C --> UC4
    C --> UC5
    C --> UC6
    C --> UC8
    C --> UC9
    C --> UC10
    C --> UC11
    C --> UC20

    O --> UC2
    O --> UC7
    O --> UC12
    O --> UC13
    O --> UC14
    O --> UC8
    O --> UC20

    D --> UC2
    D --> UC15
    D --> UC16
    D --> UC17
    D --> UC18
    D --> UC19
    D --> UC21
    D --> UC20

    UC3 -. includes .-> UC4
    UC3 -. includes .-> UC5
    UC14 -. enables .-> UC15
    UC16 -. enables .-> UC17
    UC16 -. enables .-> UC10
```

Catatan: UI role-based mengikuti single Android application; UI tidak
menjadi security boundary. Authorization tetap dilakukan backend.
fileciteturn1file5

------------------------------------------------------------------------

# 4. Activity Diagram --- Customer

``` mermaid
flowchart TD
    A([Start]) --> B[Open KYŪSUI]
    B --> C{Authenticated?}

    C -- No --> D[Login / Register]
    D --> E{Login Success?}
    E -- No --> D
    E -- Yes --> F[Customer Home]

    C -- Yes --> F

    F --> G{Choose Activity}

    G -- Create Order --> H[Enter Quantity]
    H --> I[Set Delivery Location]
    I --> J[Review Order]
    J --> K[Select Payment Method]

    K --> L{Payment Method}
    L -- QRIS --> M[Display Active Static QRIS]
    M --> N[Customer Pays Externally]
    N --> O[Upload QRIS Proof]
    O --> P[Show WAITING_VERIFICATION]
    L -- CASH --> Q[Show Cash on Delivery instruction]
    Q --> P

    G -- Orders --> P
    P --> R{Delivery Active?}
    R -- Yes --> S[Open Tracking]
    R -- No --> T[View Order Detail]

    G -- History --> U[View Order History]
    G -- Profile --> V[View Profile]

    S --> W[Receive Delivery]
    W --> X[Order Completed]
    X --> U

    T --> G
    U --> G
    V --> G

    G --> Y[Logout]
    Y --> Z([End])
```

------------------------------------------------------------------------

# 5. Activity Diagram --- Owner

``` mermaid
flowchart TD
    A([Start]) --> B[Login]
    B --> C{Login Success?}
    C -- No --> B
    C -- Yes --> D[Owner Dashboard]

    D --> E[View Incoming Orders]
    E --> F[Select Order]
    F --> G[View Customer, Quantity, Location, Payment Status]

    G --> H{Payment Method / Status}
    H -- QRIS PAID --> I[Process Order]
    H -- QRIS WAITING_VERIFICATION --> J[Review QRIS Proof]
    J --> K{Approve?}
    K -- Yes --> I
    K -- No --> H
    H -- CASH PENDING or PAID --> I
    I --> L[Open Courier Assignment]

    L --> M[Select Available Courier]
    M --> N[Create Assignment]
    N --> O[Order Status = Ditugaskan]

    O --> P[Monitor Order / Delivery Status]
    P --> Q{Delivery Completed?}
    Q -- No --> P
    Q -- Yes --> R[View Completed Order]

    D --> S[View Payment Information]
    D --> T[View Order History]

    R --> U[Logout]
    S --> U
    T --> U
    U --> V([End])
```

Owner melakukan Manual Owner Verification untuk QRIS Proof dan tidak melakukan Cash confirmation. CASH dapat diproses saat payment masih `PENDING`; QRIS memerlukan `PAID` sebelum processing.

------------------------------------------------------------------------

# 6. Activity Diagram --- Courier

``` mermaid
flowchart TD
    A([Start]) --> B[Login]
    B --> C{Login Success?}
    C -- No --> B
    C -- Yes --> D[Courier Dashboard]

    D --> E[View Assigned Deliveries]
    E --> F{Assignment Available?}

    F -- No --> G[Wait / Refresh]
    G --> E

    F -- Yes --> H[Open Assigned Order]
    H --> I[View Customer and Delivery Location]
    I --> J[Start Delivery]
    J --> K[Order Status = Dalam Pengantaran]

    K --> L[Enable Location Context]
    L --> M[Obtain GPS Location]
    M --> N[Send Location to Backend]
    N --> O[Continue Delivery]

    O --> P{Arrived at Customer?}
    P -- No --> M
    P -- Yes --> Q[Deliver Galon]

    Q --> R{Order Received?}
    R -- No --> Q
    R -- Yes --> S[Update Delivery / Completion Status]
    S --> T[Order Status = Selesai]

    T --> U[Delivery History]
    U --> V[Logout]
    V --> W([End])
```

Tracking location hanya dilakukan pada delivery context yang valid.
Arsitektur tracking menggunakan Fused Location Provider → backend →
persistence → customer map. fileciteturn1file4

------------------------------------------------------------------------

# 7. Activity Diagram --- Order

``` mermaid
flowchart TD
    A([Start]) --> B[Customer Creates Order]
    B --> C[Input Quantity]
    C --> D[Set Delivery Location]
    D --> E[Review Order]
    E --> F[Create Order]

    F --> G[Order Created]
    G --> H[Select Payment Method]

    H --> I{Payment Method}
    I -- QRIS --> J[Persist payment = PENDING]
    J --> K[Display Active Static QRIS]
    K --> L[Customer Pays Externally]
    L --> M[Upload QRIS Proof]
    M --> N[Payment = WAITING_VERIFICATION]
    N --> O{Owner Approves Proof?}
    O -- No --> J
    O -- Yes --> P[Payment = PAID]
    I -- CASH --> Q[Persist payment = PENDING]
    Q --> R[Order Ready for Processing]
    P --> S[Owner Receives Order]
    R --> S
    S --> T[Owner Processes Order]
    T --> U[Order Ready for Delivery]
    U --> V[Courier Assignment]
    V --> W[Delivery]
    W --> X[Tracking]
    X --> Y[Customer Receives Order]
    Y --> Z[Order Completed]
    Z --> AA[History]
    AA --> AB([End])
```

Untuk CASH, Assigned Courier mengonfirmasi `Uang Diterima` setelah pembayaran tunai diterima pada delivery. Konfirmasi mengubah payment dari `PENDING` menjadi `PAID` tanpa menghalangi proses order sebelumnya.

------------------------------------------------------------------------

# 8. Activity Diagram --- Payment QRIS

``` mermaid
flowchart TD
    A([Start]) --> B[Customer Selects QRIS]
    B --> C[Persist payment = PENDING]
    C --> D[Read Active QRIS from business_settings]
    D --> E[Display Static QRIS]
    E --> F[Customer Pays Externally]
    F --> G[Customer Uploads QRIS Proof]
    G --> H[Payment = WAITING_VERIFICATION]
    H --> I[Owner Reviews Proof]
    I --> J{Approve?}
    J -- Yes --> K[Payment = PAID]
    J -- No --> L[Payment = PENDING]
    L --> M[Customer may upload replacement proof]
    M --> G
    K --> N([End])
```

Customer tidak dapat menetapkan sendiri status `PAID`. Re-upload QRIS Proof memperbarui payment yang sama dan tidak membuat payment record baru.

------------------------------------------------------------------------

# 9. Activity Diagram --- Payment Cash

``` mermaid
flowchart TD
    A([Start]) --> B[Customer Selects CASH]
    B --> C[Persist payment = PENDING]
    C --> D[Order proceeds through processing, assignment, and delivery]
    D --> E[Customer pays cash to Assigned Courier]
    E --> F[Assigned Courier selects Uang Diterima]
    F --> G[Backend validates Courier authentication and active assignment]
    G --> H{Valid CASH payment and assignment?}
    H -- No --> I[Reject confirmation; payment remains PENDING]
    H -- Yes --> J[Payment = PAID]
    I --> K([End])
    J --> K
```

Customer dan Owner tidak dapat mengonfirmasi CASH. Hanya Assigned Courier yang dapat melakukan aksi `Uang Diterima`.

------------------------------------------------------------------------

# 10. Activity Diagram --- Courier Assignment

``` mermaid
flowchart TD
    A([Start]) --> B[Owner Opens Processed Order]
    B --> C[View Eligible Courier Data]
    C --> D[Select Courier]
    D --> E[Submit Assignment]
    E --> F[Backend Validates Owner Authorization]
    F --> G{Authorized and Valid?}

    G -- No --> H[Reject Assignment]
    H --> I([End])

    G -- Yes --> J[Create Delivery Assignment]
    J --> K[Update Order Status = Ditugaskan]
    K --> L[Notify Courier if notification is enabled]
    L --> M[Courier Sees Assigned Order]
    M --> N([End])
```

------------------------------------------------------------------------

# 11. Activity Diagram --- Delivery

``` mermaid
flowchart TD
    A([Start]) --> B[Courier Opens Assignment]
    B --> C[Review Order]
    C --> D[Review Customer Location]
    D --> E[Start Delivery]
    E --> F[Update Order Status = Dalam Pengantaran]

    F --> G[Obtain Current Location]
    G --> H[Send Location]
    H --> I[Move Toward Customer]
    I --> J{Arrived?}

    J -- No --> G
    J -- Yes --> K[Deliver Galon]
    K --> L{Customer Receives?}

    L -- No --> K
    L -- Yes --> M[Proceed to Completion]
    M --> N([End])
```

------------------------------------------------------------------------

# 12. Activity Diagram --- Tracking

``` mermaid
flowchart TD
    A([Start]) --> B[Courier Has Active Delivery]
    B --> C[Request Device Location]
    C --> D{Location Permission / Location Available?}

    D -- No --> E[Show Location Error / Unavailable]
    E --> C

    D -- Yes --> F[Send Courier Location to Backend]
    F --> G[Backend Validates Courier + Assignment]
    G --> H{Valid Delivery Context?}

    H -- No --> I[Reject Location Update]
    I --> J([End])

    H -- Yes --> K[Store / Update Location]
    K --> L[Customer Requests Tracking]
    L --> M[Backend Returns Latest Valid Location]
    M --> N[Render Google Map]
    N --> O{Delivery Still Active?}

    O -- Yes --> L
    O -- No --> P[Stop Tracking Context]
    P --> Q([End])
```

Baseline arsitektur tidak menetapkan WebSocket sebagai teknologi wajib;
REST-based retrieval/polling digunakan sebagai baseline. Interval
polling belum dikunci. fileciteturn1file4

------------------------------------------------------------------------

# 13. Activity Diagram --- Completion

``` mermaid
flowchart TD
    A([Start]) --> B[Courier Arrives]
    B --> C[Deliver Galons]
    C --> D{Customer Receives Order?}

    D -- No --> E[Continue Delivery Handling]
    E --> C

    D -- Yes --> F[Submit Completion Status]
    F --> G[Backend Validates Assignment and Order]
    G --> H{Valid?}

    H -- No --> I[Reject Completion]
    I --> J([End])

    H -- Yes --> K[Update Order Status = Selesai]
    K --> L[Close Active Delivery Context]
    L --> M[Make Order Available in History]
    M --> N[Customer Sees Completed Order]
    N --> O([End])
```

------------------------------------------------------------------------

# 14. Sequence Diagram --- Login

``` mermaid
sequenceDiagram
    actor U as User
    participant A as Android App
    participant API as Laravel API
    participant DB as MySQL

    U->>A: Enter credentials
    A->>API: POST /login
    API->>DB: Find user
    DB-->>API: User record
    API->>API: Verify credentials
    API->>API: Generate Sanctum token
    API-->>A: Token + user + role
    A->>A: Store session/token state
    A-->>U: Open role-specific home
```

------------------------------------------------------------------------

# 15. Sequence Diagram --- Order

``` mermaid
sequenceDiagram
    actor C as Customer
    participant A as Android
    participant API as Laravel API
    participant DB as MySQL

    C->>A: Enter quantity
    C->>A: Select delivery location
    C->>A: Confirm order
    A->>API: POST /orders
    API->>API: Authenticate + authorize customer
    API->>API: Validate order data
    API->>DB: Create order
    DB-->>API: Order created
    API-->>A: Order detail + payment state
    A-->>C: Show payment selection
```

------------------------------------------------------------------------

# 16. Sequence Diagram --- Payment

``` mermaid
sequenceDiagram
    actor C as Customer
    participant A as Android
    participant API as Laravel API
    participant DB as MySQL
    actor O as Owner
    actor D as Courier

    alt QRIS
        C->>A: Select QRIS
        A->>API: Create order/payment with QRIS
        API->>DB: Persist payment = PENDING
        A->>API: Get Active QRIS
        API->>DB: Read business_settings
        API-->>A: Static QRIS
        C->>A: Upload QRIS Proof after external payment
        A->>API: Upload QRIS Proof
        API->>DB: Update payment = WAITING_VERIFICATION
        O->>API: Approve or reject QRIS Proof
        API->>DB: Update payment = PAID or PENDING
        API-->>A: Authoritative payment status
    else CASH
        C->>A: Select CASH
        A->>API: Create order/payment with CASH
        API->>DB: Persist payment = PENDING
        API-->>A: Cash on Delivery instruction
        Note over API,DB: Order may proceed while CASH is PENDING
        D->>API: Uang Diterima
        API->>API: Validate Courier authentication and active assignment
        API->>DB: Update payment = PAID
    end
```

------------------------------------------------------------------------

# 17. Sequence Diagram --- Assignment

``` mermaid
sequenceDiagram
    actor O as Owner
    participant A as Android
    participant API as Laravel API
    participant DB as MySQL
    actor D as Courier

    O->>A: Open processed order
    A->>API: Request courier options
    API->>DB: Validate owner + retrieve courier data
    DB-->>API: Courier data
    API-->>A: Courier options
    O->>A: Select courier
    A->>API: Create assignment
    API->>API: Validate owner authorization
    API->>DB: Create delivery assignment
    API->>DB: Update order = Ditugaskan
    DB-->>API: Assignment saved
    API-->>A: Assignment success
    API-->>D: Assignment notification if enabled
    D->>API: Request assigned orders
    API-->>D: Assigned order
```

------------------------------------------------------------------------

# 18. Sequence Diagram --- Tracking

``` mermaid
sequenceDiagram
    actor D as Courier
    participant CA as Courier Android
    participant API as Laravel API
    participant DB as MySQL
    actor C as Customer
    participant AA as Customer Android

    loop While Delivery Active
        D->>CA: Active delivery
        CA->>CA: Get GPS location
        CA->>API: Submit courier location
        API->>API: Authenticate + authorize assignment
        API->>DB: Store latest location
    end

    C->>AA: Open tracking
    AA->>API: Get tracking data
    API->>DB: Read latest valid location
    DB-->>API: Location + order state
    API-->>AA: Tracking response
    AA->>AA: Render Google Map
    AA-->>C: Show courier position
```

------------------------------------------------------------------------

# 19. Sequence Diagram --- Completion

``` mermaid
sequenceDiagram
    actor D as Courier
    participant A as Courier Android
    participant API as Laravel API
    participant DB as MySQL
    actor C as Customer
    participant CA as Customer Android

    D->>A: Confirm delivery completed
    A->>API: Submit completion
    API->>API: Authenticate courier
    API->>API: Validate assignment ownership
    API->>DB: Update delivery status
    API->>DB: Update order = Selesai
    DB-->>API: Saved
    API-->>A: Completion success
    API-->>CA: Updated order state available
    CA->>API: Refresh order
    API-->>CA: Completed order
    CA-->>C: Show Selesai
```

------------------------------------------------------------------------

# 20. State Diagram --- Order

``` mermaid
stateDiagram-v2
    [*] --> Menunggu_Pembayaran: Order created

    Menunggu_Pembayaran --> Menunggu_Diproses: QRIS PAID or CASH selected
    Menunggu_Pembayaran --> Menunggu_Pembayaran: Payment pending / retry

    Menunggu_Diproses --> Diproses: Owner processes order
    Diproses --> Ditugaskan: Courier assigned
    Ditugaskan --> Dalam_Pengantaran: Courier starts delivery
    Dalam_Pengantaran --> Selesai: Customer receives order

    Selesai --> [*]
```

Catatan: cancellation state tidak dimasukkan karena belum ditetapkan
sebagai requirement final.

------------------------------------------------------------------------

# 21. State Diagram --- Payment

``` mermaid
stateDiagram-v2
    [*] --> PENDING: Payment record created
    PENDING --> WAITING_VERIFICATION: QRIS Proof uploaded
    WAITING_VERIFICATION --> PAID: Owner approves QRIS Proof
    WAITING_VERIFICATION --> PENDING: Owner rejects QRIS Proof
    PENDING --> PAID: Assigned Courier confirms CASH
    PAID --> [*]
```

Transition `PENDING` ke `WAITING_VERIFICATION` hanya berlaku untuk QRIS. Transition `PENDING` ke `PAID` hanya berlaku untuk CASH setelah validasi Assigned Courier.

------------------------------------------------------------------------

# 22. Context Diagram

Context diagram menempatkan KYŪSUI sebagai satu proses utama dan
menunjukkan pertukaran informasi dengan entitas eksternal.

``` mermaid
flowchart LR
    C[Customer]
    O[Owner / Pemilik Depot]
    D[Courier / Pengantar]
    F[Firebase Cloud Messaging]

    S((KYŪSUI System))

    C -->|Registration, login, order, location, payment selection, tracking request| S
    S -->|Order status, payment status, tracking, history, notifications| C

    O -->|Login, order processing, payment verification, courier assignment| S
    S -->|Order data, payment data, assignment status, delivery status| O

    D -->|Login, assignment, delivery status, GPS location| S
    S -->|Assigned orders, customer/location information, delivery status| D

    S -->|Push notification event| F
    F -->|Push notification delivery| C
    F -->|Push notification delivery| O
    F -->|Push notification delivery| D
```

------------------------------------------------------------------------

# 23. DFD Level 0

Pada dokumen ini, DFD Level 0 digunakan sebagai dekomposisi utama sistem
ke proses-proses inti.

``` mermaid
flowchart LR
    C[Customer]
    O[Owner]
    D[Courier]

    P1((1. Authentication))
    P2((2. Order Management))
    P3((3. Payment Management))
    P4((4. Courier Assignment))
    P5((5. Delivery & Tracking))
    P6((6. Completion & History))

    DS1[(D1 Users)]
    DS2[(D2 Orders)]
    DS3[(D3 Payments)]
    DS4[(D4 Delivery Assignments)]
    DS5[(D5 Courier Locations)]

    C -->|Credentials| P1
    O -->|Credentials| P1
    D -->|Credentials| P1
    P1 -->|Auth result / role| C
    P1 -->|Auth result / role| O
    P1 -->|Auth result / role| D
    P1 <--> DS1

    C -->|Order data + delivery location| P2
    P2 -->|Order status| C
    O -->|Process order| P2
    P2 -->|Order information| O
    P2 <--> DS2

    C -->|Payment selection| P3
    P3 -->|Payment status| C
    O -->|Payment verification/view| P3
    P3 -->|Payment information| O
    P3 <--> DS3
    D -->|CASH Uang Diterima| P3

    O -->|Courier assignment| P4
    P4 -->|Assignment result| O
    P4 -->|Assigned order| D
    P4 <--> DS4
    P4 --> DS2

    D -->|GPS location + delivery status| P5
    P5 -->|Assigned delivery information| D
    C -->|Tracking request| P5
    P5 -->|Courier location + delivery status| C
    P5 <--> DS5
    P5 <--> DS4
    P5 <--> DS2

    D -->|Completion confirmation| P6
    P6 -->|Completion result| D
    C -->|History request| P6
    P6 -->|Order history| C
    P6 --> DS2
```

------------------------------------------------------------------------

# 24. DFD Level 1 --- Order and Delivery Workflow

DFD Level 1 memperinci proses inti yang paling penting bagi KYŪSUI:
order → payment → processing → assignment → delivery → tracking →
completion.

``` mermaid
flowchart LR
    C[Customer]
    O[Owner]
    D[Courier]

    P21((2.1 Create Order))
    P22((2.2 Validate Order))
    P31((3.1 Create Payment))
    P32((3.2 Manage QRIS Proof))
    P33((3.3 Verify QRIS / Confirm CASH))
    P23((2.3 Process Order))
    P41((4.1 Create Assignment))
    P42((4.2 Validate Assignment))
    P51((5.1 Start Delivery))
    P52((5.2 Receive Location))
    P53((5.3 Provide Tracking))
    P61((6.1 Confirm Completion))
    P62((6.2 Update History))

    D1[(D1 Users)]
    D2[(D2 Orders)]
    D3[(D3 Payments)]
    D4[(D4 Delivery Assignments)]
    D5[(D5 Courier Locations)]

    C -->|Quantity + location| P21
    P21 -->|Order draft| P22
    P22 -->|Validated order| D2
    D2 -->|Order data| P21
    P21 -->|Order/payment selection| C

    C -->|Payment method| P31
    P31 -->|Payment record| D3

    P31 -->|QRIS Proof or CASH selection| P32
    P32 -->|Payment status| D3
    O -->|QRIS review action| P33
    D -->|CASH Uang Diterima| P33
    P33 -->|Validated payment status| D3
    P33 -->|Payment state| D2
    P33 -->|Payment result| C

    D2 -->|Eligible order| P23
    O -->|Process order action| P23
    P23 -->|Updated order| D2
    P23 -->|Processing result| O

    O -->|Courier selection| P41
    P41 -->|Assignment candidate| P42
    P42 -->|Courier authorization data| D1
    P42 -->|Assignment| D4
    P42 -->|Assigned order| D
    P42 -->|Assignment result| O
    P42 -->|Order status| D2

    D -->|Start delivery| P51
    P51 -->|Delivery status| D2
    P51 -->|Active assignment| D4

    D -->|GPS coordinates| P52
    P52 -->|Validate active assignment| D4
    P52 -->|Courier location| D5

    C -->|Tracking request| P53
    P53 -->|Read active delivery| D4
    P53 -->|Read latest location| D5
    P53 -->|Tracking data| C

    D -->|Completion confirmation| P61
    P61 -->|Validate assignment| D4
    P61 -->|Order completed| D2
    P61 -->|Completion result| D

    C -->|History request| P62
    P62 -->|Completed orders| D2
    P62 -->|Order history| C
```

------------------------------------------------------------------------

# 25. Cross-Diagram Consistency Matrix

  ----------------------------------------------------------------------------------------
  Business       Activity                 Sequence       State            DFD
  Process                                                                 
  -------------- ------------------------ -------------- ---------------- ----------------
  Login          Customer/Owner/Courier   Login          Authentication   1\.
                                                         session          Authentication

  Create Order   Order                    Order          Menunggu         2.1 Create Order
                                                         Pembayaran       

   QRIS           Payment QRIS             Payment        PENDING →        3.2--3.3
   Payment                                                WAITING_VERIFICATION → PAID

   Cash Payment   Payment Cash             Payment        PENDING → PAID   3.1--3.3

  Process Order  Owner                    Order          Menunggu         2.3
                                                         Diproses →       
                                                         Diproses         

  Assignment     Courier Assignment       Assignment     Diproses →       4.1--4.2
                                                         Ditugaskan       

  Delivery       Delivery                 Tracking /     Ditugaskan →     5.1
                                          Completion     Dalam            
                                                         Pengantaran      

  Tracking       Tracking                 Tracking       Dalam            5.2--5.3
                                                         Pengantaran      

  Completion     Completion               Completion     Dalam            6.1
                                                         Pengantaran →    
                                                         Selesai          

  History        Customer                 Completion     Selesai          6.2
  ----------------------------------------------------------------------------------------

------------------------------------------------------------------------

# 26. End-to-End System Workflow

``` mermaid
flowchart TD
    A([START]) --> B[Login]
    B --> C[Customer Creates Order]
    C --> D[Set Quantity]
    D --> E[Set Delivery Location]
    E --> F[Select Payment]

    F --> G{Payment Method}

    G -- QRIS --> H[Display Active Static QRIS]
    H --> I[Customer Pays Externally and Uploads QRIS Proof]
    I --> J[WAITING_VERIFICATION]
    J --> K{Owner Approves?}
    K -- No --> L[PENDING: customer may re-upload]
    L --> I
    K -- Yes --> M[PAID]
    G -- CASH --> N[PENDING: Cash on Delivery]
    N --> O[Owner Receives Order]
    M --> O
    O --> P[Owner Processes Order]
    P --> Q[Courier Assignment]
    Q --> R[Courier Receives Assignment]
    R --> S[Courier Starts Delivery]
    S --> T[Courier Sends Location]
    T --> U[Customer Tracks Courier]
    U --> V{Customer Receives Order?}
    V -- No --> T
    V -- Yes --> W[If CASH: Assigned Courier selects Uang Diterima]
    W --> X[Completion]
    X --> Y[Order Status = Selesai]
    Y --> Z[Order Appears in History]
    Z --> AA([END])
```

------------------------------------------------------------------------

# 27. System Analysis Notes

## 27.1 Source of Truth

Data flow mengikuti:

``` text
Android
  ↓ HTTPS / REST
Laravel API
  ↓
MySQL
```

Android tidak mengakses MySQL secara langsung. fileciteturn1file4

## 27.2 Authorization

UI role-based hanya mengatur pengalaman pengguna.

Security boundary berada pada backend:

``` text
Authenticated User
        ↓
Role Check
        ↓
Ownership / Relationship Check
        ↓
Business Validation
        ↓
Action
```

## 27.3 Payment

Untuk QRIS:

``` text
Customer → Android → Laravel API → MySQL
Customer pays externally using Active Static QRIS
Customer uploads QRIS Proof → WAITING_VERIFICATION
Owner approves/rejects through Laravel API → PAID/PENDING
```

Untuk CASH, Assigned Courier mengirim aksi `Uang Diterima` ke Laravel API setelah menerima uang. Backend memvalidasi authentication, assignment, payment method, dan payment state sebelum menetapkan `PAID`. Client tidak dapat menetapkan sendiri status pembayaran.

## 27.4 Tracking

Untuk tracking:

``` text
Courier
  ↓
Fused Location Provider
  ↓
Courier App
  ↓
Laravel API
  ↓
MySQL / Tracking Store
  ↓
Customer API
  ↓
Google Maps
```

Arsitektur ini sesuai dengan baseline tracking pada system architecture.
fileciteturn1file4

## 27.5 UI Alignment

UI/UX menetapkan tiga experience utama:

``` text
Customer
Memesan → Membayar → Memantau → Menerima

Owner
Menerima → Memproses → Menugaskan → Memantau

Courier
Menerima → Menuju Lokasi → Mengirim → Selesai
```

Workflow dokumen ini mempertahankan urutan tersebut.
fileciteturn1file5

------------------------------------------------------------------------

# 28. Unresolved Decisions

Diagram tidak boleh dianggap menetapkan keputusan bisnis yang belum
disetujui.

### UD-01 --- Cancellation

**Question:** Apakah Customer dapat membatalkan order dan pada state
mana?

**Current:** UNRESOLVED.

### UD-02 --- Assignment Acceptance

**Question:** Apakah Courier harus menerima atau dapat menolak
assignment?

**Current:** UNRESOLVED.

### UD-03 --- Tracking Interval

**Question:** Berapa interval pengiriman dan pembaruan lokasi GPS?

**Current:** UNRESOLVED.

Architecture juga menyatakan bahwa polling frequency belum dikunci.
fileciteturn1file4

------------------------------------------------------------------------

# 29. Final Workflow Baseline

Workflow utama KYŪSUI dapat diringkas menjadi:

``` text
AUTHENTICATION
      ↓
ORDER CREATION
      ↓
PAYMENT
 ┌────┴────┐
  ↓         ↓
 QRIS      CASH
  ↓         ↓
STATIC QRIS +     CASH ON DELIVERY +
MANUAL OWNER      ASSIGNED COURIER
VERIFICATION      CONFIRMATION
  └────┬────┘
      ↓
ORDER PROCESSING
      ↓
COURIER ASSIGNMENT
      ↓
DELIVERY
      ↓
GPS LOCATION UPDATE
      ↓
CUSTOMER TRACKING
      ↓
CUSTOMER RECEIVES ORDER
      ↓
COMPLETION
      ↓
ORDER HISTORY
```

Dokumen ini menjadi baseline workflow sebelum penyusunan detail
database, API contract, state transition matrix, dan implementation task
breakdown.


---

# REBUILT PAYMENT BASELINE

Dokumen ini menggunakan revisi payment architecture KYŪSUI sebagai baseline:

```text
Payment Method:
QRIS
CASH

Payment Status:
PENDING
WAITING_VERIFICATION
PAID
```

QRIS:

```text
PENDING
→ Customer obtains active QRIS
→ Customer pays
→ Upload payment proof
→ WAITING_VERIFICATION
→ Owner verifies
→ PAID
```

QRIS rejection:

```text
WAITING_VERIFICATION
→ Owner rejects
→ PENDING
→ Customer may upload new proof
```

CASH:

```text
PENDING
→ Order may be processed
→ Delivery
→ Customer pays Courier
→ Courier confirms "Uang Diterima"
→ PAID
```

Aturan penting:

```text
DILARANG:
Order hanya dapat diproses jika payment = PAID
```

Yang berlaku:

```text
QRIS:
Payment PAID diperlukan sebelum order masuk processing workflow.

CASH:
Payment dapat PENDING selama order diproses dan dikirim.
Payment menjadi PAID ketika Courier mengonfirmasi uang telah diterima.
```

Tidak digunakan:

```text
MIDTRANS
PAYMENT GATEWAY
PROVIDER WEBHOOK
DYNAMIC QRIS
AUTOMATIC PAYMENT VERIFICATION
PROCESSING
FAILED
EXPIRED
CONFIRMED
```

Dokumen ini adalah versi REBUILT dan digunakan sebagai baseline workflow untuk sinkronisasi API, Android, database, notification, tracking, dan testing.
