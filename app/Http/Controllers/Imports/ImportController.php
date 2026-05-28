<?php

declare(strict_types=1);

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Imports\StoreImportCommitRequest;
use App\Http\Requests\Imports\StoreImportUploadRequest;
use App\Imports\Workflow\PrepareImportUpload;
use App\Imports\Workflow\QueueImportCommit;
use App\Imports\Workflow\QueueImportCommitStatus;
use App\Models\Account;
use App\Models\Import;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ImportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $imports = Import::query()
            ->whereBelongsTo($request->user())
            ->latest()
            ->limit(30)
            ->get([
                'id',
                'account_id',
                'status',
                'rows_total',
                'rows_imported',
                'rows_skipped_duplicate',
                'rows_failed_validation',
                'error_summary',
                'created_at',
                'committed_at',
            ]);

        return response()->json([
            'data' => $imports,
        ]);
    }

    public function show(Request $request, Import $import): JsonResponse
    {
        $this->authorize('view', $import);

        return response()->json($this->importDetailPayload($import));
    }

    /**
     * @return array<string, mixed>
     */
    private function importDetailPayload(Import $import): array
    {
        return [
            'id' => $import->id,
            'status' => $import->status,
            'rows_total' => $import->rows_total,
            'rows_imported' => $import->rows_imported,
            'rows_skipped_duplicate' => $import->rows_skipped_duplicate,
            'rows_failed_validation' => $import->rows_failed_validation,
            'error_summary' => $import->error_summary,
            'committed_at' => $import->committed_at,
        ];
    }

    public function upload(StoreImportUploadRequest $request, PrepareImportUpload $prepareImportUpload): JsonResponse
    {
        $validated = $request->validated();

        $account = Account::query()
            ->whereBelongsTo($request->user())
            ->whereKey($validated['account_id'])
            ->firstOrFail();

        $result = $prepareImportUpload->execute($account, $request->user(), $request->file('file'));

        if (! $result->success) {
            return response()->json([
                'message' => $result->message,
                'code' => $result->code,
                'errors' => [
                    'file' => [$result->code],
                ],
            ], 422);
        }

        return response()->json([
            'import_id' => $result->import->id,
            'status' => $result->import->status,
            'headers' => $result->headers,
        ], 201);
    }

    public function commit(StoreImportCommitRequest $request, Import $import, QueueImportCommit $queueImportCommit): RedirectResponse|JsonResponse
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
