<?php

namespace App\Listeners;

use App\Events\TransferCreated;
use Illuminate\Support\Facades\Log;

final class LogTransferCreated
{
    public function handle(TransferCreated $event): void
    {
        Log::info('transfer_created', [
            'user_id' => $event->userId,
            'transfer_id' => $event->transferId,
            'from_account_id' => $event->fromAccountId,
            'to_account_id' => $event->toAccountId,
            'amount' => $event->amount,
            'date' => $event->date,
        ]);
    }
}
