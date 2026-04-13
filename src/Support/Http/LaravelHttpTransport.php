<?php

namespace Aliziodev\PayIdMidtrans\Support\Http;

use Aliziodev\MidtransPhp\Exceptions\MidtransException;
use Aliziodev\MidtransPhp\Http\HttpResponse;
use Aliziodev\MidtransPhp\Http\Transport;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class LaravelHttpTransport implements Transport
{
    /**
     * @param  array<string, string>  $headers
     */
    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $jsonBody,
        int $timeoutSeconds,
        int $maxRetries = 0,
        int $retryDelayMs = 0,
    ): HttpResponse {
        try {
            $pending = Http::timeout($timeoutSeconds)
                ->retry($maxRetries, $retryDelayMs)
                ->withHeaders($headers);

            $options = [];
            if ($jsonBody !== null) {
                $decoded = json_decode($jsonBody, true);
                if (! is_array($decoded)) {
                    throw MidtransException::invalidResponse('Unable to decode JSON payload for HTTP transport.');
                }

                $options['json'] = $decoded;
            }

            $response = $pending->send(strtoupper($method), $url, $options);

            return new HttpResponse(
                statusCode: $response->status(),
                body: (string) $response->body(),
            );
        } catch (ConnectionException $e) {
            throw MidtransException::transportError($e->getMessage());
        } catch (Throwable $e) {
            throw MidtransException::transportError($e->getMessage());
        }
    }
}
