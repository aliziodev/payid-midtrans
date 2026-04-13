<?php

use Aliziodev\PayId\Enums\Capability;
use Aliziodev\PayId\Enums\PaymentStatus;
use Aliziodev\PayId\Managers\PayIdManager;
use Illuminate\Support\Facades\Http;

describe('MidtransDriver approve/deny', function () {

    it('declares Approve and Deny capabilities', function () {
        $driver = app(PayIdManager::class)->getDriver();

        expect($driver->supports(Capability::Approve))->toBeTrue();
        expect($driver->supports(Capability::Deny))->toBeTrue();
    });

    it('approve calls Core API and returns StatusResponse with Authorized status', function () {
        Http::fake([
            '*/v2/ORDER-CC-001/approve' => Http::response($this->fixture('status-cc-approved'), 200),
        ]);

        $manager = app(PayIdManager::class);
        $response = $manager->approve('ORDER-CC-001');

        expect($response->providerName)->toBe('midtrans');
        expect($response->merchantOrderId)->toBe('ORDER-CC-001');
        expect($response->status)->toBe(PaymentStatus::Authorized);
    });

    it('approve sends POST to correct endpoint', function () {
        Http::fake([
            '*/v2/ORDER-CC-001/approve' => Http::response($this->fixture('status-cc-approved'), 200),
        ]);

        app(PayIdManager::class)->approve('ORDER-CC-001');

        Http::assertSent(fn ($req) => str_contains($req->url(), 'v2/ORDER-CC-001/approve')
            && $req->method() === 'POST');
    });

    it('deny calls Core API and returns StatusResponse with Failed status', function () {
        Http::fake([
            '*/v2/ORDER-CC-001/deny' => Http::response($this->fixture('status-cc-denied'), 200),
        ]);

        $manager = app(PayIdManager::class);
        $response = $manager->deny('ORDER-CC-001');

        expect($response->providerName)->toBe('midtrans');
        expect($response->merchantOrderId)->toBe('ORDER-CC-001');
        expect($response->status)->toBe(PaymentStatus::Failed);
    });

    it('deny sends POST to correct endpoint', function () {
        Http::fake([
            '*/v2/ORDER-CC-001/deny' => Http::response($this->fixture('status-cc-denied'), 200),
        ]);

        app(PayIdManager::class)->deny('ORDER-CC-001');

        Http::assertSent(fn ($req) => str_contains($req->url(), 'v2/ORDER-CC-001/deny')
            && $req->method() === 'POST');
    });

});
