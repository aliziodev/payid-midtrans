<?php

use Aliziodev\PayId\Events\WebhookParsingFailed;
use Aliziodev\PayId\Events\WebhookReceived;
use Illuminate\Support\Facades\Event;

describe('Midtrans webhook endpoint replay protection', function () {

    it('dispatches webhook side effect once and rejects duplicate delivery', function () {
        Event::fake([WebhookReceived::class, WebhookParsingFailed::class]);

        $orderId = 'ORDER-ENDPOINT-REPLAY-001';
        $statusCode = '200';
        $grossAmount = '150000.00';
        $serverKey = 'SB-Mid-server-test-key-1234567890';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        $payload = [
            'order_id' => $orderId,
            'transaction_id' => 'TRX-ENDPOINT-REPLAY-001',
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'transaction_time' => now()->toDateTimeString(),
        ];

        $first = $this->postJson('/payid/webhook/midtrans', $payload);
        $second = $this->postJson('/payid/webhook/midtrans', $payload);

        $first->assertOk();
        $second->assertStatus(422);
        $second->assertSeeText('Webhook payload parsing failed.');

        Event::assertDispatchedTimes(WebhookReceived::class, 1);
        Event::assertDispatchedTimes(WebhookParsingFailed::class, 1);
    });

});
