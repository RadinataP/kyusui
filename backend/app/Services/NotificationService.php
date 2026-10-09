<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Nilai `schema_version` untuk kontrak payload saat ini.
     *
     * Spec 10 §19 mewajibkan key `schema_version` tetapi tidak menetapkan
     * nilainya. Klien Android sudah mendeklarasikan `schema_version: Int = 1`
     * pada `NotificationDataDto`, sehingga `1` adalah satu-satunya nilai yang
     * tidak memutus kontrak klien yang sudah ada. Penambahan event atau
     * perubahan bentuk payload harus menaikkan nilai ini.
     */
    private const SCHEMA_VERSION = 1;

    /**
     * Kontrak payload per event, mengikuti blok `Payload` pada spec 10 §8–§18.
     *
     * - `resource_type` dan `resource_id` disalin apa adanya dari blok payload.
     * - `routes` disalin dari blok `Target Route`/`Target`/`Targets`, dipakai
     *   sesuai role penerima.
     *
     * Dua nilai route merupakan turunan dari navigasi yang sama di spec 10 dan
     * ditandai pada komentar di bawahnya.
     */
    private const EVENT_CONTRACT = [
        'PAYMENT_QRIS_PROOF_UPLOADED' => [
            'resource_type' => 'PAYMENT',
            'resource_id' => 'payment_id',
            'routes' => ['OWNER' => 'owner/payment-verification/{order_id}'],
        ],
        'PAYMENT_QRIS_APPROVED' => [
            'resource_type' => 'PAYMENT',
            'resource_id' => 'payment_id',
            'routes' => ['CUSTOMER' => 'customer/orders/{order_id}/payment'],
        ],
        'PAYMENT_QRIS_REJECTED' => [
            'resource_type' => 'PAYMENT',
            'resource_id' => 'payment_id',
            'routes' => ['CUSTOMER' => 'customer/orders/{order_id}/payment'],
        ],
        'PAYMENT_CASH_CONFIRMED' => [
            'resource_type' => 'PAYMENT',
            'resource_id' => 'payment_id',
            'routes' => ['CUSTOMER' => 'customer/orders/{order_id}/payment'],
        ],
        'ORDER_CREATED' => [
            'resource_type' => 'ORDER',
            'resource_id' => 'order_id',
            'routes' => [
                'CUSTOMER' => 'customer/orders/{order_id}',
                'OWNER' => 'owner/orders/{order_id}',
            ],
        ],
        'ORDER_PROCESSED' => [
            'resource_type' => 'ORDER',
            'resource_id' => 'order_id',
            // Spec 10 §14 menyatakan target "Customer Order Detail" tanpa route
            // literal; route ini sama dengan target CUSTOMER pada spec 10 §13.
            'routes' => ['CUSTOMER' => 'customer/orders/{order_id}'],
        ],
        'COURIER_ASSIGNED' => [
            'resource_type' => 'DELIVERY_ASSIGNMENT',
            'resource_id' => 'assignment_id',
            'routes' => [
                'CUSTOMER' => 'customer/orders/{order_id}',
                'COURIER' => 'courier/deliveries/{order_id}',
            ],
        ],
        'DELIVERY_STARTED' => [
            'resource_type' => 'ORDER',
            'resource_id' => 'order_id',
            'routes' => ['CUSTOMER' => 'customer/orders/{order_id}/tracking'],
        ],
        'TRACKING_AVAILABLE' => [
            'resource_type' => 'DELIVERY_ASSIGNMENT',
            'resource_id' => 'assignment_id',
            // Spec 10 §17 menyatakan target "Tracking" tanpa route literal; route
            // ini sama dengan target tracking pada spec 10 §16.
            'routes' => ['CUSTOMER' => 'customer/orders/{order_id}/tracking'],
        ],
        'ORDER_COMPLETED' => [
            'resource_type' => 'ORDER',
            'resource_id' => 'order_id',
            'routes' => [
                'CUSTOMER' => 'customer/orders/{order_id}',
                'OWNER' => 'owner/orders/{order_id}',
                'COURIER' => 'courier/deliveries/{order_id}',
            ],
        ],
    ];

    public function sendToUser(
        int $userId,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): Notification {
        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        // Spec 10 §19 mewajibkan payload berisi kunci kontrak umum, dan
        // spec 13 §37 mengulang kunci minimum yang sama sebagai kontrak
        // integrasi tertinggi. Penyusunan payload dilakukan di satu titik
        // supaya semua event konsisten dan tidak ada call site yang
        // membangun bentuk payload sendiri.
        $notification->update([
            'data' => $this->payload($notification, $this->recipientRole($userId)),
        ]);

        Log::info('Notification created.', [
            'notification_id' => $notification->id,
            'user_id' => $userId,
            'type' => $type,
        ]);

        return $notification;
    }

    public function sendToRole(
        string $role,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): int {
        $userIds = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', $role))
            ->pluck('id');

        foreach ($userIds as $userId) {
            $this->sendToUser((int) $userId, $type, $title, $body, $data);
        }

        return $userIds->count();
    }

    /**
     * Menyusun payload notifikasi sesuai kontrak payload.
     *
     * Kunci yang sudah ada pada event tetap dipertahankan apa adanya, termasuk
     * `order_id`, `assignment_id`, dan `payment_id`. Pemeliharaan itu wajib
     * karena `StoreBusinessNotification` memakai kunci tersebut untuk
     * idempotency (spec 10 §28) melalui perbandingan `whereJsonContains`, dan
     * perubahan tipe pada kunci itu akan melonggarkan deduplikasi.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(Notification $notification, ?string $role): array
    {
        $data = $notification->data ?? [];
        $orderId = $data['order_id'] ?? null;
        $contract = self::EVENT_CONTRACT[$notification->type] ?? null;
        $resourceType = is_array($contract) ? $contract['resource_type'] : null;

        $resourceIdKey = is_array($contract) ? $contract['resource_id'] : null;
        $resourceId = is_string($resourceIdKey) ? ($data[$resourceIdKey] ?? null) : null;

        return array_merge($data, [
            'schema_version' => self::SCHEMA_VERSION,
            'notification_id' => (string) $notification->id,
            'type' => $notification->type,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId === null ? null : (string) $resourceId,
            'order_id' => $orderId,
            'target_route' => $this->targetRoute($contract, $role, $orderId),
            'created_at' => $notification->created_at,
        ], $this->eventSpecificFields($resourceType, $data));
    }

    /**
     * Event-specific field yang diwajibkan tabel spec 10 §19.
     *
     * Nilai yang sudah dikirim call site dipertahankan karena nilainya
     * mencerminkan keadaan pada saat event terjadi. Spec 10 §8–§18 mengikat
     * sebagian field pada nilai literal pada saat itu, jadi membaca ulang dari
     * database setelah commit dapat menghasilkan nilai yang berbeda bila order
     * sudah bergerak lebih lanjut. Karena itu database hanya dipakai sebagai
     * cadangan ketika call site ternyata tidak mengirim field yang diwajibkan,
     * sehingga kontrak tidak pernah bisa terlewat diam-diam.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function eventSpecificFields(?string $resourceType, array $data): array
    {
        return match ($resourceType) {
            'PAYMENT' => $this->paymentFields($data),
            'ORDER' => $this->orderFields($data),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function paymentFields(array $data): array
    {
        if (array_key_exists('payment_method', $data) && array_key_exists('payment_status', $data)) {
            return [];
        }

        $payment = Payment::query()->whereKey($data['payment_id'] ?? 0)->first();

        return [
            'payment_method' => $payment?->payment_method,
            'payment_status' => $payment?->payment_status,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function orderFields(array $data): array
    {
        if (array_key_exists('order_status', $data)) {
            return [];
        }

        return [
            'order_status' => Order::query()->whereKey($data['order_id'] ?? 0)->value('order_status'),
        ];
    }

    /**
     * Route dihitung dari template yang disalin dari spesifikasi, bukan dari
     * tabel rute Laravel, supaya bentuk yang dikirim ke Android persis seperti
     * tertulis pada spec 10 dan tidak ikut berubah bila ada route canonical baru.
     *
     * @param  array{resource_type: string, resource_id: string, routes: array<string, string>}|null  $contract
     */
    private function targetRoute(?array $contract, ?string $role, mixed $orderId): ?string
    {
        if ($contract === null || $orderId === null || $role === null) {
            return null;
        }

        $template = $contract['routes'][$role] ?? null;

        return is_string($template) ? str_replace('{order_id}', (string) $orderId, $template) : null;
    }

    private function recipientRole(int $userId): ?string
    {
        $name = User::query()
            ->whereKey($userId)
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->value('roles.name');

        return is_string($name) ? $name : null;
    }
}
