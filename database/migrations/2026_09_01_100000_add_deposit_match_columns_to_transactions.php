<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('deposit_match_status', 20)->default('none')->after('transfer_candidate_for_id');
            $table->string('deposit_candidate_kind', 32)->nullable()->after('deposit_match_status');
            $table->index(['user_id', 'deposit_match_status']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'deposit_match_status']);
            $table->dropColumn(['deposit_match_status', 'deposit_candidate_kind']);
        });
    }
};
