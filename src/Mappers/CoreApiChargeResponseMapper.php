<?php

namespace Aliziodev\PayIdMidtrans\Mappers;

use Aliziodev\PayId\DTO\ChargeResponse;
use Aliziodev\PayId\Enums\PaymentStatus;
use Aliziodev\PayId\Exceptions\ProviderResponseException;
use Carbon\Carbon;

/**
 * Mapper: Midtrans Core API charge response → ChargeResponse (PayID)
 *
 * Core API mengembalikan detail pembayaran langsung (VA number, QR, action URL)
 * — berbeda dengan Snap yang hanya mengembalikan redirect_url.
 */
class CoreApiChargeResponseMapper
{
    /**
     * @param  array<string, mixed>  $raw
     *
     * @throws ProviderResponseException
     */
    public function toChargeResponse(array $raw, string $merchantOrderId): ChargeResponse
    {
        if (empty($raw['transaction_id'])) {
            throw new ProviderResponseException(
                'midtrans',
                'Core API charge response missing transaction_id.',
            );
        }

        return new ChargeResponse(
            providerName: 'midtrans',
            providerTransactionId: $raw['transaction_id'],
            merchantOrderId: $merchantOrderId,
            status: PaymentStatus::Pending,
            rawResponse: $raw,
            paymentUrl: $this->extractPaymentUrl($raw),
            qrString: $this->extractQrString($raw),
            vaNumber: $this->extractVaNumber($raw),
            vaBankCode: $this->extractVaBankCode($raw),
            expiresAt: isset($raw['expiry_time'])
                ? Carbon::parse($raw['expiry_time'])
                : null,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function extractVaNumber(array $raw): ?string
    {
        // bank_transfer VA
        if (! empty($raw['va_numbers'][0]['va_number'])) {
            return $raw['va_numbers'][0]['va_number'];
        }
        // echannel (Mandiri)
        if (! empty($raw['bill_key'])) {
            return $raw['biller_code'].'/'.$raw['bill_key'];
        }
        // permata
        if (! empty($raw['permata_va_number'])) {
            return $raw['permata_va_number'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function extractVaBankCode(array $raw): ?string
    {
        if (! empty($raw['va_numbers'][0]['bank'])) {
            return strtoupper($raw['va_numbers'][0]['bank']);
        }
        if (! empty($raw['biller_code'])) {
            return 'MANDIRI';
        }
        if (! empty($raw['permata_va_number'])) {
            return 'PERMATA';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function extractQrString(array $raw): ?string
    {
        // QRIS: ada di actions[type=generate-qr-code].url atau qr_string
        if (! empty($raw['qr_string'])) {
            return $raw['qr_string'];
        }
        foreach ($raw['actions'] ?? [] as $action) {
            if (($action['name'] ?? '') === 'generate-qr-code') {
                return $action['url'] ?? null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function extractPaymentUrl(array $raw): ?string
    {
        // GoPay / ShopeePay: deeplink atau web payment URL
        foreach ($raw['actions'] ?? [] as $action) {
            if (in_array($action['name'] ?? '', ['deeplink-redirect', 'pay-using-gopay-or-qris', 'get-qr-code'], true)) {
                return $action['url'] ?? null;
            }
        }

        return null;
    }
}
