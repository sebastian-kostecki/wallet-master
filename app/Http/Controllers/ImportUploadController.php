<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Imports\StoreImportUploadRequest;
use App\Imports\BankImportAdapterResolver;
use App\Models\Account;
use App\Models\Import;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ImportUploadController extends Controller
{
    public function store(StoreImportUploadRequest $request, BankImportAdapterResolver $resolver): JsonResponse
    {
        $validated = $request->validated();

        $account = Account::query()
            ->whereBelongsTo($request->user())
            ->whereKey($validated['account_id'])
            ->firstOrFail();

        $adapter = $resolver->resolve($account->bank);

        $import = Import::query()->create([
            'user_id' => $request->user()->id,
            'account_id' => $account->id,
            'status' => 'draft',
        ]);

        $uploadedFile = $request->file('file');
        $extension = strtolower($uploadedFile->getClientOriginalExtension()) ?: 'csv';
        $relativePath = "imports/{$request->user()->id}/{$import->id}/source.".Str::lower($extension);

        Storage::disk('local')->put($relativePath, $uploadedFile->get());

        $absolutePath = Storage::disk('local')->path($relativePath);
        $headers = $adapter->extractHeaders($absolutePath);

        $import->details = [
            'source_file' => $relativePath,
            'parser' => $adapter::class,
            'headers' => $headers,
        ];
        $import->save();

        return response()->json([
            'import_id' => $import->id,
            'status' => $import->status,
            'headers' => $headers,
        ], 201);
    }
}
