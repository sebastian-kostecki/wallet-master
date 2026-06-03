<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\Bank;
use App\Integrations\DescriptionMemory\DescriptionMemoryRepository;
use App\Integrations\DescriptionMemory\SuggestedFields;

final class FakeDescriptionMemoryRepository implements DescriptionMemoryRepository
{
    /** @var list<array{user_id:int, bank:Bank, raw:string, subject:?string, description:string}> */
    public array $rememberCalls = [];

    /** @var array<string, SuggestedFields> */
    private array $suggestionsByKey = [];

    public function remember(
        int $userId,
        Bank $bank,
        string $rawStatementDescription,
        ?string $subject,
        string $description,
        ?int $categoryId = null,
    ): void {
        $this->rememberCalls[] = [
            'user_id' => $userId,
            'bank' => $bank,
            'raw' => $rawStatementDescription,
            'subject' => $subject,
            'description' => $description,
            'category_id' => $categoryId,
        ];
    }

    public function suggest(int $userId, Bank $bank, string $rawStatementDescription): ?SuggestedFields
    {
        return $this->suggestionsByKey[$this->key($userId, $bank, $rawStatementDescription)] ?? null;
    }

    public function setSuggestion(int $userId, Bank $bank, string $rawStatementDescription, SuggestedFields $suggestedFields): void
    {
        $this->suggestionsByKey[$this->key($userId, $bank, $rawStatementDescription)] = $suggestedFields;
    }

    private function key(int $userId, Bank $bank, string $rawStatementDescription): string
    {
        return "{$userId}|{$bank->value}|{$rawStatementDescription}";
    }
}
