<?php

namespace Aliziodev\PayIdMidtrans\Mappers;

use Aliziodev\PayId\DTO\ChargeRequest;
use Aliziodev\PayId\Enums\PaymentChannel;
use Aliziodev\PayId\Exceptions\PayloadMappingException;

/**
 * Mapper: ChargeRequest (PayID) → Midtrans Snap API payload
 *
 * Menggunakan Snap API karena mendukung semua channel utama
 * dengan satu endpoint, dan menghasilkan redirect_url siap pakai.
 */
class ChargeRequestMapper
{
    /**
     * @return array<string, mixed>
     */
    public function toSnapPayload(ChargeRequest $request): array
    {
        $payload = [
            'transaction_details' => [
                'order_id' => $request->merchantOrderId,
                'gross_amount' => $request->amount,
            ],
            'customer_details' => $this->buildCustomerDetails($request),
            'enabled_payments' => [$this->mapChannel($request->channel)],
        ];

        if (! empty($request->items)) {
            $payload['item_details'] = $this->buildItemDetails($request);
        }

        if ($request->callbackUrl !== null) {
            $payload['callbacks']['notification'] = $request->callbackUrl;
        }

        if ($request->successUrl !== null) {
            $payload['callbacks']['finish'] = $request->successUrl;
        }

        if ($request->expiresAt !== null) {
            $payload['expiry'] = [
                'start_time' => now()->format('Y-m-d H:i:s O'),
                'unit' => 'minutes',
                'duration' => (int) now()->diffInMinutes($request->expiresAt),
            ];
        }

        if ($request->description !== null) {
            $payload['custom_expiry'] = ['order_description' => $request->description];
        }

        if (! empty($request->metadata)) {
            $payload['custom_field1'] = json_encode($request->metadata);
        }

        return $payload;
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

        if ($request->customer->address !== null) {
            $customer['billing_address'] = [
                'address' => $request->customer->address,
                'country_code' => 'IDN',
            ];
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

    /**
     * Map PaymentChannel standar PayID ke nilai enabled_payments Midtrans Snap.
     *
     * @throws PayloadMappingException
     */
    public function mapChannel(PaymentChannel $channel): string
    {
        return match ($channel) {
            PaymentChannel::VaBca => 'bca_va',
            PaymentChannel::VaBni => 'bni_va',
            PaymentChannel::VaBri => 'bri_va',
            PaymentChannel::VaMandiri => 'mandiri_va',
            PaymentChannel::VaPermata => 'permata_va',
            PaymentChannel::VaOther => 'other_va',
            PaymentChannel::VaCimb => 'cimb_va',
            PaymentChannel::Qris => 'qris',
            PaymentChannel::Gopay => 'gopay',
            PaymentChannel::Shopeepay => 'shopeepay',
            PaymentChannel::CreditCard => 'credit_card',
            PaymentChannel::DebitCard => 'credit_card',
            PaymentChannel::CstoreAlfamart => 'alfamart',
            PaymentChannel::CstoreIndomaret => 'indomaret',
            default => throw new PayloadMappingException(
                'midtrans',
                $channel->value,
            ),
        };
    }
}
