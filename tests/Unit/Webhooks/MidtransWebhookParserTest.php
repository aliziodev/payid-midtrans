<?php

use Aliziodev\PayId\Enums\PaymentChannel;
use Aliziodev\PayId\Enums\PaymentStatus;
use Aliziodev\PayId\Exceptions\WebhookParsingException;
use Aliziodev\PayIdMidtrans\Mappers\StatusResponseMapper;
use Aliziodev\PayIdMidtrans\Mappers\WebhookMapper;
use Aliziodev\PayIdMidtrans\Webhooks\MidtransWebhookParser;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Request;

describe('MidtransWebhookParser', function () {

    beforeEach(function () {
        $this->parser = new MidtransWebhookParser(
            new WebhookMapper(new StatusResponseMapper),
        );
    });

    it('parses settlement (paid) webhook fixture', function () {
        $content = $this->fixtureContent('webhook-paid');
        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);

        $webhook = $this->parser->parse($request, true);

        expect($webhook->provider)->toBe('midtrans');
        expect($webhook->merchantOrderId)->toBe('ORDER-001');
        expect($webhook->status)->toBe(PaymentStatus::Paid);
        expect($webhook->signatureValid)->toBeTrue();
        expect($webhook->channel)->toBe(PaymentChannel::Qris);
        expect($webhook->amount)->toBe(150000);
        expect($webhook->currency)->toBe('IDR');
        expect($webhook->rawPayload)->toBeArray();
    });

    it('parses pending VA webhook fixture', function () {
        $content = $this->fixtureContent('webhook-pending');
        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);

        $webhook = $this->parser->parse($request, true);

        expect($webhook->status)->toBe(PaymentStatus::Pending);
        expect($webhook->channel)->toBe(PaymentChannel::VaBca);
        expect($webhook->merchantOrderId)->toBe('ORDER-002');
    });

    it('parses expire webhook fixture', function () {
        $content = $this->fixtureContent('webhook-expire');
        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);

        $webhook = $this->parser->parse($request, true);

        expect($webhook->status)->toBe(PaymentStatus::Expired);
        expect($webhook->channel)->toBe(PaymentChannel::Gopay);
        expect($webhook->merchantOrderId)->toBe('ORDER-003');
    });

    it('parses cancel webhook fixture', function () {
        $content = $this->fixtureContent('webhook-cancel');
        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);

        $webhook = $this->parser->parse($request, true);

        expect($webhook->status)->toBe(PaymentStatus::Cancelled);
        expect($webhook->channel)->toBe(PaymentChannel::VaBni);
        expect($webhook->merchantOrderId)->toBe('ORDER-004');
    });

    it('preserves signatureValid flag as-is', function () {
        $content = $this->fixtureContent('webhook-paid');
        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);

        $webhookInvalid = $this->parser->parse($request, false);
        expect($webhookInvalid->signatureValid)->toBeFalse();

        $webhookValid = $this->parser->parse($request, true);
        expect($webhookValid->signatureValid)->toBeTrue();
    });

    it('throws WebhookParsingException for empty body', function () {
        $request = Request::create('/webhook', 'POST');

        expect(fn () => $this->parser->parse($request, true))
            ->toThrow(WebhookParsingException::class);
    });

    it('throws WebhookParsingException for invalid JSON', function () {
        $request = Request::create('/webhook', 'POST', [], [], [], [], 'not-json');

        expect(fn () => $this->parser->parse($request, true))
            ->toThrow(WebhookParsingException::class);
    });

    it('throws WebhookParsingException for missing required fields', function () {
        $payload = json_encode(['foo' => 'bar']); // missing order_id, transaction_status
        $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);

        expect(fn () => $this->parser->parse($request, true))
            ->toThrow(WebhookParsingException::class);
    });

    it('rejects duplicate webhook payload when replay protection is enabled', function () {
        $cache = new Repository(new ArrayStore);
        $parser = new MidtransWebhookParser(
            mapper: new WebhookMapper(new StatusResponseMapper),
            cache: $cache,
            replayProtectionEnabled: true,
            replayTtlSeconds: 3600,
        );

        $content = $this->fixtureContent('webhook-paid');
        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);

        $first = $parser->parse($request, true);
        expect($first->merchantOrderId)->toBe('ORDER-001');

        expect(fn () => $parser->parse($request, true))
            ->toThrow(WebhookParsingException::class, 'Duplicate webhook detected (replay).');
    });

    it('allows same order with different status events', function () {
        $cache = new Repository(new ArrayStore);
        $parser = new MidtransWebhookParser(
            mapper: new WebhookMapper(new StatusResponseMapper),
            cache: $cache,
            replayProtectionEnabled: true,
            replayTtlSeconds: 3600,
        );

        $pendingPayload = [
            'order_id' => 'ORDER-OUT-001',
            'transaction_id' => 'TRX-OUT-001',
            'transaction_status' => 'pending',
            'gross_amount' => '150000.00',
            'payment_type' => 'qris',
            'transaction_time' => now()->toDateTimeString(),
        ];

        $settlementPayload = [
            'order_id' => 'ORDER-OUT-001',
            'transaction_id' => 'TRX-OUT-001',
            'transaction_status' => 'settlement',
            'gross_amount' => '150000.00',
            'payment_type' => 'qris',
            'transaction_time' => now()->toDateTimeString(),
        ];

        $pendingRequest = Request::create('/webhook', 'POST', [], [], [], [], json_encode($pendingPayload));
        $settlementRequest = Request::create('/webhook', 'POST', [], [], [], [], json_encode($settlementPayload));

        $pending = $parser->parse($pendingRequest, true);
        $settlement = $parser->parse($settlementRequest, true);

        expect($pending->status)->toBe(PaymentStatus::Pending);
        expect($settlement->status)->toBe(PaymentStatus::Paid);
    });

    it('allows duplicate payload when replay protection is disabled', function () {
        $cache = new Repository(new ArrayStore);
        $parser = new MidtransWebhookParser(
            mapper: new WebhookMapper(new StatusResponseMapper),
            cache: $cache,
            replayProtectionEnabled: false,
            replayTtlSeconds: 3600,
        );

        $content = $this->fixtureContent('webhook-paid');
        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);

        $first = $parser->parse($request, true);
        $second = $parser->parse($request, true);

        expect($first->merchantOrderId)->toBe('ORDER-001');
        expect($second->merchantOrderId)->toBe('ORDER-001');
    });

});
