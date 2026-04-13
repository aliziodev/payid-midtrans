<?php

namespace Aliziodev\PayIdMidtrans;

use Aliziodev\MidtransPhp\Exceptions\MidtransApiException;
use Aliziodev\MidtransPhp\Exceptions\MidtransException;
use Aliziodev\MidtransPhp\MidtransClient;
use Aliziodev\MidtransPhp\Support\IdempotencyKey;
use Aliziodev\PayId\Contracts\DriverInterface;
use Aliziodev\PayId\Contracts\HasCapabilities;
use Aliziodev\PayId\Contracts\SupportsApprove;
use Aliziodev\PayId\Contracts\SupportsCancel;
use Aliziodev\PayId\Contracts\SupportsCharge;
use Aliziodev\PayId\Contracts\SupportsDeny;
use Aliziodev\PayId\Contracts\SupportsDirectCharge;
use Aliziodev\PayId\Contracts\SupportsExpire;
use Aliziodev\PayId\Contracts\SupportsRefund;
use Aliziodev\PayId\Contracts\SupportsStatus;
use Aliziodev\PayId\Contracts\SupportsSubscription;
use Aliziodev\PayId\Contracts\SupportsWebhookParsing;
use Aliziodev\PayId\Contracts\SupportsWebhookVerification;
use Aliziodev\PayId\DTO\ChargeRequest;
use Aliziodev\PayId\DTO\ChargeResponse;
use Aliziodev\PayId\DTO\NormalizedWebhook;
use Aliziodev\PayId\DTO\RefundRequest;
use Aliziodev\PayId\DTO\RefundResponse;
use Aliziodev\PayId\DTO\StatusResponse;
use Aliziodev\PayId\DTO\SubscriptionRequest;
use Aliziodev\PayId\DTO\SubscriptionResponse;
use Aliziodev\PayId\DTO\UpdateSubscriptionRequest;
use Aliziodev\PayId\Enums\Capability;
use Aliziodev\PayId\Exceptions\ProviderApiException;
use Aliziodev\PayId\Exceptions\ProviderNetworkException;
use Aliziodev\PayIdMidtrans\DTO\GopayAccountDetails;
use Aliziodev\PayIdMidtrans\DTO\GopayAccountLinkRequest;
use Aliziodev\PayIdMidtrans\Mappers\ChargeRequestMapper;
use Aliziodev\PayIdMidtrans\Mappers\CoreApiChargeRequestMapper;
use Aliziodev\PayIdMidtrans\Mappers\CoreApiChargeResponseMapper;
use Aliziodev\PayIdMidtrans\Mappers\GopayAccountMapper;
use Aliziodev\PayIdMidtrans\Mappers\RefundResponseMapper;
use Aliziodev\PayIdMidtrans\Mappers\SnapResponseMapper;
use Aliziodev\PayIdMidtrans\Mappers\StatusResponseMapper;
use Aliziodev\PayIdMidtrans\Mappers\SubscriptionMapper;
use Aliziodev\PayIdMidtrans\Webhooks\MidtransSignatureVerifier;
use Aliziodev\PayIdMidtrans\Webhooks\MidtransWebhookParser;
use Illuminate\Http\Request;

class MidtransDriver implements DriverInterface, SupportsApprove, SupportsCancel, SupportsCharge, SupportsDeny, SupportsDirectCharge, SupportsExpire, SupportsRefund, SupportsStatus, SupportsSubscription, SupportsWebhookParsing, SupportsWebhookVerification
{
    use HasCapabilities;

    public function __construct(
        protected readonly MidtransConfig $config,
        protected readonly MidtransClient $client,
        protected readonly ChargeRequestMapper $chargeMapper,
        protected readonly SnapResponseMapper $snapMapper,
        protected readonly CoreApiChargeRequestMapper $coreApiChargeMapper,
        protected readonly CoreApiChargeResponseMapper $coreApiResponseMapper,
        protected readonly StatusResponseMapper $statusMapper,
        protected readonly RefundResponseMapper $refundMapper,
        protected readonly GopayAccountMapper $gopayMapper,
        protected readonly SubscriptionMapper $subscriptionMapper,
        protected readonly MidtransSignatureVerifier $signatureVerifier,
        protected readonly MidtransWebhookParser $webhookParser,
    ) {}

    public function getName(): string
    {
        return 'midtrans';
    }

    public function getCapabilities(): array
    {
        return [
            Capability::Charge,
            Capability::DirectCharge,
            Capability::Status,
            Capability::Refund,
            Capability::Cancel,
            Capability::Expire,
            Capability::Approve,
            Capability::Deny,
            Capability::CreateSubscription,
            Capability::GetSubscription,
            Capability::UpdateSubscription,
            Capability::PauseSubscription,
            Capability::ResumeSubscription,
            Capability::CancelSubscription,
            Capability::WebhookVerification,
            Capability::WebhookParsing,
        ];
    }

    public function charge(ChargeRequest $request): ChargeResponse
    {
        $raw = $this->sdkCall(fn () => $this->withIdempotency('snap-charge', $request->merchantOrderId)
            ->snapCreateTransaction($this->chargeMapper->toSnapPayload($request))
        );

        return $this->snapMapper->toChargeResponse($raw, $request->merchantOrderId);
    }

    public function directCharge(ChargeRequest $request): ChargeResponse
    {
        $raw = $this->sdkCall(fn () => $this->withIdempotency('core-charge', $request->merchantOrderId)
            ->coreCharge($this->coreApiChargeMapper->toCorePayload($request))
        );

        return $this->coreApiResponseMapper->toChargeResponse($raw, $request->merchantOrderId);
    }

    public function status(string $merchantOrderId): StatusResponse
    {
        $raw = $this->sdkCall(fn () => $this->client->transactionStatus($merchantOrderId));

        return $this->statusMapper->toStatusResponse($raw);
    }

    public function cancel(string $merchantOrderId): StatusResponse
    {
        $raw = $this->sdkCall(fn () => $this->withIdempotency('core-cancel', $merchantOrderId)
            ->cancelTransaction($merchantOrderId)
        );

        return $this->statusMapper->toStatusResponse($raw);
    }

    public function expire(string $merchantOrderId): StatusResponse
    {
        $raw = $this->sdkCall(fn () => $this->withIdempotency('core-expire', $merchantOrderId)
            ->expireTransaction($merchantOrderId)
        );

        return $this->statusMapper->toStatusResponse($raw);
    }

    public function approve(string $merchantOrderId): StatusResponse
    {
        $raw = $this->sdkCall(fn () => $this->withIdempotency('core-approve', $merchantOrderId)
            ->approveTransaction($merchantOrderId)
        );

        return $this->statusMapper->toStatusResponse($raw);
    }

    public function deny(string $merchantOrderId): StatusResponse
    {
        $raw = $this->sdkCall(fn () => $this->withIdempotency('core-deny', $merchantOrderId)
            ->denyTransaction($merchantOrderId)
        );

        return $this->statusMapper->toStatusResponse($raw);
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $payload = array_filter([
            'refund_amount' => $request->amount,
            'reason' => $request->reason,
            'refund_key' => $request->refundKey,
        ], fn ($v) => $v !== null);

        $raw = $this->sdkCall(fn () => $this->withIdempotency('core-refund', $request->merchantOrderId)
            ->refundTransaction($request->merchantOrderId, $payload)
        );

        return $this->refundMapper->toRefundResponse($raw, $request->merchantOrderId);
    }

    public function verifyWebhook(Request $request): bool
    {
        return $this->signatureVerifier->verify($request);
    }

    public function parseWebhook(Request $request): NormalizedWebhook
    {
        $signatureValid = $this->signatureVerifier->verify($request);

        return $this->webhookParser->parse($request, $signatureValid);
    }

    public function createSubscription(SubscriptionRequest $request): SubscriptionResponse
    {
        $raw = $this->sdkCall(fn () => $this->withIdempotency('subscription-create', $request->subscriptionId)
            ->createSubscription($this->subscriptionMapper->toCreatePayload($request))
        );

        return $this->subscriptionMapper->toSubscriptionResponse($raw, $request->subscriptionId);
    }

    public function getSubscription(string $providerSubscriptionId): SubscriptionResponse
    {
        $raw = $this->sdkCall(fn () => $this->client->getSubscription($providerSubscriptionId));

        return $this->subscriptionMapper->toSubscriptionResponse($raw);
    }

    public function updateSubscription(UpdateSubscriptionRequest $request): SubscriptionResponse
    {
        $this->sdkCall(fn () => $this->withIdempotency('subscription-update', $request->providerSubscriptionId)
            ->updateSubscription($request->providerSubscriptionId, $this->subscriptionMapper->toUpdatePayload($request))
        );

        return $this->getSubscription($request->providerSubscriptionId);
    }

    public function pauseSubscription(string $providerSubscriptionId): SubscriptionResponse
    {
        $this->sdkCall(fn () => $this->withIdempotency('subscription-disable', $providerSubscriptionId)
            ->disableSubscription($providerSubscriptionId)
        );

        return $this->getSubscription($providerSubscriptionId);
    }

    public function resumeSubscription(string $providerSubscriptionId): SubscriptionResponse
    {
        $this->sdkCall(fn () => $this->withIdempotency('subscription-enable', $providerSubscriptionId)
            ->enableSubscription($providerSubscriptionId)
        );

        return $this->getSubscription($providerSubscriptionId);
    }

    public function cancelSubscription(string $providerSubscriptionId): SubscriptionResponse
    {
        $this->sdkCall(fn () => $this->withIdempotency('subscription-cancel', $providerSubscriptionId)
            ->cancelSubscription($providerSubscriptionId)
        );

        return $this->getSubscription($providerSubscriptionId);
    }

    public function linkGopayAccount(GopayAccountLinkRequest $request): GopayAccountDetails
    {
        $raw = $this->sdkCall(fn () => $this->withIdempotency('gopay-link', $request->phoneNumber)
            ->linkPaymentAccount($this->gopayMapper->toLinkPayload($request))
        );

        return $this->gopayMapper->toAccountDetails($raw);
    }

    public function getGopayAccount(string $accountId): GopayAccountDetails
    {
        $raw = $this->sdkCall(fn () => $this->client->getPaymentAccount($accountId));

        return $this->gopayMapper->toAccountDetails($raw);
    }

    public function unlinkGopayAccount(string $accountId): bool
    {
        $this->sdkCall(fn () => $this->withIdempotency('gopay-unlink', $accountId)
            ->unlinkPaymentAccount($accountId)
        );

        return true;
    }

    protected function withIdempotency(string $scope, string $reference): MidtransClient
    {
        return $this->client->withIdempotencyKey(IdempotencyKey::generate($scope.'-'.trim($reference)));
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     *
     * @throws ProviderApiException
     * @throws ProviderNetworkException
     */
    protected function sdkCall(callable $callback): array
    {
        try {
            return $callback();
        } catch (MidtransApiException $e) {
            throw new ProviderApiException(
                driver: 'midtrans',
                message: $e->getMessage(),
                httpStatus: $e->statusCode,
                rawResponse: $e->payload,
                previous: $e,
            );
        } catch (MidtransException $e) {
            throw new ProviderNetworkException('midtrans', $e->getMessage(), $e);
        }
    }
}
