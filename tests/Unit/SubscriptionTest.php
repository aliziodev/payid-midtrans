<?php

use Aliziodev\PayId\DTO\SubscriptionRequest;
use Aliziodev\PayId\DTO\UpdateSubscriptionRequest;
use Aliziodev\PayId\Enums\Capability;
use Aliziodev\PayId\Enums\SubscriptionInterval;
use Aliziodev\PayId\Enums\SubscriptionStatus;
use Aliziodev\PayId\Managers\PayIdManager;
use Illuminate\Support\Facades\Http;

/**
 * @return array<string, mixed>
 */
function fixturePayload(string $name): array
{
    /** @var array<string, mixed> $data */
    $data = json_decode(file_get_contents(__DIR__.'/../Fixtures/'.$name.'.json'), true);

    return $data;
}

describe('MidtransDriver Subscription', function () {

    it('declares all subscription capabilities', function () {
        $driver = app(PayIdManager::class)->getDriver();

        expect($driver->supports(Capability::CreateSubscription))->toBeTrue();
        expect($driver->supports(Capability::GetSubscription))->toBeTrue();
        expect($driver->supports(Capability::UpdateSubscription))->toBeTrue();
        expect($driver->supports(Capability::PauseSubscription))->toBeTrue();
        expect($driver->supports(Capability::ResumeSubscription))->toBeTrue();
    });

    it('createSubscription calls POST /v1/subscriptions and returns SubscriptionResponse', function () {
        Http::fake([
            '*/v1/subscriptions' => Http::response(fixturePayload('subscription-active'), 201),
        ]);

        $manager = app(PayIdManager::class);
        $response = $manager->createSubscription(SubscriptionRequest::make([
            'subscription_id' => 'SUB-MERCHANT-001',
            'name' => 'Langganan Bulanan',
            'amount' => 150000,
            'token' => '481111xNpbentpC2115N1114',
            'interval' => 'month',
            'interval_count' => 1,
            'max_cycle' => 12,
            'customer' => ['name' => 'Budi', 'email' => 'budi@example.com'],
        ]));

        expect($response->providerName)->toBe('midtrans');
        expect($response->providerSubscriptionId)->toBe('SUB-MIDTRANS-001');
        expect($response->subscriptionId)->toBe('SUB-MERCHANT-001');
        expect($response->status)->toBe(SubscriptionStatus::Active);
        expect($response->amount)->toBe(150000);
        expect($response->interval)->toBe(SubscriptionInterval::Month);
        expect($response->maxCycle)->toBe(12);
        expect($response->nextChargeAt)->not->toBeNull();
    });

    it('createSubscription sends correct payload', function () {
        Http::fake([
            '*/v1/subscriptions' => Http::response(fixturePayload('subscription-active'), 201),
        ]);

        app(PayIdManager::class)->createSubscription(SubscriptionRequest::make([
            'subscription_id' => 'SUB-MERCHANT-001',
            'name' => 'Langganan Bulanan',
            'amount' => 150000,
            'token' => 'TOKEN-123',
            'interval' => 'month',
        ]));

        Http::assertSent(function ($req) {
            $body = $req->data();

            return str_contains($req->url(), 'subscriptions')
                && $body['name'] === 'Langganan Bulanan'
                && $body['amount'] === '150000'
                && $body['token'] === 'TOKEN-123'
                && $body['schedule']['interval_unit'] === 'month';
        });
    });

    it('createSubscription normalizes yearly interval to month x12', function () {
        Http::fake([
            '*/v1/subscriptions' => Http::response(fixturePayload('subscription-active'), 201),
        ]);

        app(PayIdManager::class)->createSubscription(SubscriptionRequest::make([
            'subscription_id' => 'SUB-YEAR-001',
            'name' => 'Tahunan',
            'amount' => 300000,
            'token' => 'TOKEN-YEAR-1',
            'interval' => SubscriptionInterval::Year,
            'interval_count' => 2,
        ]));

        Http::assertSent(function ($req) {
            $body = $req->data();

            return str_contains($req->url(), 'subscriptions')
                && $body['schedule']['interval_unit'] === 'month'
                && $body['schedule']['interval'] === 24;
        });
    });

    it('getSubscription calls GET /v1/subscriptions/{id}', function () {
        Http::fake([
            '*/v1/subscriptions/SUB-MIDTRANS-001' => Http::response(fixturePayload('subscription-active'), 200),
        ]);

        $manager = app(PayIdManager::class);
        $response = $manager->getSubscription('SUB-MIDTRANS-001');

        expect($response->providerSubscriptionId)->toBe('SUB-MIDTRANS-001');
        expect($response->status)->toBe(SubscriptionStatus::Active);

        Http::assertSent(fn ($req) => str_contains($req->url(), 'subscriptions/SUB-MIDTRANS-001')
            && $req->method() === 'GET');
    });

    it('updateSubscription calls PATCH /v1/subscriptions/{id}', function () {
        Http::fake([
            '*/v1/subscriptions/SUB-MIDTRANS-001' => Http::sequence()
                ->push(['status_message' => 'Subscription is updated.'], 200)
                ->push(fixturePayload('subscription-active'), 200),
        ]);

        app(PayIdManager::class)->updateSubscription(
            UpdateSubscriptionRequest::make([
                'provider_subscription_id' => 'SUB-MIDTRANS-001',
                'amount' => 200000,
            ]),
        );

        Http::assertSent(function ($req) {
            return str_contains($req->url(), 'subscriptions/SUB-MIDTRANS-001')
                && $req->method() === 'PATCH'
                && $req->data()['amount'] === '200000';
        });
    });

    it('updateSubscription normalizes yearly interval to month x12', function () {
        Http::fake([
            '*/v1/subscriptions/SUB-MIDTRANS-001' => Http::sequence()
                ->push(['status_message' => 'Subscription is updated.'], 200)
                ->push(fixturePayload('subscription-active'), 200),
        ]);

        app(PayIdManager::class)->updateSubscription(
            UpdateSubscriptionRequest::make([
                'provider_subscription_id' => 'SUB-MIDTRANS-001',
                'interval' => SubscriptionInterval::Year,
                'interval_count' => 1,
            ]),
        );

        Http::assertSent(function ($req) {
            $body = $req->data();

            return str_contains($req->url(), 'subscriptions/SUB-MIDTRANS-001')
                && $req->method() === 'PATCH'
                && $body['schedule']['interval_unit'] === 'month'
                && $body['schedule']['interval'] === 12;
        });
    });

    it('pauseSubscription calls POST /disable and returns inactive status', function () {
        Http::fake([
            '*/v1/subscriptions/SUB-MIDTRANS-001/disable' => Http::response(['status_message' => 'Subscription is updated.'], 200),
            '*/v1/subscriptions/SUB-MIDTRANS-001' => Http::response(fixturePayload('subscription-inactive'), 200),
        ]);

        $manager = app(PayIdManager::class);
        $response = $manager->pauseSubscription('SUB-MIDTRANS-001');

        expect($response->status)->toBe(SubscriptionStatus::Inactive);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'disable'));
    });

    it('resumeSubscription calls POST /enable and returns active status', function () {
        Http::fake([
            '*/v1/subscriptions/SUB-MIDTRANS-001/enable' => Http::response(['status_message' => 'Subscription is updated.'], 200),
            '*/v1/subscriptions/SUB-MIDTRANS-001' => Http::response(fixturePayload('subscription-active'), 200),
        ]);

        $manager = app(PayIdManager::class);
        $response = $manager->resumeSubscription('SUB-MIDTRANS-001');

        expect($response->status)->toBe(SubscriptionStatus::Active);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'enable'));
    });

    it('cancelSubscription calls POST /cancel and returns inactive status', function () {
        Http::fake([
            '*/v1/subscriptions/SUB-MIDTRANS-001/cancel' => Http::response(['status_message' => 'Subscription is updated.'], 200),
            '*/v1/subscriptions/SUB-MIDTRANS-001' => Http::response(fixturePayload('subscription-cancelled'), 200),
        ]);

        $manager = app(PayIdManager::class);
        $response = $manager->cancelSubscription('SUB-MIDTRANS-001');

        expect($response->providerSubscriptionId)->toBe('SUB-MIDTRANS-001');
        expect($response->status)->toBe(SubscriptionStatus::Inactive);

        Http::assertSent(fn ($req) => str_contains($req->url(), 'subscriptions/SUB-MIDTRANS-001/cancel')
            && $req->method() === 'POST');
    });

    it('declares CancelSubscription capability', function () {
        $driver = app(PayIdManager::class)->getDriver();
        expect($driver->supports(Capability::CancelSubscription))->toBeTrue();
    });

});
