<?php

declare(strict_types=1);

use App\Actions\Goals\MigrateLegacySavingsEstimate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(MigrateLegacySavingsEstimate::class)->handle();
    }

    public function down(): void
    {
        // Data migration — no rollback.
    }
};
