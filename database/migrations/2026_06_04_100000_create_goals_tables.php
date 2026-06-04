<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(10);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });

        Schema::create('goal_annual_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['goal_id', 'year']);
        });

        Schema::create('goal_monthly_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['goal_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_monthly_estimates');
        Schema::dropIfExists('goal_annual_estimates');
        Schema::dropIfExists('goals');
    }
};
