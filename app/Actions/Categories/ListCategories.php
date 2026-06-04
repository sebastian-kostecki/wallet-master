<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class ListCategories
{
    /** @var Collection<int, Category> */
    private Collection $categories;

    public function handle(User $user, ?int $year = null): void
    {
        app(EnsureUserCategories::class)->handle($user);

        $query = Category::query()
            ->where('user_id', $user->id)
            ->ordered();

        if ($year !== null) {
            $query->with([
                'annualEstimates' => fn ($estimateQuery) => $estimateQuery->where('year', $year),
            ]);
        }

        $this->categories = $query->get();
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }
}
