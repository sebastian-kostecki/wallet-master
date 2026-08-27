<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
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

        Schema::create('deposit_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->unique();
            $table->string('kind', 32);
            $table->timestamps();
            $table->unique(['deposit_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_movements');
        Schema::dropIfExists('deposits');
    }
};
