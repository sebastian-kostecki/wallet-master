<?php

declare(strict_types=1);

use App\Support\Categories\CategoryColors;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->string('icon')->default('target')->after('name');
            $table->string('color')->default('#6366f1')->after('icon');
            $table->decimal('target_amount', 12, 2)->nullable()->after('sort_order');
            $table->string('planning_mode')->nullable()->after('target_amount');
            $table->decimal('monthly_contribution', 12, 2)->nullable()->after('planning_mode');
            $table->date('target_date')->nullable()->after('monthly_contribution');
            $table->boolean('is_archived')->default(false)->after('target_date');
        });

        $year = (int) now()->year;

        if (Schema::hasTable('goal_annual_estimates')) {
            DB::table('goal_annual_estimates')
                ->where('year', $year)
                ->whereNotNull('amount')
                ->orderBy('id')
                ->each(function ($row): void {
                    DB::table('goals')->where('id', $row->goal_id)->update([
                        'target_amount' => $row->amount,
                        'planning_mode' => 'monthly',
                        'monthly_contribution' => bcdiv((string) $row->amount, '12', 2),
                    ]);
                });
        }

        $colors = CategoryColors::values();
        $colorCount = count($colors);

        if ($colorCount > 0) {
            $goals = DB::table('goals')->orderBy('sort_order')->orderBy('id')->get();

            foreach ($goals as $index => $goal) {
                DB::table('goals')->where('id', $goal->id)->update([
                    'color' => $colors[$index % $colorCount],
                ]);
            }
        }

        Schema::dropIfExists('goal_monthly_estimates');
        Schema::dropIfExists('goal_annual_estimates');
    }

    public function down(): void
    {
        Schema::create('goal_annual_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['goal_id', 'year']);
        });

        Schema::create('goal_monthly_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['goal_id', 'year', 'month']);
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn([
                'icon',
                'color',
                'target_amount',
                'planning_mode',
                'monthly_contribution',
                'target_date',
                'is_archived',
            ]);
        });
    }
};
