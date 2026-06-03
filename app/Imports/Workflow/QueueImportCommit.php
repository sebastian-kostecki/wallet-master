<?php

declare(strict_types=1);

namespace App\Imports\Workflow;

use App\Enums\ImportStatus;
use App\Jobs\CommitImportJob;
use App\Models\Import;
use App\Models\User;

final class QueueImportCommit
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Import $import, User $user, array $validated): QueueImportCommitResult
    {
        /** @var array<string, mixed>|null $mappingToPersist */
        $mappingToPersist = $validated['mapping'] ?? $import->mapping;

        if ($mappingToPersist === null || $mappingToPersist === []) {
            return new QueueImportCommitResult(QueueImportCommitStatus::MissingMapping);
        }

        $affected = Import::query()
            ->whereKey($import->id)
            ->where('user_id', $user->id)
            ->where('status', ImportStatus::Draft->value)
            ->update([
                'status' => ImportStatus::Queued->value,
                'mapping' => json_encode($mappingToPersist, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            return new QueueImportCommitResult(QueueImportCommitStatus::NotDraft);
        }

        $import->refresh();

        CommitImportJob::dispatch($import->id);

        return new QueueImportCommitResult(QueueImportCommitStatus::Queued, $import);
    }
}
