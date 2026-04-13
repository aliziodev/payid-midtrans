<?php

namespace Aliziodev\PayIdMidtrans\Mappers;

use Aliziodev\PayId\DTO\StatusResponse;
use Aliziodev\PayId\Enums\PaymentChannel;
use Aliziodev\PayId\Exceptions\ProviderResponseException;
use Aliziodev\PayIdMidtrans\Enums\MidtransTransactionStatus;
use Carbon\Carbon;

/**
 * Mapper: Midtrans Core API status response → StatusResponse (PayID)
 */
class StatusResponseMapper
{
    /**
     * @param  array<string, mixed>  $raw
     *
     * @throws ProviderResponseException
     */
    public function toStatusResponse(array $raw): StatusResponse
    {
        if (empty($raw['order_id']) || empty($raw['transaction_status'])) {
            throw new ProviderResponseException(
                'midtrans',
                'Core API status response missing order_id or transaction_status.',
            );
        }

        $status = MidtransTransactionStatus::fromString($raw['transaction_status']);
        $paidAt = $this->resolvePaidAt($raw, $status);
        $amount = isset($raw['gross_amount']) ? (int) (float) $raw['gross_amount'] : null;
        $channel = $this->mapPaymentType($raw);

        return new StatusResponse(
            providerName: 'midtrans',
            providerTransactionId: $raw['transaction_id'] ?? '',
            merchantOrderId: $raw['order_id'],
            status: $status->toPaymentStatus(),
            rawResponse: $raw,
            paidAt: $paidAt,
            amount: $amount,
            currency: 'IDR',
            channel: $channel,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function resolvePaidAt(array $raw, MidtransTransactionStatus $status): ?Carbon
    {
        if (! in_array($status, [MidtransTransactionStatus::Settlement, MidtransTransactionStatus::Capture], true)) {
            return null;
        }

        $timestamp = $raw['settlement_time'] ?? $raw['transaction_time'] ?? null;

        return $timestamp ? Carbon::parse($timestamp) : null;
    }

    /**
     * @param  array<string, mixed>  $raw
     *                                     Map payment_type Midtrans (+ VA bank / store) ke PaymentChannel PayID.
     */
    public function mapPaymentType(array $raw): ?PaymentChannel
    {
        $type = $raw['payment_type'] ?? null;

        return match ($type) {
            'qris' => PaymentChannel::Qris,
            'gopay' => PaymentChannel::Gopay,
            'shopeepay' => PaymentChannel::Shopeepay,
            'credit_card' => PaymentChannel::CreditCard,
            'echannel' => PaymentChannel::VaMandiri,
            'permata' => PaymentChannel::VaPermata,
            'bank_transfer' => $this->mapBankTransfer($raw),
            'cstore' => $this->mapCstore($raw),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function mapBankTransfer(array $raw): PaymentChannel
    {
        // va_numbers: [{"bank": "bca", "va_number": "xxx"}]
        $bank = strtolower($raw['va_numbers'][0]['bank'] ?? '');

        return match ($bank) {
            'bca' => PaymentChannel::VaBca,
            'bni' => PaymentChannel::VaBni,
            'bri' => PaymentChannel::VaBri,
            'cimb' => PaymentChannel::VaCimb,
            default => PaymentChannel::VaOther,
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function mapCstore(array $raw): PaymentChannel
    {
        $store = strtolower($raw['store'] ?? '');

        return match ($store) {
            'alfamart' => PaymentChannel::CstoreAlfamart,
            'indomaret' => PaymentChannel::CstoreIndomaret,
            default => PaymentChannel::CstoreAlfamart,
        };
    }
}
