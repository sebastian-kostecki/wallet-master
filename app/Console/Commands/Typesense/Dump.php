<?php

declare(strict_types=1);

namespace App\Console\Commands\Typesense;

use App\Support\Typesense\TypesenseClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

#[Signature('typesense:dump {userId : User ID} {--bank= : Optional bank value} {--page=1} {--per-page=50}')]
#[Description('Dump Typesense import description memory for a user')]
final class Dump extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $enabled = (bool) config('services.typesense.enabled');
        $apiKey = (string) config('services.typesense.api_key');
        $protocol = (string) config('services.typesense.protocol');
        $host = (string) config('services.typesense.host');
        $port = (int) config('services.typesense.port');

        $userId = (int) $this->argument('userId');

        if (! $enabled) {
            $this->error('Typesense is disabled (services.typesense.enabled=false).');

            return self::FAILURE;
        }

        if ($apiKey === '') {
            $this->error('Typesense API key is missing (services.typesense.api_key is empty).');

            return self::FAILURE;
        }

        $bank = $this->option('bank');
        $page = (int) $this->option('page');
        $perPage = (int) $this->option('per-page');

        $page = max(1, $page);
        $perPage = max(1, min(250, $perPage));

        $filterBy = "user_id:={$userId}";
        if (is_string($bank) && trim($bank) !== '') {
            $filterBy .= ' && bank:="'.str_replace('"', '\"', trim($bank)).'"';
        }

        $collection = 'import_description_memory';

        try {
            $client = app(TypesenseClient::class);

            $response = $client->search($collection, [
                'q' => '*',
                'query_by' => 'raw_key',
                'page' => $page,
                'per_page' => $perPage,
                'filter_by' => $filterBy,
            ]);
        } catch (Throwable $e) {
            $this->error('Typesense request failed.');
            $this->line("Endpoint: {$protocol}://{$host}:{$port}");
            $this->line('Exception: '.$e::class);
            $this->line('Message: '.$e->getMessage());

            return self::FAILURE;
        }

        $found = (int) ($response['found'] ?? 0);
        $hits = is_array($response['hits'] ?? null) ? $response['hits'] : [];

        $rows = collect($hits)
            ->map(fn ($hit) => is_array($hit) ? ($hit['document'] ?? null) : null)
            ->filter(fn ($doc) => is_array($doc))
            ->map(function (array $doc) {
                return [
                    'id' => (string) ($doc['id'] ?? ''),
                    'bank' => (string) ($doc['bank'] ?? ''),
                    'raw_key' => Str::limit((string) ($doc['raw_key'] ?? ''), 80),
                    'learned_subject' => Str::limit((string) ($doc['learned_subject'] ?? ''), 60),
                    'learned_description' => Str::limit((string) ($doc['learned_description'] ?? ''), 80),
                    'updated_at' => (string) ($doc['updated_at'] ?? ''),
                ];
            })
            ->values()
            ->all();

        $this->info("Typesense {$collection}: found={$found} page={$page} perPage={$perPage}");
        $this->table(
            ['id', 'bank', 'raw_key', 'learned_subject', 'learned_description', 'updated_at'],
            $rows,
        );

        return self::SUCCESS;
    }
}
