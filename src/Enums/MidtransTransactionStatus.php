<?php

namespace Aliziodev\PayIdMidtrans\Enums;

use Aliziodev\PayId\Enums\PaymentStatus;

/**
 * Status transaksi internal Midtrans.
 * Digunakan untuk mapping ke PaymentStatus standar PayID.
 */
enum MidtransTransactionStatus: string
{
    case Capture = 'capture';
    case Settlement = 'settlement';
    case Pending = 'pending';
    case Deny = 'deny';
    case Cancel = 'cancel';
    case Expire = 'expire';
    case Refund = 'refund';
    case PartialRefund = 'partial_refund';
    case Authorize = 'authorize';
    case Challenge = 'challenge';

    /**
     * Konversi ke PaymentStatus standar PayID.
     */
    public function toPaymentStatus(): PaymentStatus
    {
        return match ($this) {
            self::Settlement => PaymentStatus::Paid,
            self::Capture => PaymentStatus::Authorized,
            self::Authorize => PaymentStatus::Authorized,
            self::Pending => PaymentStatus::Pending,
            self::Challenge => PaymentStatus::Pending,
            self::Deny => PaymentStatus::Failed,
            self::Cancel => PaymentStatus::Cancelled,
            self::Expire => PaymentStatus::Expired,
            self::Refund => PaymentStatus::Refunded,
            self::PartialRefund => PaymentStatus::PartiallyRefunded,
        };
    }

    /**
     * Parse dari string Midtrans, dengan fallback ke Pending.
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Pending;
    }
}
