<?php

namespace App\Listeners;

use App\Events\TransferFailedValidation;
use App\Telemetry\Event;

final class LogTransferFailedValidation
{
    public function handle(TransferFailedValidation $event): void
    {
        Event::record('transfer_failed_validation', [
            'fields' => $event->fields,
        ], $event->userId);
    }
}
