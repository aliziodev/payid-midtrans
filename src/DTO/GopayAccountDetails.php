<?php

namespace Aliziodev\PayIdMidtrans\DTO;

final class GopayAccountDetails
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        /** ID akun GoPay yang sudah terhubung. Digunakan untuk direct charge. */
        public readonly string $accountId,
        public readonly string $phoneNumber,
        /** Status binding: 'ENABLED' | 'DISABLED' | 'PENDING' */
        public readonly string $status,
        /** URL untuk redirect customer agar menyetujui linking (saat status PENDING). */
        public readonly ?string $approvalUrl,
        public readonly array $rawResponse,
    ) {}

    public function isEnabled(): bool
    {
        return $this->status === 'ENABLED';
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }
}
