<?php

namespace Aliziodev\PayIdMidtrans\Tests;

use Aliziodev\PayId\Laravel\PayIdServiceProvider;
use Aliziodev\PayId\Managers\PayIdManager;
use Aliziodev\PayIdMidtrans\MidtransServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->applyMidtransTestConfig();
    }

    protected function getPackageProviders($app): array
    {
        return [
            PayIdServiceProvider::class,
            MidtransServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $this->applyMidtransTestEnv();

        $app['config']->set('payid.default', 'midtrans');

        $app['config']->set('payid.drivers.midtrans', [
            'driver' => 'midtrans',
            'environment' => 'sandbox',
            'server_key' => 'SB-Mid-server-test-key-1234567890',
            'client_key' => 'SB-Mid-client-test-key-1234567890',
            'merchant_id' => 'G141532850',
        ]);
    }

    private function applyMidtransTestConfig(): void
    {
        $this->applyMidtransTestEnv();

        $this->app['config']->set('payid.default', 'midtrans');

        $this->app['config']->set('payid.drivers.midtrans', [
            'driver' => 'midtrans',
            'environment' => 'sandbox',
            'server_key' => 'SB-Mid-server-test-key-1234567890',
            'client_key' => 'SB-Mid-client-test-key-1234567890',
            'merchant_id' => 'G141532850',
        ]);

        /** @var PayIdManager $manager */
        $manager = $this->app->make(PayIdManager::class);
        $manager->resolveCredentialsUsing(static fn (string $driver): array => $driver === 'midtrans'
            ? [
                'environment' => 'sandbox',
                'server_key' => 'SB-Mid-server-test-key-1234567890',
                'client_key' => 'SB-Mid-client-test-key-1234567890',
                'merchant_id' => 'G141532850',
            ]
            : []);
    }

    private function applyMidtransTestEnv(): void
    {
        putenv('MIDTRANS_ENV=sandbox');
        putenv('MIDTRANS_SERVER_KEY=SB-Mid-server-test-key-1234567890');
        putenv('MIDTRANS_CLIENT_KEY=SB-Mid-client-test-key-1234567890');
        putenv('MIDTRANS_MERCHANT_ID=G141532850');

        $_ENV['MIDTRANS_ENV'] = 'sandbox';
        $_ENV['MIDTRANS_SERVER_KEY'] = 'SB-Mid-server-test-key-1234567890';
        $_ENV['MIDTRANS_CLIENT_KEY'] = 'SB-Mid-client-test-key-1234567890';
        $_ENV['MIDTRANS_MERCHANT_ID'] = 'G141532850';

        $_SERVER['MIDTRANS_ENV'] = 'sandbox';
        $_SERVER['MIDTRANS_SERVER_KEY'] = 'SB-Mid-server-test-key-1234567890';
        $_SERVER['MIDTRANS_CLIENT_KEY'] = 'SB-Mid-client-test-key-1234567890';
        $_SERVER['MIDTRANS_MERCHANT_ID'] = 'G141532850';
    }

    /**
     * @return array<string, mixed>
     */
    protected function fixture(string $name): array
    {
        $path = __DIR__.'/Fixtures/'.$name.'.json';

        return json_decode(file_get_contents($path), true);
    }

    protected function fixtureContent(string $name): string
    {
        return file_get_contents(__DIR__.'/Fixtures/'.$name.'.json');
    }
}
