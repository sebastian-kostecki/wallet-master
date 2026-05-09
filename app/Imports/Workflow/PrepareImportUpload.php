<?php

declare(strict_types=1);

namespace App\Imports\Workflow;

use App\Enums\ImportStatus;
use App\Models\Account;
use App\Models\Import;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class PrepareImportUpload
{
    public function execute(Account $account, User $user, UploadedFile $uploadedFile): PrepareImportUploadResult
    {
        $bank = $account->bank;

        if ($bank === null || ! $bank->supportsImport()) {
            throw new RuntimeException('This account bank does not support imports.');
        }

        $adapter = $bank->makeImportAdapter();

        $extension = strtolower($uploadedFile->getClientOriginalExtension()) ?: 'csv';
        $tempRelativePath = "imports/{$user->id}/tmp/".Str::uuid()->toString().'.'.Str::lower($extension);
        $contents = $uploadedFile->get();

        if ($contents === false) {
            return PrepareImportUploadResult::unreadableContents();
        }

        Storage::disk('local')->put($tempRelativePath, $contents);

        $absoluteTempPath = Storage::disk('local')->path($tempRelativePath);

        try {
            $headers = $adapter->extractHeaders($absoluteTempPath);
        } catch (\Throwable) {
            Storage::disk('local')->delete($tempRelativePath);

            return PrepareImportUploadResult::unreadableHeaders();
        }

        $mapping = $adapter->defaultMapping($headers);

        if ($mapping === null) {
            Storage::disk('local')->delete($tempRelativePath);

            return PrepareImportUploadResult::unrecognizedHeaders();
        }

        $import = Import::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'status' => ImportStatus::Draft->value,
        ]);

        $finalRelativePath = "imports/{$user->id}/{$import->id}/source.".Str::lower($extension);
        Storage::disk('local')->move($tempRelativePath, $finalRelativePath);

        $import->mapping = $mapping;
        $import->details = [
            'source_file' => $finalRelativePath,
            'parser' => $adapter::class,
            'bank' => $account->bank->value,
            'headers' => $headers,
        ];
        $import->save();

        return PrepareImportUploadResult::ok($import, $headers);
    }
}
