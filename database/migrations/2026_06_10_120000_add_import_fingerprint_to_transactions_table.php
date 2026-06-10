<?php

declare(strict_types=1);

use App\Support\Transactions\TransactionDedupe;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->binary('import_fingerprint', 16)->nullable()->after('dedupe_hash');
            $table->dropUnique(['account_id', 'dedupe_hash']);
            $table->index(['account_id', 'dedupe_hash']);
        });

        $transactions = DB::table('transactions')
            ->whereNotNull('import_id')
            ->whereNotNull('raw_statement_description')
            ->orderBy('id')
            ->select(['id', 'account_id', 'date', 'amount', 'raw_statement_description'])
            ->lazyById();

        /** @var array<string, true> $seenBackfillFingerprints */
        $seenBackfillFingerprints = [];

        foreach ($transactions as $transaction) {
            $normalizedRaw = TransactionDedupe::normalizeDescription((string) $transaction->raw_statement_description);
            $fingerprint = TransactionDedupe::importFingerprint(
                accountId: (int) $transaction->account_id,
                dateYmd: (string) $transaction->date,
                amountDecimalString: TransactionDedupe::amountToDecimalString((string) $transaction->amount),
                normalizedRawStatementDescription: $normalizedRaw,
            );
            $fingerprintKey = (int) $transaction->account_id.'|'.bin2hex($fingerprint);

            if (isset($seenBackfillFingerprints[$fingerprintKey])) {
                continue;
            }

            DB::table('transactions')
                ->where('id', $transaction->id)
                ->update([
                    'import_fingerprint' => $fingerprint,
                ]);

            $seenBackfillFingerprints[$fingerprintKey] = true;
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->unique(['account_id', 'import_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropUnique(['account_id', 'import_fingerprint']);
            $table->dropIndex(['account_id', 'dedupe_hash']);
            $table->unique(['account_id', 'dedupe_hash']);
            $table->dropColumn('import_fingerprint');
        });
    }
};
