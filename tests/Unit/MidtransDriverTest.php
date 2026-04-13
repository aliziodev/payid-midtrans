<?php

use Aliziodev\PayId\DTO\ChargeRequest;
use Aliziodev\PayId\DTO\RefundRequest;
use Aliziodev\PayId\Enums\Capability;
use Aliziodev\PayId\Enums\PaymentChannel;
use Aliziodev\PayId\Enums\PaymentStatus;
use Aliziodev\PayId\Exceptions\ProviderApiException;
use Aliziodev\PayId\Exceptions\WebhookParsingException;
use Aliziodev\PayId\Managers\PayIdManager;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

describe('MidtransDriver', function () {

    it('has correct driver name', function () {
        $driver = app(PayIdManager::class)->getDriver();
        expect($driver->getName())->toBe('midtrans');
    });

    it('declares correct capabilities', function () {
        $driver = app(PayIdManager::class)->getDriver();

        expect($driver->supports(Capability::Charge))->toBeTrue();
        expect($driver->supports(Capability::Status))->toBeTrue();
        expect($driver->supports(Capability::Cancel))->toBeTrue();
        expect($driver->supports(Capability::Expire))->toBeTrue();
        expect($driver->supports(Capability::WebhookVerification))->toBeTrue();
        expect($driver->supports(Capability::WebhookParsing))->toBeTrue();
        expect($driver->supports(Capability::Refund))->toBeTrue();
    });

    it('charge calls Snap API and returns ChargeResponse', function () {
        Http::fake([
            '*/snap/v1/transactions' => Http::response(
                $this->fixture('snap-response'),
                200,
            ),
        ]);

        $manager = app(PayIdManager::class);

        $response = $manager->charge(ChargeRequest::make([
            'merchant_order_id' => 'ORDER-001',
            'amount' => 150000,
            'channel' => PaymentChannel::Qris,
            'customer' => ['name' => 'Budi', 'email' => 'budi@example.com'],
        ]));

        expect($response->providerName)->toBe('midtrans');
        expect($response->merchantOrderId)->toBe('ORDER-001');
        expect($response->status)->toBe(PaymentStatus::Pending);
        expect($response->paymentUrl)->toContain('midtrans.com');
        expect($response->rawResponse)->toHaveKey('token');
    });

    it('charge sends correct authorization header', function () {
        Http::fake([
            '*/snap/v1/transactions' => Http::response($this->fixture('snap-response'), 200),
        ]);

        $manager = app(PayIdManager::class);

        $manager->charge(ChargeRequest::make([
            'merchant_order_id' => 'ORDER-002',
            'amount' => 100000,
            'channel' => PaymentChannel::Gopay,
            'customer' => ['name' => 'Ani', 'email' => 'ani@example.com'],
        ]));

        Http::assertSent(function (HttpRequest $request) {
            return str_contains($request->url(), 'snap/v1/transactions')
                && str_starts_with($request->header('Authorization')[0], 'Basic ');
        });
    });

    it('status calls Core API and returns StatusResponse', function () {
        Http::fake([
            '*/v2/ORDER-001/status' => Http::response($this->fixture('status-paid'), 200),
        ]);

        $manager = app(PayIdManager::class);
        $response = $manager->status('ORDER-001');

        expect($response->providerName)->toBe('midtrans');
        expect($response->merchantOrderId)->toBe('ORDER-001');
        expect($response->status)->toBe(PaymentStatus::Paid);
        expect($response->channel)->toBe(PaymentChannel::Qris);
        expect($response->amount)->toBe(150000);
    });

    it('throws ProviderApiException on non-2xx response', function () {
        Http::fake([
            '*/snap/v1/transactions' => Http::response(['error_messages' => ['Invalid param']], 400),
        ]);

        $manager = app(PayIdManager::class);

        expect(fn () => $manager->charge(ChargeRequest::make([
            'merchant_order_id' => 'ORDER-ERR',
            'amount' => 100000,
            'channel' => PaymentChannel::Qris,
            'customer' => ['name' => 'X', 'email' => 'x@x.com'],
        ])))->toThrow(ProviderApiException::class);
    });

    it('refund calls Core API and returns RefundResponse', function () {
        Http::fake([
            '*/v2/ORDER-001/refund' => Http::response($this->fixture('refund-response'), 200),
        ]);

        $manager = app(PayIdManager::class);
        $response = $manager->refund(RefundRequest::make([
            'merchant_order_id' => 'ORDER-001',
            'amount' => 150000,
            'reason' => 'Customer request',
            'refund_key' => 'RFD-ORDER-001-001',
        ]));

        expect($response->providerName)->toBe('midtrans');
        expect($response->merchantOrderId)->toBe('ORDER-001');
        expect($response->status)->toBe(PaymentStatus::Refunded);
        expect($response->amount)->toBe(150000);
        expect($response->refundId)->toBe('RFD-ORDER-001-001');
    });

    it('refund sends correct payload to Core API', function () {
        Http::fake([
            '*/v2/ORDER-001/refund' => Http::response($this->fixture('refund-response'), 200),
        ]);

        $manager = app(PayIdManager::class);

        $manager->refund(RefundRequest::make([
            'merchant_order_id' => 'ORDER-001',
            'amount' => 150000,
            'reason' => 'Damaged item',
        ]));

        Http::assertSent(function (HttpRequest $request) {
            $body = $request->data();

            return str_contains($request->url(), 'v2/ORDER-001/refund')
                && $body['refund_amount'] === 150000
                && $body['reason'] === 'Damaged item';
        });
    });

    it('verifyWebhook returns true for valid signature', function () {
        $serverKey = 'SB-Mid-server-test-key-1234567890';
        $orderId = 'ORDER-001';
        $statusCode = '200';
        $grossAmount = '150000.00';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        $payload = json_encode([
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);

        $driver = app(PayIdManager::class)->getDriver();
        expect($driver->verifyWebhook($request))->toBeTrue();
    });

    it('parseWebhook returns NormalizedWebhook from valid payload', function () {
        $serverKey = 'SB-Mid-server-test-key-1234567890';
        $orderId = 'ORDER-001';
        $statusCode = '200';
        $grossAmount = '150000.00';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        $payload = json_encode([
            'order_id' => $orderId,
            'transaction_id' => 'TRX-001',
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);

        $driver = app(PayIdManager::class)->getDriver();
        $webhook = $driver->parseWebhook($request);

        expect($webhook->merchantOrderId)->toBe('ORDER-001');
        expect($webhook->status)->toBe(PaymentStatus::Paid);
        expect($webhook->signatureValid)->toBeTrue();
        expect($webhook->channel)->toBe(PaymentChannel::Qris);
    });

    it('parseWebhook rejects duplicate event by default', function () {
        $serverKey = 'SB-Mid-server-test-key-1234567890';
        $orderId = 'ORDER-REPLAY-001';
        $statusCode = '200';
        $grossAmount = '150000.00';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        $payload = json_encode([
            'order_id' => $orderId,
            'transaction_id' => 'TRX-REPLAY-001',
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);

        $driver = app(PayIdManager::class)->getDriver();
        $first = $driver->parseWebhook($request);

        expect($first->merchantOrderId)->toBe($orderId);
        expect(fn () => $driver->parseWebhook($request))
            ->toThrow(WebhookParsingException::class, 'Duplicate webhook detected (replay).');
    });

});
