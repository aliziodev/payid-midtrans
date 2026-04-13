<?php

use Aliziodev\PayId\Managers\PayIdManager;
use Aliziodev\PayIdMidtrans\DTO\GopayAccountLinkRequest;
use Aliziodev\PayIdMidtrans\MidtransDriver;
use Illuminate\Support\Facades\Http;

describe('GoPay Account Linking', function () {

    it('linkGopayAccount returns GopayAccountDetails with PENDING status', function () {
        Http::fake([
            '*/v2/pay/account' => Http::response($this->fixture('gopay-account-pending'), 201),
        ]);

        /** @var MidtransDriver $driver */
        $driver = app(PayIdManager::class)->getDriver();
        $account = $driver->linkGopayAccount(GopayAccountLinkRequest::make([
            'phone_number' => '+6281234567890',
            'callback_url' => 'https://example.com/gopay/callback',
        ]));

        expect($account->accountId)->toBe('USER-123');
        expect($account->isPending())->toBeTrue();
        expect($account->approvalUrl)->toContain('USER-123/link');
    });

    it('linkGopayAccount sends correct payload', function () {
        Http::fake([
            '*/v2/pay/account' => Http::response($this->fixture('gopay-account-pending'), 201),
        ]);

        $driver = app(PayIdManager::class)->getDriver();
        $driver->linkGopayAccount(GopayAccountLinkRequest::make([
            'phone_number' => '+6281234567890',
            'callback_url' => 'https://example.com/gopay/callback',
        ]));

        Http::assertSent(function ($req) {
            $body = $req->data();

            return str_contains($req->url(), 'v2/pay/account')
                && $body['payment_type'] === 'gopay'
                && $body['gopay_partner']['phone_number'] === '+6281234567890';
        });
    });

    it('getGopayAccount returns GopayAccountDetails with ENABLED status', function () {
        Http::fake([
            '*/v2/pay/account/USER-123' => Http::response($this->fixture('gopay-account-enabled'), 200),
        ]);

        $driver = app(PayIdManager::class)->getDriver();
        $account = $driver->getGopayAccount('USER-123');

        expect($account->accountId)->toBe('USER-123');
        expect($account->phoneNumber)->toBe('USER-123');
        expect($account->isEnabled())->toBeTrue();
        expect($account->approvalUrl)->toBeNull();
    });

    it('unlinkGopayAccount sends POST to unbind endpoint and returns true', function () {
        Http::fake([
            '*/v2/pay/account/USER-123/unbind' => Http::response(['status_code' => '200'], 200),
        ]);

        $driver = app(PayIdManager::class)->getDriver();
        $result = $driver->unlinkGopayAccount('USER-123');

        expect($result)->toBeTrue();
        Http::assertSent(fn ($req) => str_contains($req->url(), 'USER-123/unbind'));
    });

    it('MidtransDriver is accessible via getDriver()', function () {
        $driver = app(PayIdManager::class)->getDriver();
        expect($driver)->toBeInstanceOf(MidtransDriver::class);
    });

});
