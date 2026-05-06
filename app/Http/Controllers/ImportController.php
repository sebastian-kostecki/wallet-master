<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Import;
use Illuminate\Http\JsonResponse;
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

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
            'rows_total' => $import->rows_total,
            'rows_imported' => $import->rows_imported,
            'rows_skipped_duplicate' => $import->rows_skipped_duplicate,
            'rows_failed_validation' => $import->rows_failed_validation,
            'error_summary' => $import->error_summary,
            'committed_at' => $import->committed_at,
        ]);
    }
}
