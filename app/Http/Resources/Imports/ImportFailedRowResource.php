<?php

declare(strict_types=1);

namespace App\Http\Resources\Imports;

use App\Models\ImportFailedRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ImportFailedRow
 */
final class ImportFailedRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'import_id' => $this->import_id,
            'account_id' => $this->account_id,
            'row_number' => $this->row_number,
            'reason_code' => $this->reason_code->value,
            'reason_label_key' => $this->reason_code->labelKey(),
            'date_raw' => $this->date_raw,
            'amount_raw' => $this->amount_raw,
            'description_raw' => $this->description_raw,
            'subject_raw' => $this->subject_raw,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
