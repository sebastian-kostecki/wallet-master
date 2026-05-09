<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Imports\StoreImportCommitRequest;
use App\Imports\Workflow\QueueImportCommit;
use App\Imports\Workflow\QueueImportCommitStatus;
use App\Models\Import;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class ImportCommitController extends Controller
{
    public function store(StoreImportCommitRequest $request, Import $import, QueueImportCommit $queueImportCommit): RedirectResponse|JsonResponse
    {
        $result = $queueImportCommit->execute($import, $request->user(), $request->validated());

        if ($result->status === QueueImportCommitStatus::MissingMapping) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Import is missing a column mapping.',
                    'code' => 'missing_mapping',
                ], 422);
            }

            return to_route('transactions.index')->withErrors(['import' => 'Import is missing a column mapping.']);
        }

        if ($result->status === QueueImportCommitStatus::NotDraft) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Import can be committed only once.',
                ], 422);
            }

            return to_route('transactions.index')->withErrors(['import' => 'Import can be committed only once.']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'import_id' => $result->import->id,
                'status' => $result->import->status,
            ], 202);
        }

        return to_route('transactions.index')->with('toast', [
            'type' => 'info',
            'message' => 'Import queued.',
        ]);
    }
}
