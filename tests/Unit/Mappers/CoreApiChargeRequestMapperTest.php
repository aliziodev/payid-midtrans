<?php

use Aliziodev\PayId\DTO\ChargeRequest;
use Aliziodev\PayId\Enums\PaymentChannel;
use Aliziodev\PayId\Exceptions\PayloadMappingException;
use Aliziodev\PayIdMidtrans\Mappers\CoreApiChargeRequestMapper;

describe('CoreApiChargeRequestMapper', function () {

    beforeEach(function () {
        $this->mapper = new CoreApiChargeRequestMapper;
    });

    it('omits empty callback_url for GoPay and ShopeePay', function () {
        $gopayPayload = $this->mapper->toCorePayload(ChargeRequest::make([
            'merchant_order_id' => 'ORDER-GP-NULL-CB',
            'amount' => 100000,
            'channel' => PaymentChannel::Gopay,
            'customer' => ['name' => 'A', 'email' => 'a@example.com'],
        ]));

        expect($gopayPayload['gopay'])->toHaveKey('enable_callback', true);
        expect($gopayPayload['gopay'])->not->toHaveKey('callback_url');

        $shopeePayload = $this->mapper->toCorePayload(ChargeRequest::make([
            'merchant_order_id' => 'ORDER-SP-NULL-CB',
            'amount' => 100000,
            'channel' => PaymentChannel::Shopeepay,
            'customer' => ['name' => 'B', 'email' => 'b@example.com'],
        ]));

        expect($shopeePayload['shopeepay'])->not->toHaveKey('callback_url');
    });

    it('requires token_id for credit card channel', function () {
        expect(fn () => $this->mapper->toCorePayload(ChargeRequest::make([
            'merchant_order_id' => 'ORDER-CC-NO-TOKEN',
            'amount' => 200000,
            'channel' => PaymentChannel::CreditCard,
            'customer' => ['name' => 'C', 'email' => 'c@example.com'],
        ])))->toThrow(PayloadMappingException::class);
    });

    it('maps credit card token payload from metadata', function () {
        $payload = $this->mapper->toCorePayload(ChargeRequest::make([
            'merchant_order_id' => 'ORDER-CC-001',
            'amount' => 250000,
            'channel' => PaymentChannel::CreditCard,
            'customer' => ['name' => 'D', 'email' => 'd@example.com'],
            'metadata' => [
                'token_id' => '481111-xxxx-xxxx',
                'authentication' => true,
                'save_card' => true,
            ],
        ]));

        expect($payload['payment_type'])->toBe('credit_card');
        expect($payload['credit_card']['token_id'])->toBe('481111-xxxx-xxxx');
        expect($payload['credit_card']['authentication'])->toBeTrue();
        expect($payload['credit_card']['save_token_id'])->toBeTrue();
    });
});
