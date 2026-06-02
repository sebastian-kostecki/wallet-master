<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_failed_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('reason_code');
            $table->string('date_raw')->nullable();
            $table->string('amount_raw')->nullable();
            $table->string('description_raw', 2000)->nullable();
            $table->string('subject_raw', 255)->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'account_id', 'dismissed_at']);
            $table->index('import_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_failed_rows');
    }
};
