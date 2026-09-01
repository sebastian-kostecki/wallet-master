<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\PocketMovement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('pocket_movement', 20)->nullable()->after('pocket_id');
        });

        DB::table('transactions')
            ->whereNull('transfer_id')
            ->update(['pocket_id' => null]);

        $transferIds = DB::table('transactions')
            ->whereNotNull('pocket_id')
            ->whereNotNull('transfer_id')
            ->distinct()
            ->pluck('transfer_id');

        foreach ($transferIds as $transferId) {
            $legs = DB::table('transactions')
                ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
                ->where('transactions.transfer_id', $transferId)
                ->select(['transactions.amount', 'accounts.type'])
                ->get();

            $movement = PocketMovement::Deposit->value;

            foreach ($legs as $leg) {
                if ($leg->type === AccountType::Savings->value && (float) $leg->amount > 0) {
                    $movement = PocketMovement::Deposit->value;
                    break;
                }

                if ($leg->type === AccountType::Savings->value && (float) $leg->amount < 0) {
                    $movement = PocketMovement::Withdrawal->value;
                    break;
                }
            }

            DB::table('transactions')
                ->where('transfer_id', $transferId)
                ->update(['pocket_movement' => $movement]);
        }

        DB::table('pockets')->update(['is_archived' => false]);

        Schema::table('pockets', function (Blueprint $table): void {
            $table->dropColumn('is_archived');
        });
    }

    public function down(): void
    {
        Schema::table('pockets', function (Blueprint $table): void {
            $table->boolean('is_archived')->default(false);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn('pocket_movement');
        });
    }
};
