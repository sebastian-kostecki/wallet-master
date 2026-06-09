<?php

declare(strict_types=1);

namespace App\Integrations\DescriptionMemory;

use App\Enums\Bank;
use App\Integrations\Typesense\TypesenseClient;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class TypesenseDescriptionMemoryRepository implements DescriptionMemoryRepository
{
    private const string COLLECTION = 'import_description_memory';

    public function __construct(
        private TypesenseClient $client,
        private int $minTextMatch,
        private int $numTypos,
    ) {}

    public function remember(
        int $userId,
        Bank $bank,
        string $rawStatementDescription,
        ?string $subject,
        string $description,
        ?int $categoryId = null,
    ): void {
        if ($bank === Bank::Cash) {
            return;
        }

        if (trim($description) === '' && $categoryId === null) {
            return;
        }

        $rawKey = RawStatementDescriptionNormalizer::normalizeStrict($rawStatementDescription);
        if ($rawKey === '') {
            return;
        }

        $id = hash('sha256', "{$userId}|{$bank->value}|{$rawKey}");

        try {
            $document = [
                'id' => $id,
                'user_id' => $userId,
                'bank' => $bank->value,
                'raw_key' => $rawKey,
                'learned_subject' => $subject,
                'learned_description' => $description,
                'updated_at' => now()->timestamp,
            ];

            if ($categoryId !== null) {
                $document['learned_category_id'] = $categoryId;
            }

            $this->client->upsertDocument(self::COLLECTION, $document);
        } catch (Throwable $e) {
            Log::warning('Typesense description memory remember failed.', [
                'bank' => $bank->value,
                'exception_class' => $e::class,
            ]);
        }
    }

    public function suggest(
        int $userId,
        Bank $bank,
        string $rawStatementDescription,
    ): ?SuggestedFields {
        if ($bank === Bank::Cash) {
            return null;
        }

        $rawKey = RawStatementDescriptionNormalizer::normalizeStrict($rawStatementDescription);
        if ($rawKey === '') {
            return null;
        }

        try {
            /** @var array<string, mixed> $response */
            $response = $this->client->search(self::COLLECTION, [
                'q' => $rawKey,
                'query_by' => 'raw_key',
                'num_typos' => $this->numTypos,
                'per_page' => 1,
                'filter_by' => "user_id:={$userId} && bank:=\"{$bank->value}\"",
            ]);
        } catch (Throwable $e) {
            Log::warning('Typesense description memory suggest failed.', [
                'bank' => $bank->value,
                'exception_class' => $e::class,
            ]);

            return null;
        }

        $hits = $response['hits'] ?? null;
        if (! is_array($hits) || $hits === []) {
            return null;
        }

        $firstHit = $hits[0] ?? null;
        if (! is_array($firstHit)) {
            return null;
        }

        $score = (int) ($firstHit['text_match'] ?? 0);
        if ($score < $this->minTextMatch) {
            return null;
        }

        $document = $firstHit['document'] ?? null;
        if (! is_array($document)) {
            return null;
        }

        $suggestedSubject = isset($document['learned_subject']) ? (string) $document['learned_subject'] : null;
        $suggestedSubject = $suggestedSubject !== null && trim($suggestedSubject) !== '' ? $suggestedSubject : null;

        $suggestedDescription = isset($document['learned_description']) ? (string) $document['learned_description'] : null;
        $suggestedDescription = $suggestedDescription !== null && trim($suggestedDescription) !== '' ? $suggestedDescription : null;

        $categoryId = isset($document['learned_category_id']) ? (int) $document['learned_category_id'] : null;

        if ($suggestedSubject === null && $suggestedDescription === null && $categoryId === null) {
            return null;
        }

        return new SuggestedFields(
            subject: $suggestedSubject,
            description: $suggestedDescription,
            matchType: 'fuzzy',
            score: $score,
            categoryId: $categoryId,
        );
    }
}
