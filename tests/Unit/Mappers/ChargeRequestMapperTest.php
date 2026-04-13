<?php

use Aliziodev\PayId\DTO\ChargeRequest;
use Aliziodev\PayId\Enums\PaymentChannel;
use Aliziodev\PayId\Exceptions\PayloadMappingException;
use Aliziodev\PayIdMidtrans\Mappers\ChargeRequestMapper;

describe('ChargeRequestMapper', function () {

    beforeEach(function () {
        $this->mapper = new ChargeRequestMapper;
    });

    it('maps minimal ChargeRequest to Snap payload', function () {
        $request = ChargeRequest::make([
            'merchant_order_id' => 'ORDER-001',
            'amount' => 150000,
            'channel' => PaymentChannel::Qris,
            'customer' => ['name' => 'Budi Santoso', 'email' => 'budi@example.com'],
        ]);

        $payload = $this->mapper->toSnapPayload($request);

        expect($payload['transaction_details']['order_id'])->toBe('ORDER-001');
        expect($payload['transaction_details']['gross_amount'])->toBe(150000);
        expect($payload['customer_details']['first_name'])->toBe('Budi Santoso');
        expect($payload['customer_details']['email'])->toBe('budi@example.com');
        expect($payload['enabled_payments'])->toBe(['qris']);
    });

    it('maps item details correctly', function () {
        $request = ChargeRequest::make([
            'merchant_order_id' => 'ORDER-002',
            'amount' => 60000,
            'channel' => PaymentChannel::Gopay,
            'customer' => ['name' => 'Ani', 'email' => 'ani@example.com'],
            'items' => [
                ['id' => 'P-001', 'name' => 'Kopi', 'price' => 20000, 'quantity' => 3],
            ],
        ]);

        $payload = $this->mapper->toSnapPayload($request);

        expect($payload['item_details'])->toHaveCount(1);
        expect($payload['item_details'][0]['id'])->toBe('P-001');
        expect($payload['item_details'][0]['name'])->toBe('Kopi');
        expect($payload['item_details'][0]['price'])->toBe(20000);
        expect($payload['item_details'][0]['quantity'])->toBe(3);
    });

    it('maps callback and success URL', function () {
        $request = ChargeRequest::make([
            'merchant_order_id' => 'ORDER-003',
            'amount' => 100000,
            'channel' => PaymentChannel::VaBca,
            'customer' => ['name' => 'Rudi', 'email' => 'rudi@example.com'],
            'callback_url' => 'https://example.com/webhook',
            'success_url' => 'https://example.com/success',
        ]);

        $payload = $this->mapper->toSnapPayload($request);

        expect($payload['callbacks']['notification'])->toBe('https://example.com/webhook');
        expect($payload['callbacks']['finish'])->toBe('https://example.com/success');
    });

    it('includes customer phone when provided', function () {
        $request = ChargeRequest::make([
            'merchant_order_id' => 'ORDER-004',
            'amount' => 50000,
            'channel' => PaymentChannel::Shopeepay,
            'customer' => ['name' => 'Siti', 'email' => 'siti@example.com', 'phone' => '08123456789'],
        ]);

        $payload = $this->mapper->toSnapPayload($request);

        expect($payload['customer_details']['phone'])->toBe('08123456789');
    });

    it('maps all supported channels correctly', function () {
        $cases = [
            [PaymentChannel::VaBca, 'bca_va'],
            [PaymentChannel::VaBni, 'bni_va'],
            [PaymentChannel::VaBri, 'bri_va'],
            [PaymentChannel::VaMandiri, 'mandiri_va'],
            [PaymentChannel::VaPermata, 'permata_va'],
            [PaymentChannel::Qris, 'qris'],
            [PaymentChannel::Gopay, 'gopay'],
            [PaymentChannel::Shopeepay, 'shopeepay'],
            [PaymentChannel::CreditCard, 'credit_card'],
            [PaymentChannel::CstoreAlfamart, 'alfamart'],
            [PaymentChannel::CstoreIndomaret, 'indomaret'],
        ];

        foreach ($cases as [$channel, $expected]) {
            expect($this->mapper->mapChannel($channel))->toBe($expected);
        }
    });

    it('throws PayloadMappingException for unsupported channel', function () {
        expect(fn () => $this->mapper->mapChannel(PaymentChannel::Invoice))
            ->toThrow(PayloadMappingException::class);
    });

    it('includes metadata as custom_field1', function () {
        $request = ChargeRequest::make([
            'merchant_order_id' => 'ORDER-005',
            'amount' => 100000,
            'channel' => PaymentChannel::Qris,
            'customer' => ['name' => 'X', 'email' => 'x@x.com'],
            'metadata' => ['source' => 'web', 'user_id' => 42],
        ]);

        $payload = $this->mapper->toSnapPayload($request);

        expect($payload['custom_field1'])->toBe(json_encode(['source' => 'web', 'user_id' => 42]));
    });

});
