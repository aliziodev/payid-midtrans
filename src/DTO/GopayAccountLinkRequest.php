<?php

namespace Aliziodev\PayIdMidtrans\DTO;

final class GopayAccountLinkRequest
{
    public function __construct(
        /** Nomor HP customer yang terdaftar di GoPay. Format: +628xxx */
        public readonly string $phoneNumber,
        /** URL callback setelah proses linking selesai (approval/rejection). */
        public readonly string $callbackUrl,
        /** Opsional: nama merchant yang ditampilkan ke customer. */
        public readonly ?string $gopayPartnerName = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function make(array $data): self
    {
        return new self(
            phoneNumber: $data['phone_number'],
            callbackUrl: $data['callback_url'],
            gopayPartnerName: $data['gopay_partner_name'] ?? null,
        );
    }
}
