<?php

namespace App\Listeners;

use App\Events\TransferCreated;
use App\Telemetry\Event;

final class LogTransferCreated
{
    public function handle(TransferCreated $event): void
    {
        Event::record('transfer_created', [
            'transfer_id' => $event->transferId,
            'from_account_id' => $event->fromAccountId,
            'to_account_id' => $event->toAccountId,
            'amount' => $event->amount,
            'date' => $event->date,
        ], $event->userId);
    }
}
