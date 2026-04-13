<?php

use Aliziodev\PayId\DTO\ChargeRequest;
use Aliziodev\PayId\Enums\Capability;
use Aliziodev\PayId\Enums\PaymentChannel;
use Aliziodev\PayId\Enums\PaymentStatus;
use Aliziodev\PayId\Managers\PayIdManager;
use Illuminate\Support\Facades\Http;

describe('MidtransDriver directCharge (Core API)', function () {

    it('declares DirectCharge capability', function () {
        $driver = app(PayIdManager::class)->getDriver();
        expect($driver->supports(Capability::DirectCharge))->toBeTrue();
    });

    it('directCharge VA BCA returns vaNumber and vaBankCode', function () {
        Http::fake([
            '*/v2/charge' => Http::response($this->fixture('core-charge-va-bca'), 201),
        ]);

        $manager = app(PayIdManager::class);
        $response = $manager->directCharge(ChargeRequest::make([
            'merchant_order_id' => 'ORDER-VA-001',
            'amount' => 200000,
            'channel' => PaymentChannel::VaBca,
            'customer' => ['name' => 'Budi', 'email' => 'budi@example.com'],
        ]));

        expect($response->providerName)->toBe('midtrans');
        expect($response->merchantOrderId)->toBe('ORDER-VA-001');
        expect($response->status)->toBe(PaymentStatus::Pending);
        expect($response->vaNumber)->toBe('12345678901234');
        expect($response->vaBankCode)->toBe('BCA');
        expect($response->paymentUrl)->toBeNull();
        expect($response->expiresAt)->not->toBeNull();
    });

    it('directCharge VA BCA sends bank_transfer payload', function () {
        Http::fake([
            '*/v2/charge' => Http::response($this->fixture('core-charge-va-bca'), 201),
        ]);

        app(PayIdManager::class)->directCharge(ChargeRequest::make([
            'merchant_order_id' => 'ORDER-VA-001',
            'amount' => 200000,
            'channel' => PaymentChannel::VaBca,
            'customer' => ['name' => 'Budi', 'email' => 'budi@example.com'],
        ]));

        Http::assertSent(function ($req) {
            $body = $req->data();

            return str_contains($req->url(), 'v2/charge')
                && $body['payment_type'] === 'bank_transfer'
                && $body['bank_transfer']['bank'] === 'bca';
        });
    });

    it('directCharge QRIS returns qrString', function () {
        Http::fake([
            '*/v2/charge' => Http::response($this->fixture('core-charge-qris'), 201),
        ]);

        $manager = app(PayIdManager::class);
        $response = $manager->directCharge(ChargeRequest::make([
            'merchant_order_id' => 'ORDER-QR-001',
            'amount' => 150000,
            'channel' => PaymentChannel::Qris,
            'customer' => ['name' => 'Ani', 'email' => 'ani@example.com'],
        ]));

        expect($response->qrString)->toStartWith('00020101');
        expect($response->vaNumber)->toBeNull();
    });

    it('directCharge GoPay returns paymentUrl (deeplink)', function () {
        Http::fake([
            '*/v2/charge' => Http::response($this->fixture('core-charge-gopay'), 201),
        ]);

        $manager = app(PayIdManager::class);
        $response = $manager->directCharge(ChargeRequest::make([
            'merchant_order_id' => 'ORDER-GP-001',
            'amount' => 100000,
            'channel' => PaymentChannel::Gopay,
            'customer' => ['name' => 'Cici', 'email' => 'cici@example.com'],
        ]));

        expect($response->paymentUrl)->toStartWith('gojek://');
        expect($response->vaNumber)->toBeNull();
        expect($response->qrString)->toStartWith('https://');
    });

    it('directCharge sends correct payload structure for Mandiri (echannel)', function () {
        Http::fake([
            '*/v2/charge' => Http::response([
                'transaction_id' => 'ec1d2e3f',
                'order_id' => 'ORDER-MD-001',
                'payment_type' => 'echannel',
                'transaction_status' => 'pending',
                'status_code' => '201',
                'gross_amount' => '300000.00',
                'bill_key' => '123456789',
                'biller_code' => '70012',
            ], 201),
        ]);

        app(PayIdManager::class)->directCharge(ChargeRequest::make([
            'merchant_order_id' => 'ORDER-MD-001',
            'amount' => 300000,
            'channel' => PaymentChannel::VaMandiri,
            'customer' => ['name' => 'Dodi', 'email' => 'dodi@example.com'],
        ]));

        Http::assertSent(function ($req) {
            $body = $req->data();

            return $body['payment_type'] === 'echannel'
                && isset($body['echannel']['bill_info1']);
        });
    });

});
