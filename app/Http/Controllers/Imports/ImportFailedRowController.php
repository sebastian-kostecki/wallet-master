<?php

declare(strict_types=1);

namespace App\Http\Controllers\Imports;

use App\Actions\Imports\DismissAllImportFailedRows;
use App\Actions\Imports\DismissImportFailedRow;
use App\Http\Controllers\Controller;
use App\Models\ImportFailedRow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ImportFailedRowController extends Controller
{
    public function dismiss(
        Request $request,
        ImportFailedRow $importFailedRow,
        DismissImportFailedRow $dismissImportFailedRow,
    ): RedirectResponse {
        $this->authorize('dismiss', $importFailedRow);

        $dismissImportFailedRow->handle($request->user(), $importFailedRow);

        return back();
    }

    public function dismissAll(Request $request, DismissAllImportFailedRows $dismissAllImportFailedRows): RedirectResponse
    {
        $this->authorize('dismissAll', ImportFailedRow::class);

        $accountId = $request->filled('account_id') ? $request->integer('account_id') : null;

        $dismissAllImportFailedRows->handle($request->user(), $accountId);

        return back();
    }
}
