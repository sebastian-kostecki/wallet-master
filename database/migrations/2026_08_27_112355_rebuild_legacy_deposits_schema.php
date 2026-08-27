<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forward-fix: June 11 leftover deposits used `bank` instead of `source_account_id`
 * and added deposit_match_* columns on transactions. Those migration files are gone,
 * but the schema can still be present. Rollback is not supported.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropLegacyTransactionDepositColumns();
        $this->rebuildLegacyDepositsTables();
    }

    public function down(): void
    {
        // Irreversible: June 11 deposit schema is not restored.
    }

    private function dropLegacyTransactionDepositColumns(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        if (Schema::hasColumn('transactions', 'deposit_candidate_deposit_id')) {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->dropForeign(['deposit_candidate_deposit_id']);
                $table->dropColumn('deposit_candidate_deposit_id');
            });
        }

        if (Schema::hasColumn('transactions', 'deposit_match_status')) {
            Schema::table('transactions', function (Blueprint $table): void {
                if (Schema::hasIndex('transactions', ['user_id', 'deposit_match_status'])) {
                    $table->dropIndex(['user_id', 'deposit_match_status']);
                }

                $table->dropColumn('deposit_match_status');
            });
        }
    }

    private function rebuildLegacyDepositsTables(): void
    {
        if (! Schema::hasTable('deposits') || Schema::hasColumn('deposits', 'source_account_id')) {
            return;
        }

        Schema::dropIfExists('deposit_movements');
        Schema::dropIfExists('deposits');

        Schema::create('deposits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained();
            $table->foreignId('source_account_id')->constrained('accounts');
            $table->string('name')->nullable();
            $table->decimal('principal', 15, 2);
            $table->date('opened_at');
            $table->date('matures_at')->nullable();
            $table->string('status', 20);
            $table->date('closed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'currency_id', 'status']);
        });

        Schema::create('deposit_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deposit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->unique();
            $table->string('kind', 32);
            $table->timestamps();
            $table->unique(['deposit_id', 'kind']);
        });
    }
};
