<?php

namespace Aliziodev\PayIdMidtrans\Webhooks;

use Aliziodev\PayId\Support\Signature;
use Aliziodev\PayIdMidtrans\MidtransConfig;
use Illuminate\Http\Request;

/**
 * Verifikasi signature webhook Midtrans.
 *
 * Midtrans mengirim signature_key di dalam body JSON, bukan di header.
 * Algoritma: SHA512(order_id + status_code + gross_amount + server_key)
 *
 * Referensi: https://docs.midtrans.com/reference/receiving-notifications
 */
class MidtransSignatureVerifier
{
    public function __construct(
        protected readonly MidtransConfig $config,
    ) {}

    /**
     * Verifikasi signature dari notification payload Midtrans.
     *
     * @param  Request  $request  Raw HTTP request (harus menggunakan getContent())
     */
    public function verify(Request $request): bool
    {
        $raw = $request->getContent();

        if (empty($raw)) {
            return false;
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return false;
        }

        return $this->verifyPayload($payload);
    }

    /**
     * Verifikasi array payload yang sudah di-decode.
     * Berguna untuk testing langsung dengan array.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyPayload(array $payload): bool
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signatureKey = (string) ($payload['signature_key'] ?? '');

        if (empty($orderId) || empty($statusCode) || empty($grossAmount) || empty($signatureKey)) {
            return false;
        }

        $expected = Signature::sha512(
            $orderId.$statusCode.$grossAmount.$this->config->serverKey,
        );

        return Signature::timingSafeEquals($expected, $signatureKey);
    }
}
