<?php

namespace Aliziodev\PayIdMidtrans;

use Aliziodev\MidtransPhp\MidtransClient;
use Aliziodev\PayId\Factories\DriverFactory;
use Aliziodev\PayIdMidtrans\Mappers\ChargeRequestMapper;
use Aliziodev\PayIdMidtrans\Mappers\CoreApiChargeRequestMapper;
use Aliziodev\PayIdMidtrans\Mappers\CoreApiChargeResponseMapper;
use Aliziodev\PayIdMidtrans\Mappers\GopayAccountMapper;
use Aliziodev\PayIdMidtrans\Mappers\RefundResponseMapper;
use Aliziodev\PayIdMidtrans\Mappers\SnapResponseMapper;
use Aliziodev\PayIdMidtrans\Mappers\StatusResponseMapper;
use Aliziodev\PayIdMidtrans\Mappers\SubscriptionMapper;
use Aliziodev\PayIdMidtrans\Mappers\WebhookMapper;
use Aliziodev\PayIdMidtrans\Support\Http\LaravelHttpTransport;
use Aliziodev\PayIdMidtrans\Webhooks\MidtransSignatureVerifier;
use Aliziodev\PayIdMidtrans\Webhooks\MidtransWebhookParser;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class MidtransServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->resolving(DriverFactory::class, function (DriverFactory $factory): void {
            $factory->extend('midtrans', function (array $config): MidtransDriver {
                $midtransConfig = new MidtransConfig($config);
                $statusMapper = new StatusResponseMapper;
                $cacheFactory = $this->app->make(CacheFactory::class);
                $cacheStore = $midtransConfig->webhookReplayCacheStore !== null
                    ? $cacheFactory->store($midtransConfig->webhookReplayCacheStore)
                    : $cacheFactory->store();
                $logger = $this->app->make(LoggerInterface::class);
                $sdkClient = new MidtransClient(
                    config: $midtransConfig->toSdkConfig(),
                    transport: new LaravelHttpTransport,
                );

                return new MidtransDriver(
                    config: $midtransConfig,
                    client: $sdkClient,
                    chargeMapper: new ChargeRequestMapper,
                    snapMapper: new SnapResponseMapper,
                    coreApiChargeMapper: new CoreApiChargeRequestMapper,
                    coreApiResponseMapper: new CoreApiChargeResponseMapper,
                    statusMapper: $statusMapper,
                    refundMapper: new RefundResponseMapper,
                    gopayMapper: new GopayAccountMapper,
                    subscriptionMapper: new SubscriptionMapper,
                    signatureVerifier: new MidtransSignatureVerifier($midtransConfig),
                    webhookParser: new MidtransWebhookParser(
                        new WebhookMapper($statusMapper),
                        cache: $cacheStore,
                        replayProtectionEnabled: $midtransConfig->webhookReplayProtection,
                        replayTtlSeconds: $midtransConfig->webhookReplayTtlSeconds,
                        logger: $logger,
                    ),
                );
            });
        });
    }
}
