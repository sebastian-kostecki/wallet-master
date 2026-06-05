<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->after('sort_order')->constrained('currencies');
        });

        DB::table('currencies')->updateOrInsert(
            ['code' => 'PLN'],
            [
                'name' => 'Złoty',
                'symbol' => 'zł',
                'precision' => 2,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $plnId = DB::table('currencies')->where('code', 'PLN')->value('id');

        if ($plnId === null) {
            throw new RuntimeException('PLN currency must exist before goals currency migration.');
        }

        DB::table('goals')->whereNull('currency_id')->update(['currency_id' => $plnId]);

        Schema::table('goals', function (Blueprint $table) {
            $table->unsignedBigInteger('currency_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn('currency_id');
        });
    }
};
