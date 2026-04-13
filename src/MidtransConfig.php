<?php

namespace Aliziodev\PayIdMidtrans;

use Aliziodev\MidtransPhp\Config\MidtransConfig as SdkMidtransConfig;
use Aliziodev\PayId\Exceptions\InvalidCredentialException;

final class MidtransConfig
{
    public readonly string $serverKey;

    public readonly string $clientKey;

    public readonly string $merchantId;

    public readonly bool $isProduction;

    public readonly string $snapBaseUrl;

    public readonly string $coreBaseUrl;

    public readonly string $subscriptionBaseUrl;

    public readonly int $timeout;

    public readonly int $retryTimes;

    public readonly bool $webhookReplayProtection;

    public readonly int $webhookReplayTtlSeconds;

    public readonly ?string $webhookReplayCacheStore;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        /** @var array<string, mixed> $credentials */
        $credentials = is_array($config['credentials'] ?? null)
            ? $config['credentials']
            : [];

        $environment = $config['environment']
            ?? $credentials['environment']
            ?? (getenv('MIDTRANS_ENV') ?: 'sandbox');

        $this->serverKey = (string) ($config['server_key'] ?? $credentials['server_key'] ?? (getenv('MIDTRANS_SERVER_KEY') ?: ''));
        $this->clientKey = (string) ($config['client_key'] ?? $credentials['client_key'] ?? (getenv('MIDTRANS_CLIENT_KEY') ?: ''));
        $this->merchantId = (string) ($config['merchant_id'] ?? $credentials['merchant_id'] ?? (getenv('MIDTRANS_MERCHANT_ID') ?: ''));

        $this->isProduction = $environment === 'production';
        $this->timeout = (int) ($config['timeout'] ?? $credentials['timeout'] ?? 30);
        $this->retryTimes = (int) ($config['retry_times'] ?? $credentials['retry_times'] ?? 1);

        /** @var array<string, mixed> $webhook */
        $webhook = is_array($config['webhook'] ?? null)
            ? $config['webhook']
            : [];

        $this->webhookReplayProtection = (bool) ($webhook['replay_protection'] ?? true);
        $this->webhookReplayTtlSeconds = max(1, (int) ($webhook['replay_ttl_seconds'] ?? 3600));
        $this->webhookReplayCacheStore = isset($webhook['cache_store']) && $webhook['cache_store'] !== ''
            ? (string) $webhook['cache_store']
            : null;

        $this->snapBaseUrl = $config['endpoints']['snap_base_url']
            ?? ($this->isProduction
                ? 'https://app.midtrans.com/snap/v1'
                : 'https://app.sandbox.midtrans.com/snap/v1');

        $this->coreBaseUrl = $config['endpoints']['core_base_url']
            ?? ($this->isProduction
                ? 'https://api.midtrans.com/v2'
                : 'https://api.sandbox.midtrans.com/v2');

        $this->subscriptionBaseUrl = $config['endpoints']['subscription_base_url']
            ?? ($this->isProduction
                ? 'https://api.midtrans.com/v1'
                : 'https://api.sandbox.midtrans.com/v1');

        $this->validate();
    }

    public function toSdkConfig(): SdkMidtransConfig
    {
        $coreRoot = preg_replace('#/v[0-9]+$#', '', rtrim($this->coreBaseUrl, '/'));
        $snapBiRoot = preg_replace('#/v[0-9]+$#', '', rtrim($this->subscriptionBaseUrl, '/'));

        return new SdkMidtransConfig(
            serverKey: $this->serverKey,
            clientKey: $this->clientKey,
            isProduction: $this->isProduction,
            timeoutSeconds: $this->timeout,
            maxRetries: $this->retryTimes,
            retryDelayMs: 500,
            coreBaseUrlOverride: is_string($coreRoot) ? $coreRoot : null,
            snapBaseUrlOverride: rtrim($this->snapBaseUrl, '/'),
            snapBiBaseUrlOverride: is_string($snapBiRoot) ? $snapBiRoot : null,
        );
    }

    private function validate(): void
    {
        if (empty($this->serverKey)) {
            throw new InvalidCredentialException('midtrans', 'server_key');
        }
    }
}
