<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pockets', function (Blueprint $table) {
            $table->decimal('initial_balance', 12, 2)->default(0)->after('currency_id');
        });
    }

    public function down(): void
    {
        Schema::table('pockets', function (Blueprint $table) {
            $table->dropColumn('initial_balance');
        });
    }
};
