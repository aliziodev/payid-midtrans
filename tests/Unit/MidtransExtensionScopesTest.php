<?php

use Aliziodev\PayId\Managers\PayIdManager;
use Aliziodev\PayIdMidtrans\MidtransDriver;
use Illuminate\Support\Facades\Http;

describe('MidtransDriver extension scopes', function () {

    it('supports snap-bi status extension', function () {
        Http::fake([
            '*/v2/ORDER-B2B-001/status/b2b' => Http::response([
                'transaction_status' => 'settlement',
                'order_id' => 'ORDER-B2B-001',
            ], 200),
        ]);

        /** @var MidtransDriver $driver */
        $driver = app(PayIdManager::class)->getDriver();
        $result = $driver->getSnapBiTransactionStatus('ORDER-B2B-001');

        expect($result['transaction_status'])->toBe('settlement')
            ->and($result['order_id'])->toBe('ORDER-B2B-001');
    });

    it('supports payment link create/get/delete extension', function () {
        Http::fake([
            '*/v1/payment-links' => Http::response([
                'order_id' => 'ORDER-LINK-001',
                'payment_link_url' => 'https://pay.midtrans.com/ORDER-LINK-001',
            ], 201),
            '*/v1/payment-links/ORDER-LINK-001' => Http::response([
                'order_id' => 'ORDER-LINK-001',
                'status' => 'active',
            ], 200),
        ]);

        /** @var MidtransDriver $driver */
        $driver = app(PayIdManager::class)->getDriver();

        $created = $driver->createPaymentLink([
            'transaction_details' => [
                'order_id' => 'ORDER-LINK-001',
                'gross_amount' => 150000,
            ],
            'usage_limit' => 1,
        ]);

        $detail = $driver->getPaymentLink('ORDER-LINK-001');
        $deleted = $driver->deletePaymentLink('ORDER-LINK-001');

        expect($created['order_id'])->toBe('ORDER-LINK-001')
            ->and($detail['status'])->toBe('active')
            ->and($deleted)->toBeArray();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/v1/payment-links/ORDER-LINK-001'));
    });

    it('supports balance mutation extension', function () {
        Http::fake([
            '*v1/balance/mutation*' => Http::response([
                'status_code' => '200',
                'balance' => [
                    ['mutation' => 50000],
                ],
            ], 200),
        ]);

        /** @var MidtransDriver $driver */
        $driver = app(PayIdManager::class)->getDriver();
        $result = $driver->getBalanceMutation('IDR', '2026-04-01 00:00:00', '2026-04-14 23:59:59');

        expect($result['status_code'])->toBe('200')
            ->and($result['balance'])->toBeArray();
    });

    it('supports invoicing create/get/void extension', function () {
        Http::fake([
            '*/v1/invoices' => Http::response([
                'id' => 'INV-001',
                'external_id' => 'INVOICE-001',
                'status' => 'pending',
            ], 201),
            '*/v1/invoices/INV-001' => Http::response([
                'id' => 'INV-001',
                'status' => 'pending',
            ], 200),
            '*/v1/invoices/INV-001/void' => Http::response([
                'id' => 'INV-001',
                'status' => 'voided',
            ], 200),
        ]);

        /** @var MidtransDriver $driver */
        $driver = app(PayIdManager::class)->getDriver();

        $created = $driver->createInvoice([
            'external_id' => 'INVOICE-001',
            'payer_email' => 'budi@example.com',
            'description' => 'Invoice test',
            'amount' => 200000,
        ]);

        $detail = $driver->getInvoice('INV-001');
        $voided = $driver->voidInvoice('INV-001');

        expect($created['id'])->toBe('INV-001')
            ->and($detail['status'])->toBe('pending')
            ->and($voided['status'])->toBe('voided');
    });
});
