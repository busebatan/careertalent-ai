<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CareerTalentApiClient
{
    public function baseUrl(): string
    {
        return rtrim(config('services.careertalent.api_url'), '/');
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function health(): array
    {
        try {
            $response = Http::timeout(3)->get($this->baseUrl().'/health');

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'body' => $response->json(),
                'error' => null,
            ];
        } catch (ConnectionException $exception) {
            return [
                'ok' => false,
                'status' => null,
                'body' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }
}
