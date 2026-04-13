<?php

namespace Aliziodev\PayIdMidtrans\Mappers;

use Aliziodev\PayId\DTO\ChargeRequest;
use Aliziodev\PayId\Enums\PaymentChannel;
use Aliziodev\PayId\Exceptions\PayloadMappingException;

/**
 * Mapper: ChargeRequest (PayID) → Midtrans Core API charge payload
 *
 * Core API mengembalikan VA number, QR string, atau action URL langsung
 * dalam response body — tanpa redirect ke Snap payment page.
 * Payload-nya berbeda per channel (payment_type).
 */
class CoreApiChargeRequestMapper
{
    /**
     * @return array<string, mixed>
     */
    public function toCorePayload(ChargeRequest $request): array
    {
        $base = [
            'transaction_details' => [
                'order_id' => $request->merchantOrderId,
                'gross_amount' => $request->amount,
            ],
            'customer_details' => $this->buildCustomerDetails($request),
        ];

        if (! empty($request->items)) {
            $base['item_details'] = $this->buildItemDetails($request);
        }

        return array_merge($base, $this->buildChannelPayload($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildChannelPayload(ChargeRequest $request): array
    {
        return match ($request->channel) {
            // ---------------------------------------------------------------
            // Virtual Account — bank_transfer
            // ---------------------------------------------------------------
            PaymentChannel::VaBca => [
                'payment_type' => 'bank_transfer',
                'bank_transfer' => ['bank' => 'bca'],
            ],
            PaymentChannel::VaBni => [
                'payment_type' => 'bank_transfer',
                'bank_transfer' => ['bank' => 'bni'],
            ],
            PaymentChannel::VaBri => [
                'payment_type' => 'bank_transfer',
                'bank_transfer' => ['bank' => 'bri'],
            ],
            PaymentChannel::VaCimb => [
                'payment_type' => 'bank_transfer',
                'bank_transfer' => ['bank' => 'cimb'],
            ],
            PaymentChannel::VaPermata => [
                'payment_type' => 'bank_transfer',
                'bank_transfer' => ['bank' => 'permata'],
            ],
            // Mandiri pakai echannel, bukan bank_transfer
            PaymentChannel::VaMandiri => [
                'payment_type' => 'echannel',
                'echannel' => [
                    'bill_info1' => $request->description ?? 'Pembayaran',
                    'bill_info2' => $request->merchantOrderId,
                ],
            ],

            // ---------------------------------------------------------------
            // QRIS
            // ---------------------------------------------------------------
            PaymentChannel::Qris => [
                'payment_type' => 'qris',
                'qris' => ['acquirer' => 'gopay'],
            ],

            // ---------------------------------------------------------------
            // E-Wallet
            // ---------------------------------------------------------------
            PaymentChannel::Gopay => [
                'payment_type' => 'gopay',
                'gopay' => array_filter([
                    'enable_callback' => true,
                    'callback_url' => $request->callbackUrl,
                ], fn ($v) => $v !== null),
            ],
            PaymentChannel::Shopeepay => [
                'payment_type' => 'shopeepay',
                'shopeepay' => array_filter([
                    'callback_url' => $request->callbackUrl,
                ], fn ($v) => $v !== null),
            ],

            // ---------------------------------------------------------------
            // Convenience Store
            // ---------------------------------------------------------------
            PaymentChannel::CstoreAlfamart => [
                'payment_type' => 'cstore',
                'cstore' => [
                    'store' => 'Alfamart',
                    'message' => $request->description ?? $request->merchantOrderId,
                ],
            ],
            PaymentChannel::CstoreIndomaret => [
                'payment_type' => 'cstore',
                'cstore' => [
                    'store' => 'Indomaret',
                    'message' => $request->description ?? $request->merchantOrderId,
                ],
            ],

            // ---------------------------------------------------------------
            // Credit Card — charge dengan token (token_id wajib di metadata)
            // ---------------------------------------------------------------
            PaymentChannel::CreditCard => $this->buildCreditCardPayload($request),

            default => throw new PayloadMappingException('midtrans', $request->channel->value),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreditCardPayload(ChargeRequest $request): array
    {
        $tokenId = $request->metadata['token_id'] ?? null;

        if ($tokenId === null) {
            throw new PayloadMappingException(
                'midtrans',
                'credit_card via Core API requires metadata[token_id].',
            );
        }

        return [
            'payment_type' => 'credit_card',
            'credit_card' => array_filter([
                'token_id' => $tokenId,
                'authentication' => $request->metadata['authentication'] ?? false,
                'save_token_id' => $request->metadata['save_card'] ?? false,
                'installment_term' => $request->metadata['installment_term'] ?? null,
                'bins' => $request->metadata['bins'] ?? null,
            ], fn ($v) => $v !== null && $v !== false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCustomerDetails(ChargeRequest $request): array
    {
        $customer = [
            'first_name' => $request->customer->name,
            'email' => $request->customer->email,
        ];

        if ($request->customer->phone !== null) {
            $customer['phone'] = $request->customer->phone;
        }

        return $customer;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildItemDetails(ChargeRequest $request): array
    {
        return array_map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'price' => $item->price,
            'quantity' => $item->quantity,
        ], $request->items);
    }
}
