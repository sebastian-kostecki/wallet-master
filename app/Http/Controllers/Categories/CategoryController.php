<?php

declare(strict_types=1);

namespace App\Http\Controllers\Categories;

use App\Actions\Categories\DeleteCategory;
use App\Actions\Categories\ListCategories;
use App\Actions\Categories\SaveAnnualEstimate;
use App\Actions\Categories\SaveMonthlyEstimate;
use App\Actions\Categories\StoreCategory;
use App\Actions\Categories\UpdateCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\SaveAnnualEstimateRequest;
use App\Http\Requests\Categories\SaveMonthlyEstimateRequest;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Http\Resources\Categories\CategoryResource;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CategoryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Category::class, 'category');
    }

    public function index(Request $request, ListCategories $listCategories): Response
    {
        $year = (int) $request->query('year', (string) now()->year);
        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        $listCategories->handle($request->user(), $year);

        return Inertia::render('categories/Index', [
            'year' => $year,
            'categories' => CategoryResource::collection($listCategories->getCategories())->resolve(),
        ]);
    }

    public function store(StoreCategoryRequest $request, StoreCategory $storeCategory): RedirectResponse
    {
        $storeCategory->handle($request->user(), $request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message_key' => 'categories.toast.created',
        ]);
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category,
        UpdateCategory $updateCategory,
    ): RedirectResponse {
        $updateCategory->handle($category, $request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message_key' => 'categories.toast.updated',
        ]);
    }

    public function destroy(Category $category, DeleteCategory $deleteCategory): RedirectResponse
    {
        $deleteCategory->handle($category);

        return back()->with('toast', [
            'type' => 'success',
            'message_key' => 'categories.toast.deleted',
        ]);
    }

    public function saveAnnualEstimate(
        SaveAnnualEstimateRequest $request,
        Category $category,
        SaveAnnualEstimate $saveAnnualEstimate,
    ): RedirectResponse {
        $this->authorize('update', $category);
        $saveAnnualEstimate->handle($category, $request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message_key' => 'categories.toast.estimate_saved',
        ]);
    }

    public function saveMonthlyEstimate(
        SaveMonthlyEstimateRequest $request,
        Category $category,
        SaveMonthlyEstimate $saveMonthlyEstimate,
    ): RedirectResponse {
        $this->authorize('update', $category);
        $saveMonthlyEstimate->handle($category, $request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message_key' => 'categories.toast.estimate_saved',
        ]);
    }
}
