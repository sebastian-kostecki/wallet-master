<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained();
            $table->foreignId('pocket_id')->nullable()->constrained('pockets')->nullOnDelete();
            $table->date('date');
            $table->date('booked_at');
            $table->decimal('amount', 20, 2);
            $table->string('type', 10);
            $table->text('description');
            $table->string('subject', 255)->nullable();
            $table->text('raw_statement_description')->nullable();
            $table->string('normalized_description', 255);
            $table->binary('dedupe_hash', 16);
            $table->uuid('transfer_id')->nullable();
            $table->string('transfer_match_status', 20)->default('none');
            $table->foreignId('transfer_candidate_for_id')
                ->nullable()
                ->constrained('transactions')
                ->nullOnDelete();
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'booked_at']);
            $table->index(['user_id', 'booked_at']);
            $table->index(['category_id', 'booked_at']);
            $table->index(['user_id', 'category_id', 'booked_at']);
            $table->index(['user_id', 'pocket_id', 'booked_at']);
            $table->index(['user_id', 'transfer_match_status']);
            $table->index('transfer_id');
            $table->index('import_id');

            $table->unique(['account_id', 'dedupe_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
