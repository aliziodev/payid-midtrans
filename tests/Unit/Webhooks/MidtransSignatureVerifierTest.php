<?php

use Aliziodev\PayIdMidtrans\MidtransConfig;
use Aliziodev\PayIdMidtrans\Webhooks\MidtransSignatureVerifier;
use Illuminate\Http\Request;

describe('MidtransSignatureVerifier', function () {

    beforeEach(function () {
        $this->serverKey = 'SB-Mid-server-test-key-1234567890';
        $this->config = new MidtransConfig([
            'server_key' => $this->serverKey,
            'environment' => 'sandbox',
        ]);
        $this->verifier = new MidtransSignatureVerifier($this->config);
    });

    it('returns true for valid signature', function () {
        $orderId = 'ORDER-001';
        $statusCode = '200';
        $grossAmount = '150000.00';

        $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.$this->serverKey);

        $payload = json_encode([
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signatureKey,
            'transaction_status' => 'settlement',
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);

        expect($this->verifier->verify($request))->toBeTrue();
    });

    it('returns false for invalid signature', function () {
        $payload = json_encode([
            'order_id' => 'ORDER-001',
            'status_code' => '200',
            'gross_amount' => '150000.00',
            'signature_key' => 'wrong-signature-value',
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);

        expect($this->verifier->verify($request))->toBeFalse();
    });

    it('returns false for empty body', function () {
        $request = Request::create('/webhook', 'POST');

        expect($this->verifier->verify($request))->toBeFalse();
    });

    it('returns false for invalid JSON', function () {
        $request = Request::create('/webhook', 'POST', [], [], [], [], 'not-json');

        expect($this->verifier->verify($request))->toBeFalse();
    });

    it('returns false for payload missing required fields', function () {
        $payload = json_encode([
            'order_id' => 'ORDER-001',
            // missing status_code, gross_amount, signature_key
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);

        expect($this->verifier->verify($request))->toBeFalse();
    });

    it('verifyPayload works directly with array', function () {
        $orderId = 'ORDER-DIRECT';
        $statusCode = '201';
        $grossAmount = '50000.00';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$this->serverKey);

        $result = $this->verifier->verifyPayload([
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
        ]);

        expect($result)->toBeTrue();
    });

});
