<?php

namespace App\Payments;

use App\Payments\Exceptions\IdempotencyKeyReused;
use App\Payments\Exceptions\PaymentGatewayException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class HttpPaymentGateway implements PaymentGateway
{
    public function __construct(
        private string $baseUrl,
        private ?string $secret,
    ) {}

    public function charge(int $amountCents, string $token, string $idempotencyKey): GatewayCharge
    {
        $response = $this->send($idempotencyKey, '/v1/charges', [
            'amount' => $amountCents,
            'source' => $token,
        ]);

        return GatewayCharge::fromArray($response->json());
    }

    public function refund(string $chargeId, int $amountCents, string $idempotencyKey): GatewayRefund
    {
        $response = $this->send($idempotencyKey, '/v1/refunds', [
            'charge' => $chargeId,
            'amount' => $amountCents,
        ]);

        return GatewayRefund::fromArray($response->json());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $idempotencyKey, string $path, array $payload): Response
    {
        try {
            return $this->client()
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post($this->baseUrl.$path, $payload)
                ->throw();
        } catch (RequestException $e) {
            if ($e->response->json('error.type') === 'idempotency_error') {
                throw new IdempotencyKeyReused($idempotencyKey);
            }

            throw new PaymentGatewayException($e->getMessage(), previous: $e);
        }
    }

    private function client(): PendingRequest
    {
        if (blank($this->secret)) {
            throw new PaymentGatewayException('Missing PAYMENTS_SECRET.');
        }

        return Http::withToken($this->secret)->acceptJson()->timeout(10);
    }
}
