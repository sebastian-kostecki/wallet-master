<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
    }
}
