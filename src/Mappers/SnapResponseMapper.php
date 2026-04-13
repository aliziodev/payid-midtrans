<?php

namespace Aliziodev\PayIdMidtrans\Mappers;

use Aliziodev\PayId\DTO\ChargeResponse;
use Aliziodev\PayId\Enums\PaymentStatus;
use Aliziodev\PayId\Exceptions\ProviderResponseException;

/**
 * Mapper: Midtrans Snap API response → ChargeResponse (PayID)
 *
 * Snap API response format:
 * {
 *   "token": "xxxx",
 *   "redirect_url": "https://app.sandbox.midtrans.com/snap/v2/vtweb/xxxx"
 * }
 */
class SnapResponseMapper
{
    /**
     * @param  array<string, mixed>  $raw
     *
     * @throws ProviderResponseException
     */
    public function toChargeResponse(array $raw, string $merchantOrderId): ChargeResponse
    {
        if (empty($raw['token']) || empty($raw['redirect_url'])) {
            throw new ProviderResponseException(
                'midtrans',
                'Snap API response missing token or redirect_url.',
            );
        }

        return new ChargeResponse(
            providerName: 'midtrans',
            providerTransactionId: $raw['token'],
            merchantOrderId: $merchantOrderId,
            status: PaymentStatus::Pending,
            rawResponse: $raw,
            paymentUrl: $raw['redirect_url'],
        );
    }
}
