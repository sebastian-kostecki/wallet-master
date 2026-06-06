<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('goals') && ! Schema::hasTable('pockets')) {
            Schema::rename('goals', 'pockets');
        }

        if (Schema::hasColumn('transactions', 'goal_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropForeign(['goal_id']);
                $table->dropIndex(['user_id', 'goal_id', 'booked_at']);
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->renameColumn('goal_id', 'pocket_id');
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->foreign('pocket_id')->references('id')->on('pockets')->nullOnDelete();
                $table->index(['user_id', 'pocket_id', 'booked_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'pocket_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropForeign(['pocket_id']);
                $table->dropIndex(['user_id', 'pocket_id', 'booked_at']);
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->renameColumn('pocket_id', 'goal_id');
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->foreign('goal_id')->references('id')->on('goals')->nullOnDelete();
                $table->index(['user_id', 'goal_id', 'booked_at']);
            });
        }

        if (Schema::hasTable('pockets') && ! Schema::hasTable('goals')) {
            Schema::rename('pockets', 'goals');
        }
    }
};
