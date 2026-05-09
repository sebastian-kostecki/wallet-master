<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Imports\StoreImportUploadRequest;
use App\Imports\Workflow\PrepareImportUpload;
use App\Models\Account;
use Illuminate\Http\JsonResponse;

final class ImportUploadController extends Controller
{
    public function store(StoreImportUploadRequest $request, PrepareImportUpload $prepareImportUpload): JsonResponse
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
}
