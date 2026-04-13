<?php

use Aliziodev\PayId\Enums\PaymentChannel;
use Aliziodev\PayId\Enums\PaymentStatus;
use Aliziodev\PayIdMidtrans\Mappers\StatusResponseMapper;

describe('StatusResponseMapper', function () {

    beforeEach(function () {
        $this->mapper = new StatusResponseMapper;
    });

    it('maps settlement status to PaymentStatus::Paid', function () {
        $raw = $this->fixture('status-paid');

        $response = $this->mapper->toStatusResponse($raw);

        expect($response->status)->toBe(PaymentStatus::Paid);
        expect($response->providerName)->toBe('midtrans');
        expect($response->merchantOrderId)->toBe('ORDER-001');
        expect($response->amount)->toBe(150000);
        expect($response->channel)->toBe(PaymentChannel::Qris);
        expect($response->paidAt)->not->toBeNull();
    });

    it('maps pending VA status correctly', function () {
        $raw = $this->fixture('status-va-pending');

        $response = $this->mapper->toStatusResponse($raw);

        expect($response->status)->toBe(PaymentStatus::Pending);
        expect($response->channel)->toBe(PaymentChannel::VaBca);
        expect($response->paidAt)->toBeNull();
    });

    it('maps all transaction_status values correctly', function () {
        $cases = [
            ['transaction_status' => 'settlement',    'expected' => PaymentStatus::Paid],
            ['transaction_status' => 'capture',       'expected' => PaymentStatus::Authorized],
            ['transaction_status' => 'authorize',     'expected' => PaymentStatus::Authorized],
            ['transaction_status' => 'pending',       'expected' => PaymentStatus::Pending],
            ['transaction_status' => 'challenge',     'expected' => PaymentStatus::Pending],
            ['transaction_status' => 'deny',          'expected' => PaymentStatus::Failed],
            ['transaction_status' => 'cancel',        'expected' => PaymentStatus::Cancelled],
            ['transaction_status' => 'expire',        'expected' => PaymentStatus::Expired],
            ['transaction_status' => 'refund',        'expected' => PaymentStatus::Refunded],
            ['transaction_status' => 'partial_refund', 'expected' => PaymentStatus::PartiallyRefunded],
        ];

        foreach ($cases as $case) {
            $raw = [
                'transaction_id' => 'TRX-001',
                'order_id' => 'ORDER-001',
                'transaction_status' => $case['transaction_status'],
                'gross_amount' => '100000.00',
                'payment_type' => 'qris',
            ];

            $response = $this->mapper->toStatusResponse($raw);
            expect($response->status)->toBe($case['expected'], "Failed for status: {$case['transaction_status']}");
        }
    });

    it('maps bank_transfer with different banks', function () {
        $banks = [
            'bca' => PaymentChannel::VaBca,
            'bni' => PaymentChannel::VaBni,
            'bri' => PaymentChannel::VaBri,
            'cimb' => PaymentChannel::VaCimb,
        ];

        foreach ($banks as $bank => $expectedChannel) {
            $raw = [
                'transaction_id' => 'TRX-001',
                'order_id' => 'ORDER-001',
                'transaction_status' => 'pending',
                'gross_amount' => '100000.00',
                'payment_type' => 'bank_transfer',
                'va_numbers' => [['bank' => $bank, 'va_number' => '12345']],
            ];

            $response = $this->mapper->toStatusResponse($raw);
            expect($response->channel)->toBe($expectedChannel, "Failed for bank: $bank");
        }
    });

    it('maps convenience store channels', function () {
        $stores = [
            'alfamart' => PaymentChannel::CstoreAlfamart,
            'indomaret' => PaymentChannel::CstoreIndomaret,
        ];

        foreach ($stores as $store => $expectedChannel) {
            $raw = [
                'transaction_id' => 'TRX-001',
                'order_id' => 'ORDER-001',
                'transaction_status' => 'pending',
                'gross_amount' => '100000.00',
                'payment_type' => 'cstore',
                'store' => $store,
            ];

            $response = $this->mapper->toStatusResponse($raw);
            expect($response->channel)->toBe($expectedChannel);
        }
    });

    it('maps echannel to VaMandiri', function () {
        $raw = [
            'transaction_id' => 'TRX-001',
            'order_id' => 'ORDER-001',
            'transaction_status' => 'pending',
            'gross_amount' => '100000.00',
            'payment_type' => 'echannel',
        ];

        $response = $this->mapper->toStatusResponse($raw);
        expect($response->channel)->toBe(PaymentChannel::VaMandiri);
    });

    it('converts gross_amount string to int', function () {
        $raw = [
            'transaction_id' => 'TRX-001',
            'order_id' => 'ORDER-001',
            'transaction_status' => 'settlement',
            'gross_amount' => '75000.00',
            'payment_type' => 'gopay',
        ];

        $response = $this->mapper->toStatusResponse($raw);
        expect($response->amount)->toBe(75000);
    });

});
