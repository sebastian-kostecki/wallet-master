<?php

namespace App\Listeners;

use App\Events\TransferFailedValidation;
use Illuminate\Support\Facades\Log;

final class LogTransferFailedValidation
{
    public function handle(TransferFailedValidation $event): void
    {
        Log::warning('transfer_failed_validation', [
            'user_id' => $event->userId,
            'fields' => $event->fields,
        ]);
    }
}
