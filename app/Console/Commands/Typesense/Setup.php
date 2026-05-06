<?php

declare(strict_types=1);

namespace App\Console\Commands\Typesense;

use App\Support\Typesense\TypesenseClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Throwable;

#[Signature('typesense:setup {collection=import_description_memory : Typesense collection name}')]
#[Description('Ensure Typesense collections exist (bootstrap)')]
final class Setup extends Command
{
    public function handle(): int
    {
        $enabled = (bool) config('services.typesense.enabled');
        $apiKey = (string) config('services.typesense.api_key');
        $protocol = (string) config('services.typesense.protocol');
        $host = (string) config('services.typesense.host');
        $port = (int) config('services.typesense.port');

        $collection = trim((string) $this->argument('collection'));
        if ($collection === '') {
            $this->error('Collection name cannot be empty.');

            return self::FAILURE;
        }

        if (! $enabled) {
            $this->info('Typesense is disabled (services.typesense.enabled=false). Nothing to do.');

            return self::SUCCESS;
        }

        if ($apiKey === '') {
            $this->error('Typesense API key is missing (services.typesense.api_key is empty).');

            return self::FAILURE;
        }

        try {
            /** @var TypesenseClient $client */
            $client = app(TypesenseClient::class);
            $client->health();
        } catch (Throwable $e) {
            $this->error('Typesense health check failed.');
            $this->line("Endpoint: {$protocol}://{$host}:{$port}");
            $this->line('Exception: '.$e::class);
            $this->line('Message: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            $client->getCollection($collection);
            $this->info('Typesense collection exists: '.$collection);

            return self::SUCCESS;
        } catch (RequestException $e) {
            if ($e->response->status() !== 404) {
                $this->error('Typesense request failed while checking collection.');
                $this->line('Collection: '.$collection);
                $this->line('Exception: '.$e::class);
                $this->line('Message: '.$e->getMessage());

                return self::FAILURE;
            }
        } catch (Throwable $e) {
            $this->error('Typesense request failed while checking collection.');
            $this->line('Collection: '.$collection);
            $this->line('Exception: '.$e::class);
            $this->line('Message: '.$e->getMessage());

            return self::FAILURE;
        }

        $schema = [
            'name' => $collection,
            'fields' => [
                ['name' => 'user_id', 'type' => 'int32', 'facet' => true],
                ['name' => 'bank', 'type' => 'string', 'facet' => true],
                ['name' => 'raw_key', 'type' => 'string'],
                ['name' => 'learned_subject', 'type' => 'string', 'optional' => true, 'index' => false],
                ['name' => 'learned_description', 'type' => 'string', 'index' => false],
                ['name' => 'updated_at', 'type' => 'int64'],
            ],
            'default_sorting_field' => 'updated_at',
        ];

        try {
            $client->createCollection($schema);
            $this->info('Typesense collection created: '.$collection);
        } catch (Throwable $e) {
            $this->error('Typesense request failed while creating collection.');
            $this->line('Collection: '.$collection);
            $this->line('Exception: '.$e::class);
            $this->line('Message: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
