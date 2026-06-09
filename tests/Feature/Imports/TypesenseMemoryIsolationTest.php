<?php

declare(strict_types=1);

use App\Enums\Bank;
use App\Imports\Workflow\EnrichImportRowDescription;
use App\Integrations\DescriptionMemory\DescriptionMemoryRepository;
use App\Integrations\DescriptionMemory\SuggestedFields;
use App\Models\Import;
use Tests\Support\FakeDescriptionMemoryRepository;

test('description memory suggestions are isolated per user during import enrichment', function () {
    $fakeRepo = new FakeDescriptionMemoryRepository;
    app()->instance(DescriptionMemoryRepository::class, $fakeRepo);

    $fakeRepo->setSuggestion(
        userId: 1,
        bank: Bank::MBank,
        rawStatementDescription: 'SHOP XYZ 01.04',
        suggestedFields: new SuggestedFields(
            subject: 'Shop XYZ',
            description: 'Groceries',
            matchType: 'exact',
            score: 100,
        ),
    );

    $ownerImport = Import::query()->make([
        'user_id' => 1,
    ]);
    $ownerImport->id = 10;

    $otherImport = Import::query()->make([
        'user_id' => 2,
    ]);
    $otherImport->id = 11;

    $enricher = app(EnrichImportRowDescription::class);

    $ownerResult = $enricher->enrich(
        import: $ownerImport,
        bank: Bank::MBank,
        rawStatementDescription: 'SHOP XYZ 01.04',
        description: 'SHOP XYZ 01.04',
        subject: null,
    );

    $otherResult = $enricher->enrich(
        import: $otherImport,
        bank: Bank::MBank,
        rawStatementDescription: 'SHOP XYZ 01.04',
        description: 'SHOP XYZ 01.04',
        subject: null,
    );

    expect($ownerResult)->toBe([
        'description' => 'Groceries',
        'subject' => 'Shop XYZ',
    ]);

    expect($otherResult)->toBe([
        'description' => 'SHOP XYZ 01.04',
        'subject' => null,
    ]);
});
