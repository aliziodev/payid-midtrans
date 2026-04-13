<?php

use Aliziodev\PayIdMidtrans\MidtransConfig;

describe('MidtransConfig webhook replay settings', function () {

    it('uses secure replay defaults when webhook config is missing', function () {
        $config = new MidtransConfig([
            'driver' => 'midtrans',
            'environment' => 'sandbox',
            'server_key' => 'SB-Mid-server-test-key-1234567890',
            'client_key' => 'SB-Mid-client-test-key-1234567890',
            'merchant_id' => 'G141532850',
        ]);

        expect($config->webhookReplayProtection)->toBeTrue();
        expect($config->webhookReplayTtlSeconds)->toBe(3600);
        expect($config->webhookReplayCacheStore)->toBeNull();
    });

    it('reads replay settings from webhook config', function () {
        $config = new MidtransConfig([
            'driver' => 'midtrans',
            'environment' => 'sandbox',
            'server_key' => 'SB-Mid-server-test-key-1234567890',
            'client_key' => 'SB-Mid-client-test-key-1234567890',
            'merchant_id' => 'G141532850',
            'webhook' => [
                'replay_protection' => false,
                'replay_ttl_seconds' => 120,
                'cache_store' => 'array',
            ],
        ]);

        expect($config->webhookReplayProtection)->toBeFalse();
        expect($config->webhookReplayTtlSeconds)->toBe(120);
        expect($config->webhookReplayCacheStore)->toBe('array');
    });

    it('normalizes invalid replay ttl to minimum 1 second', function () {
        $config = new MidtransConfig([
            'driver' => 'midtrans',
            'environment' => 'sandbox',
            'server_key' => 'SB-Mid-server-test-key-1234567890',
            'client_key' => 'SB-Mid-client-test-key-1234567890',
            'merchant_id' => 'G141532850',
            'webhook' => [
                'replay_ttl_seconds' => 0,
            ],
        ]);

        expect($config->webhookReplayTtlSeconds)->toBe(1);
    });

});
