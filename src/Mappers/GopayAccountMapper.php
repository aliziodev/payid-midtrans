<?php

namespace Aliziodev\PayIdMidtrans\Mappers;

use Aliziodev\PayId\Exceptions\ProviderResponseException;
use Aliziodev\PayIdMidtrans\DTO\GopayAccountDetails;
use Aliziodev\PayIdMidtrans\DTO\GopayAccountLinkRequest;

class GopayAccountMapper
{
    /**
     * Build payload untuk POST /v2/pay/account (initiate binding).
     *
     * @return array<string, mixed>
     */
    public function toLinkPayload(GopayAccountLinkRequest $request): array
    {
        $payload = [
            'payment_type' => 'gopay',
            'gopay_partner' => array_filter([
                'phone_number' => $request->phoneNumber,
                'callback_url' => $request->callbackUrl,
                'country_code' => '62',
            ]),
        ];

        if ($request->gopayPartnerName !== null) {
            $payload['gopay_partner']['name'] = $request->gopayPartnerName;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $raw
     *                                     Map response dari /v2/pay/account ke GopayAccountDetails.
     *
     * @throws ProviderResponseException
     */
    public function toAccountDetails(array $raw): GopayAccountDetails
    {
        if (empty($raw['account_id'])) {
            throw new ProviderResponseException(
                'midtrans',
                'GoPay account response missing account_id.',
            );
        }

        // approval_url ada di metadata.approval_url saat status PENDING
        $approvalUrl = $raw['metadata']['approval_url']
            ?? $raw['actions'][0]['url']
            ?? null;

        return new GopayAccountDetails(
            accountId: $raw['account_id'],
            phoneNumber: $raw['phone_number']
                ?? $raw['masked_phone_number']
                ?? $raw['metadata']['phone_number']
                ?? $raw['account_id'],
            status: $raw['account_status'] ?? 'PENDING',
            approvalUrl: $approvalUrl,
            rawResponse: $raw,
        );
    }
}
