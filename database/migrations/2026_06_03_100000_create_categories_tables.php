<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'type', 'sort_order']);
        });

        Schema::create('category_annual_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'year']);
        });

        Schema::create('category_monthly_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_monthly_estimates');
        Schema::dropIfExists('category_annual_estimates');
        Schema::dropIfExists('categories');
    }
};
