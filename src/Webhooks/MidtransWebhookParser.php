<?php

namespace Aliziodev\PayIdMidtrans\Webhooks;

use Aliziodev\PayId\DTO\NormalizedWebhook;
use Aliziodev\PayId\Exceptions\WebhookParsingException;
use Aliziodev\PayIdMidtrans\Mappers\WebhookMapper;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

/**
 * Parser webhook Midtrans.
 * Mengurai raw JSON notification ke NormalizedWebhook.
 */
class MidtransWebhookParser
{
    public function __construct(
        protected readonly WebhookMapper $mapper,
        protected readonly ?CacheRepository $cache = null,
        protected readonly bool $replayProtectionEnabled = false,
        protected readonly int $replayTtlSeconds = 3600,
        protected readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * @throws WebhookParsingException
     */
    public function parse(Request $request, bool $signatureValid): NormalizedWebhook
    {
        $raw = $request->getContent();

        if (empty($raw)) {
            throw new WebhookParsingException('midtrans', 'Request body is empty.');
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            throw new WebhookParsingException('midtrans', 'Failed to decode JSON payload.');
        }

        $webhook = $this->mapper->toNormalizedWebhook($payload, $signatureValid);

        if ($this->replayProtectionEnabled) {
            $this->guardReplay($webhook, $payload);
        }

        return $webhook;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws WebhookParsingException
     */
    private function guardReplay(NormalizedWebhook $webhook, array $payload): void
    {
        if ($this->cache === null) {
            return;
        }

        $cacheKey = $this->buildReplayCacheKey($webhook, $payload);
        $stored = $this->cache->add($cacheKey, true, now()->addSeconds($this->replayTtlSeconds));

        if (! $stored) {
            $this->logger?->warning('payid.midtrans.webhook.replay_detected', [
                'merchant_order_id' => $webhook->merchantOrderId,
                'provider_transaction_id' => $webhook->providerTransactionId,
                'event_type' => $webhook->eventType,
                'status' => $webhook->status->value,
                'replay_key' => substr($cacheKey, -16),
                'replay_ttl_seconds' => $this->replayTtlSeconds,
            ]);

            throw new WebhookParsingException('midtrans', 'Duplicate webhook detected (replay).');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildReplayCacheKey(NormalizedWebhook $webhook, array $payload): string
    {
        $transactionId = $webhook->providerTransactionId ?? (string) ($payload['transaction_id'] ?? '-');
        $eventType = $webhook->eventType ?? (string) ($payload['transaction_status'] ?? '-');
        $status = $webhook->status->value;

        return sprintf(
            'payid:midtrans:webhook:replay:%s',
            sha1(implode('|', [
                $webhook->merchantOrderId,
                $transactionId,
                $eventType,
                $status,
            ])),
        );
    }
}
