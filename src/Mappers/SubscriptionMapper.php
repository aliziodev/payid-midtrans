<?php

namespace Aliziodev\PayIdMidtrans\Mappers;

use Aliziodev\PayId\DTO\SubscriptionRequest;
use Aliziodev\PayId\DTO\SubscriptionResponse;
use Aliziodev\PayId\DTO\UpdateSubscriptionRequest;
use Aliziodev\PayId\Enums\SubscriptionInterval;
use Aliziodev\PayId\Enums\SubscriptionStatus;
use Aliziodev\PayId\Exceptions\ProviderResponseException;
use Carbon\Carbon;

class SubscriptionMapper
{
    /**
     * Build payload untuk POST /v1/subscriptions.
     *
     * Midtrans Subscription API menggunakan token dari saved card (credit_card save_token_id = true).
     * Interval: daily | weekly | monthly.
     *
     * @return array<string, mixed>
     */
    public function toCreatePayload(SubscriptionRequest $request): array
    {
        $schedule = $this->mapIntervalAndCount(
            $request->interval,
            $request->intervalCount,
        );

        $payload = [
            'name' => $request->name,
            'amount' => (string) $request->amount,
            'currency' => $request->currency,
            'token' => $request->token,
            'schedule' => [
                'interval' => $schedule['interval'],
                'interval_unit' => $schedule['interval_unit'],
                'max_interval' => $request->maxCycle,
                'start_time' => $request->startTime
                    ? $request->startTime->format('Y-m-d H:i:s +0700')
                    : now()->addMinute()->format('Y-m-d H:i:s +0700'),
            ],
            'metadata' => array_merge(
                ['subscription_id' => $request->subscriptionId],
                $request->metadata,
            ),
            'customer_details' => $request->customer ? [
                'first_name' => $request->customer->name,
                'email' => $request->customer->email,
                'phone' => $request->customer->phone,
            ] : [],
        ];

        // hapus null dari schedule
        $payload['schedule'] = array_filter($payload['schedule'], fn ($v) => $v !== null);

        return $payload;
    }

    /**
     * Build payload untuk PATCH /v1/subscriptions/{id}.
     *
     * @return array<string, mixed>
     */
    public function toUpdatePayload(UpdateSubscriptionRequest $request): array
    {
        $schedule = null;

        if ($request->interval !== null) {
            $mapped = $this->mapIntervalAndCount(
                $request->interval,
                $request->intervalCount ?? 1,
            );

            $schedule = [
                'interval' => $mapped['interval'],
                'interval_unit' => $mapped['interval_unit'],
                'max_interval' => $request->maxCycle,
            ];
        } elseif ($request->intervalCount !== null || $request->maxCycle !== null) {
            $schedule = [
                'interval' => $request->intervalCount,
                'max_interval' => $request->maxCycle,
            ];
        }

        return array_filter([
            'name' => $request->name,
            'amount' => $request->amount !== null ? (string) $request->amount : null,
            'token' => $request->token,
            'schedule' => $schedule !== null
                ? array_filter($schedule, fn ($v) => $v !== null)
                : null,
        ], fn ($v) => $v !== null && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $raw
     *
     * @throws ProviderResponseException
     */
    public function toSubscriptionResponse(array $raw, ?string $merchantSubscriptionId = null): SubscriptionResponse
    {
        if (empty($raw['id'])) {
            throw new ProviderResponseException(
                'midtrans',
                'Subscription response missing id.',
            );
        }

        $status = match ($raw['status'] ?? '') {
            'active' => SubscriptionStatus::Active,
            'inactive' => SubscriptionStatus::Inactive,
            'completed' => SubscriptionStatus::Completed,
            default => SubscriptionStatus::Inactive,
        };

        $schedule = $raw['schedule'] ?? [];
        $interval = SubscriptionInterval::tryFrom(
            $this->reverseMapInterval($schedule['interval_unit'] ?? 'month')
        ) ?? SubscriptionInterval::Month;

        // merchant subscription_id disimpan di metadata
        $subscriptionId = $merchantSubscriptionId
            ?? $raw['metadata']['subscription_id']
            ?? $raw['id'];

        return new SubscriptionResponse(
            providerName: 'midtrans',
            providerSubscriptionId: $raw['id'],
            subscriptionId: $subscriptionId,
            name: $raw['name'] ?? '',
            status: $status,
            amount: (int) ($raw['amount'] ?? 0),
            currency: $raw['currency'] ?? 'IDR',
            interval: $interval,
            intervalCount: (int) ($schedule['interval'] ?? 1),
            rawResponse: $raw,
            currentCycle: $schedule['current_interval'] ?? null,
            maxCycle: $schedule['max_interval'] ?? null,
            startTime: isset($schedule['start_time'])
                ? Carbon::parse($schedule['start_time'])
                : null,
            nextChargeAt: isset($schedule['next_execution_at'])
                ? Carbon::parse($schedule['next_execution_at'])
                : null,
            createdAt: isset($raw['created_at'])
                ? Carbon::parse($raw['created_at'])
                : null,
        );
    }

    /**
     * Midtrans does not support yearly interval natively.
     * We convert year to month and multiply the interval count by 12.
     *
     * @return array{interval_unit: string, interval: int}
     */
    private function mapIntervalAndCount(SubscriptionInterval $interval, int $count): array
    {
        return match ($interval) {
            SubscriptionInterval::Day => [
                'interval_unit' => 'day',
                'interval' => $count,
            ],
            SubscriptionInterval::Week => [
                'interval_unit' => 'week',
                'interval' => $count,
            ],
            SubscriptionInterval::Month => [
                'interval_unit' => 'month',
                'interval' => $count,
            ],
            SubscriptionInterval::Year => [
                'interval_unit' => 'month',
                'interval' => $count * 12,
            ],
        };
    }

    private function reverseMapInterval(string $unit): string
    {
        return match ($unit) {
            'day' => 'day',
            'week' => 'week',
            default => 'month',
        };
    }
}
