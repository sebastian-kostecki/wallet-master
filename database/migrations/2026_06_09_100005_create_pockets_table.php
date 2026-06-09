<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pockets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('icon')->default('target');
            $table->string('color')->default('#6366f1');
            $table->unsignedInteger('sort_order')->default(10);
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('initial_balance', 12, 2)->default(0);
            $table->decimal('target_amount', 12, 2)->nullable();
            $table->string('planning_mode')->nullable();
            $table->decimal('monthly_contribution', 12, 2)->nullable();
            $table->date('target_date')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pockets');
    }
};
