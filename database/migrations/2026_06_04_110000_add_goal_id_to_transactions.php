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
            $table->foreignId('goal_id')->nullable()->after('category_id')->constrained('goals')->nullOnDelete();
            $table->index(['user_id', 'goal_id', 'booked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['goal_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'goal_id', 'booked_at']);
            $table->dropColumn('goal_id');
        });
    }
};
