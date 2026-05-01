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
        if ($import->status !== 'draft') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Import can be committed only once.',
                ], 422);
            }

            return to_route('transactions.index')->withErrors(['import' => 'Import can be committed only once.']);
        }

        $validated = $request->validated();

        $import->mapping = $validated['mapping'];
        $import->status = 'queued';
        $import->save();

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
