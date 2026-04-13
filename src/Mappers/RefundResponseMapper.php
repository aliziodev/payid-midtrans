<?php

namespace Aliziodev\PayIdMidtrans\Mappers;

use Aliziodev\PayId\DTO\RefundResponse;
use Aliziodev\PayId\Enums\PaymentStatus;
use Aliziodev\PayId\Exceptions\ProviderResponseException;
use Carbon\Carbon;

/**
 * Mapper: Midtrans Core API refund response → RefundResponse (PayID)
 */
class RefundResponseMapper
{
    /**
     * @param  array<string, mixed>  $raw
     *
     * @throws ProviderResponseException
     */
    public function toRefundResponse(array $raw, string $merchantOrderId): RefundResponse
    {
        if (empty($raw['transaction_id'])) {
            throw new ProviderResponseException(
                'midtrans',
                'Refund response missing transaction_id.',
            );
        }

        $refundId = $raw['refund_key']
            ?? $raw['refund_chargeback_id']
            ?? $raw['transaction_id'];

        $status = match ($raw['transaction_status'] ?? '') {
            'refund' => PaymentStatus::Refunded,
            'partial_refund' => PaymentStatus::Refunded,
            default => PaymentStatus::Refunded,
        };

        $amount = isset($raw['refund_amount']) ? (int) (float) $raw['refund_amount'] : null;
        $refundedAt = isset($raw['transaction_time'])
            ? Carbon::parse($raw['transaction_time'])
            : null;

        return new RefundResponse(
            providerName: 'midtrans',
            merchantOrderId: $merchantOrderId,
            refundId: (string) $refundId,
            status: $status,
            rawResponse: $raw,
            amount: $amount,
            refundedAt: $refundedAt,
        );
    }
}
