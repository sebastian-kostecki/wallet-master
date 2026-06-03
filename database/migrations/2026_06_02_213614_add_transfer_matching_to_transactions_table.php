<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('transfer_match_status', 20)->default('none')->after('transfer_id');
            $table->foreignId('transfer_candidate_for_id')
                ->nullable()
                ->after('transfer_match_status')
                ->constrained('transactions')
                ->nullOnDelete();

            $table->index(['user_id', 'transfer_match_status']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'transfer_match_status']);
            $table->dropConstrainedForeignId('transfer_candidate_for_id');
            $table->dropColumn('transfer_match_status');
        });
    }
};
