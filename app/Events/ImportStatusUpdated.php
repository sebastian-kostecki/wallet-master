<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\ImportStatus;
use App\Http\Resources\Imports\ImportFailedRowResource;
use App\Models\Import;
use App\Models\ImportFailedRow;
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
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $progress = [
            'rows_total' => $this->import->rows_total,
            'rows_imported' => $this->import->rows_imported,
            'rows_skipped_duplicate' => $this->import->rows_skipped_duplicate,
            'rows_failed_validation' => $this->import->rows_failed_validation,
        ];

        $payload = [
            'id' => (int) $this->import->id,
            'status' => (string) $this->import->status,
            'rows_total' => $this->import->rows_total,
            'rows_imported' => $this->import->rows_imported,
            'rows_skipped_duplicate' => $this->import->rows_skipped_duplicate,
            'rows_failed_validation' => $this->import->rows_failed_validation,
            'error_summary' => $this->import->error_summary,
            'committed_at' => $this->import->committed_at?->toISOString(),
            'progress' => $progress,
        ];

        if ($this->import->status === ImportStatus::Committed->value) {
            $failedRows = ImportFailedRow::query()
                ->where('import_id', $this->import->id)
                ->orderBy('row_number')
                ->limit(50)
                ->get();

            $payload['failed_rows'] = ImportFailedRowResource::collection($failedRows)->resolve();
            $payload['failed_rows_total'] = ImportFailedRow::query()
                ->where('import_id', $this->import->id)
                ->count();
        }

        return $payload;
    }
}
