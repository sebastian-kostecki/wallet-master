<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Import;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

final readonly class ImportStatusUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public Import $import,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->import->user_id);
    }

    /**
     * @return array{
     *   id:int,
     *   status:string,
     *   rows_total:int|null,
     *   rows_imported:int|null,
     *   rows_skipped_duplicate:int|null,
     *   rows_failed_validation:int|null,
     *   error_summary:string|null,
     *   committed_at:string|null
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'id' => (int) $this->import->id,
            'status' => (string) $this->import->status,
            'rows_total' => $this->import->rows_total,
            'rows_imported' => $this->import->rows_imported,
            'rows_skipped_duplicate' => $this->import->rows_skipped_duplicate,
            'rows_failed_validation' => $this->import->rows_failed_validation,
            'error_summary' => $this->import->error_summary,
            'committed_at' => $this->import->committed_at?->toISOString(),
        ];
    }
}
