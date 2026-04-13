# PayID Midtrans Driver

Driver Midtrans untuk `aliziodev/payid`.

Package ini menyediakan integrasi Midtrans dengan API PayID yang konsisten untuk flow:

- Snap charge
- Core API direct charge
- status / cancel / expire / refund / approve / deny
- subscription lifecycle
- webhook verification + parsing
- GoPay account linking

## Requirements

- PHP 8.2+
- Laravel 11 / 12 / 13
- `aliziodev/payid` ^0.1

## Instalasi

```bash
composer require aliziodev/payid
composer require aliziodev/payid-midtrans
```

## Konfigurasi

Tambahkan kredensial Midtrans pada konfigurasi driver `payid`.

Contoh di `config/payid.php`:

```php
'drivers' => [
    'midtrans' => [
        'driver' => 'midtrans',
        'environment' => env('MIDTRANS_ENV', 'sandbox'),
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),

        'timeout' => 30,
        'retry_times' => 1,

        'endpoints' => [
            // Optional override
            'snap_base_url' => env('MIDTRANS_SNAP_BASE_URL'),
            'core_base_url' => env('MIDTRANS_CORE_BASE_URL'),
            'subscription_base_url' => env('MIDTRANS_SUBSCRIPTION_BASE_URL'),
        ],

        'webhook' => [
            'replay_protection' => true,
            'replay_ttl_seconds' => 3600,
            'cache_store' => null,
        ],
    ],
],
```

Contoh `.env`:

```env
PAYID_DEFAULT_DRIVER=midtrans

MIDTRANS_ENV=sandbox
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxx
MIDTRANS_MERCHANT_ID=Gxxxxxxxx
```

## Endpoint Mapping

- Snap API
  - `POST /snap/v1/transactions`
- Core API v2
  - `POST /v2/charge`
  - `GET /v2/{id}/status`
  - `POST /v2/{id}/cancel`
  - `POST /v2/{id}/expire`
  - `POST /v2/{id}/refund`
  - `POST /v2/{id}/approve`
  - `POST /v2/{id}/deny`
  - `POST /v2/pay/account`
  - `GET /v2/pay/account/{account_id}`
  - `POST /v2/pay/account/{account_id}/unbind`
- Subscription API v1
  - `POST /v1/subscriptions`
  - `GET /v1/subscriptions/{subscription_id}`
  - `PATCH /v1/subscriptions/{subscription_id}`
  - `POST /v1/subscriptions/{subscription_id}/disable`
  - `POST /v1/subscriptions/{subscription_id}/enable`
  - `POST /v1/subscriptions/{subscription_id}/cancel`

## Penggunaan

### Charge (Snap)

```php
use Aliziodev\PayId\DTO\ChargeRequest;
use Aliziodev\PayId\Enums\PaymentChannel;
use Illuminate\Support\Facades\PayId;

$response = PayId::charge(ChargeRequest::make([
    'merchant_order_id' => 'ORDER-1001',
    'amount' => 150000,
    'currency' => 'IDR',
    'channel' => PaymentChannel::Qris,
]));
```

### Direct Charge (Core API)

```php
$response = PayId::directCharge(ChargeRequest::make([
    'merchant_order_id' => 'ORDER-1002',
    'amount' => 200000,
    'currency' => 'IDR',
    'channel' => PaymentChannel::VaBca,
]));
```

### Status / Refund

```php
$status = PayId::status('ORDER-1001');

$refund = PayId::refund(\Aliziodev\PayId\DTO\RefundRequest::make([
    'merchant_order_id' => 'ORDER-1001',
    'amount' => 50000,
    'reason' => 'Customer request',
]));
```

### Subscription

```php
$created = PayId::createSubscription(\Aliziodev\PayId\DTO\SubscriptionRequest::make([
    'subscription_id' => 'SUB-1001',
    'name' => 'MONTHLY_PLAN',
    'amount' => 99000,
    'token' => 'saved_token_id_or_gopay_token',
    'interval' => 'month',
    'interval_count' => 1,
]));

$paused = PayId::pauseSubscription($created->providerSubscriptionId);
$resumed = PayId::resumeSubscription($created->providerSubscriptionId);
$canceled = PayId::cancelSubscription($created->providerSubscriptionId);
```

## Webhook

- Signature diverifikasi dengan formula resmi Midtrans:
  - `SHA512(order_id + status_code + gross_amount + server_key)`
- Replay protection tersedia via cache fingerprint.

Dokumen operasional:

- `docs/webhook-replay-protection.md`
- `docs/webhook-incident-runbook.md`

## Kesiapan Saat Ini

Audit sinkronisasi API Midtrans terbaru sudah dilakukan per 2026-04-13.

Lihat detail:

- `docs/api-compatibility-audit-2026-04-13.md`
- `docs/release-readiness-checklist.md`

## Quality Gates

```bash
composer test
composer analyse
composer lint-check
```

## Lisensi

MIT
