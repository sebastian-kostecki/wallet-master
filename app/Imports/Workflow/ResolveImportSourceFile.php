<?php

declare(strict_types=1);

namespace App\Imports\Workflow;

use App\Models\Import;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final readonly class ResolveImportSourceFile
{
    /**
     * @return array{absolute: string, relative: string}
     */
    public function resolve(Import $import): array
    {
        $relative = (string) data_get($import->details, 'source_file', '');

        if ($relative === '' || ! Storage::disk('local')->exists($relative)) {
            throw new RuntimeException('Import source file does not exist.');
        }

        return [
            'absolute' => Storage::disk('local')->path($relative),
            'relative' => $relative,
        ];
    }
}
