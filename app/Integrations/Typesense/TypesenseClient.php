<?php

declare(strict_types=1);

namespace App\Integrations\Typesense;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final readonly class TypesenseClient
{
    public function __construct(
        public string $protocol,
        public string $host,
        public int $port,
        public string $apiKey,
        public int $timeoutMs,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        return $this->request()
            ->get('/health')
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function getCollection(string $name): array
    {
        return $this->request()
            ->get("/collections/{$name}")
            ->throw()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function createCollection(array $schema): array
    {
        return $this->request()
            ->post('/collections', $schema)
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteCollection(string $name): array
    {
        return $this->request()
            ->delete("/collections/{$name}")
            ->throw()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function search(string $collection, array $payload): array
    {
        return $this->request()
            ->get("/collections/{$collection}/documents/search", $payload)
            ->throw()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function upsertDocument(string $collection, array $document): array
    {
        return $this->request()
            ->post("/collections/{$collection}/documents?action=upsert", $document)
            ->throw()
            ->json();
    }

    private function request(): PendingRequest
    {
        $timeoutSeconds = max(0.1, $this->timeoutMs / 1000);

        return Http::baseUrl("{$this->protocol}://{$this->host}:{$this->port}")
            ->withHeaders([
                'X-TYPESENSE-API-KEY' => $this->apiKey,
                'Accept' => 'application/json',
            ])
            ->timeout($timeoutSeconds)
            ->connectTimeout($timeoutSeconds)
            ->retry(1, 100);
    }
}
