<?php

namespace Aliziodev\PayIdMidtrans\Mappers;

use Aliziodev\PayId\DTO\NormalizedWebhook;
use Aliziodev\PayId\Exceptions\WebhookParsingException;
use Aliziodev\PayIdMidtrans\Enums\MidtransTransactionStatus;
use Carbon\Carbon;

/**
 * Mapper: Midtrans notification payload → NormalizedWebhook (PayID)
 */
class WebhookMapper
{
    public function __construct(
        protected readonly StatusResponseMapper $statusMapper,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws WebhookParsingException
     */
    public function toNormalizedWebhook(array $payload, bool $signatureValid): NormalizedWebhook
    {
        if (empty($payload['order_id']) || empty($payload['transaction_status'])) {
            throw new WebhookParsingException(
                'midtrans',
                'Webhook payload missing order_id or transaction_status.',
            );
        }

        $status = MidtransTransactionStatus::fromString($payload['transaction_status']);
        $channel = $this->statusMapper->mapPaymentType($payload);
        $amount = isset($payload['gross_amount']) ? (int) (float) $payload['gross_amount'] : null;

        $occurredAt = isset($payload['transaction_time'])
            ? Carbon::parse($payload['transaction_time'])
            : Carbon::now();

        return new NormalizedWebhook(
            provider: 'midtrans',
            merchantOrderId: $payload['order_id'],
            status: $status->toPaymentStatus(),
            signatureValid: $signatureValid,
            rawPayload: $payload,
            providerTransactionId: $payload['transaction_id'] ?? null,
            eventType: (string) $payload['transaction_status'],
            amount: $amount,
            currency: 'IDR',
            channel: $channel,
            occurredAt: $occurredAt,
        );
    }
}
