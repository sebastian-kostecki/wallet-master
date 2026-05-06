<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Imports\StoreImportCommitRequest;
use App\Jobs\CommitImportJob;
use App\Models\Import;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class ImportCommitController extends Controller
{
    public function store(StoreImportCommitRequest $request, Import $import): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        /** @var array<string, mixed>|null $mappingToPersist */
        $mappingToPersist = $validated['mapping'] ?? $import->mapping;

        if ($mappingToPersist === null || $mappingToPersist === []) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Import is missing a column mapping.',
                    'code' => 'missing_mapping',
                ], 422);
            }

            return to_route('transactions.index')->withErrors(['import' => 'Import is missing a column mapping.']);
        }

        $affected = Import::query()
            ->whereKey($import->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'draft')
            ->update([
                'status' => 'queued',
                'mapping' => json_encode($mappingToPersist, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Import can be committed only once.',
                ], 422);
            }

            return to_route('transactions.index')->withErrors(['import' => 'Import can be committed only once.']);
        }

        $import->refresh();

        CommitImportJob::dispatch($import->id);

        if ($request->expectsJson()) {
            return response()->json([
                'import_id' => $import->id,
                'status' => $import->status,
            ], 202);
        }

        return to_route('transactions.index')->with('toast', [
            'type' => 'info',
            'message' => 'Import queued.',
        ]);
    }
}
