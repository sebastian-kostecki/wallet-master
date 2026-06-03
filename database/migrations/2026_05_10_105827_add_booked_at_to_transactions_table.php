<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    private function indexExists(string $table, string $index): bool
    {
        if ($this->isSqlite()) {
            return false;
        }

        $database = (string) DB::connection()->getDatabaseName();

        $row = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $index],
        );

        return $row !== null;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->isSqlite()) {
            if (! Schema::hasColumn('transactions', 'booked_at')) {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->date('booked_at')->nullable()->after('date');
                });

                DB::table('transactions')->whereNull('booked_at')->update([
                    'booked_at' => DB::raw('`date`'),
                ]);
            }

            Schema::table('transactions', function (Blueprint $table) {
                $table->index(['account_id', 'booked_at']);
                $table->index(['user_id', 'booked_at']);
            });

            return;
        }

        if (! Schema::hasColumn('transactions', 'booked_at')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->date('booked_at')->nullable()->after('date');
            });

            DB::table('transactions')->whereNull('booked_at')->update([
                'booked_at' => DB::raw('`date`'),
            ]);

            DB::statement('ALTER TABLE `transactions` MODIFY `booked_at` DATE NOT NULL');
        }

        if (! $this->indexExists('transactions', 'transactions_account_id_booked_at_index')) {
            DB::statement('ALTER TABLE `transactions` ADD INDEX `transactions_account_id_booked_at_index` (`account_id`, `booked_at`)');
        }

        if (! $this->indexExists('transactions', 'transactions_user_id_booked_at_index')) {
            DB::statement('ALTER TABLE `transactions` ADD INDEX `transactions_user_id_booked_at_index` (`user_id`, `booked_at`)');
        }

        if ($this->indexExists('transactions', 'transactions_account_id_date_index')) {
            DB::statement('ALTER TABLE `transactions` DROP INDEX `transactions_account_id_date_index`');
        }

        if ($this->indexExists('transactions', 'transactions_user_id_date_index')) {
            DB::statement('ALTER TABLE `transactions` DROP INDEX `transactions_user_id_date_index`');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->isSqlite()) {
            if (Schema::hasColumn('transactions', 'booked_at')) {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->dropColumn('booked_at');
                });
            }

            return;
        }

        if ($this->indexExists('transactions', 'transactions_account_id_booked_at_index')) {
            DB::statement('ALTER TABLE `transactions` DROP INDEX `transactions_account_id_booked_at_index`');
        }

        if ($this->indexExists('transactions', 'transactions_user_id_booked_at_index')) {
            DB::statement('ALTER TABLE `transactions` DROP INDEX `transactions_user_id_booked_at_index`');
        }

        if (! $this->indexExists('transactions', 'transactions_account_id_date_index')) {
            DB::statement('ALTER TABLE `transactions` ADD INDEX `transactions_account_id_date_index` (`account_id`, `date`)');
        }

        if (! $this->indexExists('transactions', 'transactions_user_id_date_index')) {
            DB::statement('ALTER TABLE `transactions` ADD INDEX `transactions_user_id_date_index` (`user_id`, `date`)');
        }

        if (Schema::hasColumn('transactions', 'booked_at')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('booked_at');
            });
        }
    }
};
