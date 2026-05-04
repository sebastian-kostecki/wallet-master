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

        $uploadedFile = $request->file('file');
        $extension = strtolower($uploadedFile->getClientOriginalExtension()) ?: 'csv';

        // Persist the file to a temp location first so we can inspect headers
        // without creating an Import record that we may immediately delete.
        $tempRelativePath = "imports/{$request->user()->id}/tmp/".Str::uuid()->toString().'.'.Str::lower($extension);
        Storage::disk('local')->put($tempRelativePath, $uploadedFile->get());

        $absoluteTempPath = Storage::disk('local')->path($tempRelativePath);

        try {
            $headers = $adapter->extractHeaders($absoluteTempPath);
        } catch (\Throwable) {
            Storage::disk('local')->delete($tempRelativePath);

            return response()->json([
                'message' => 'Unable to read import file headers.',
                'code' => 'unreadable_file',
                'errors' => [
                    'file' => ['unreadable_file'],
                ],
            ], 422);
        }

        $mapping = $adapter->defaultMapping($headers);

        if ($mapping === null) {
            Storage::disk('local')->delete($tempRelativePath);

            return response()->json([
                'message' => 'Could not recognize import file columns.',
                'code' => 'unrecognized_headers',
                'errors' => [
                    'file' => ['unrecognized_headers'],
                ],
            ], 422);
        }

        $import = Import::query()->create([
            'user_id' => $request->user()->id,
            'account_id' => $account->id,
            'status' => 'draft',
        ]);

        $finalRelativePath = "imports/{$request->user()->id}/{$import->id}/source.".Str::lower($extension);
        Storage::disk('local')->move($tempRelativePath, $finalRelativePath);

        $import->mapping = $mapping;
        $import->details = [
            'source_file' => $finalRelativePath,
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
