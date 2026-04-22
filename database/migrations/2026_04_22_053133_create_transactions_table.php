<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->decimal('amount', 20, 2);
            $table->string('type', 10);
            $table->text('description');
            $table->string('subject', 255)->nullable();
            $table->string('normalized_description', 255);
            $table->binary('dedupe_hash', 16);
            $table->uuid('transfer_id')->nullable();
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'date']);
            $table->index(['user_id', 'date']);
            $table->index('transfer_id');
            $table->index('import_id');

            $table->unique(['account_id', 'dedupe_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
