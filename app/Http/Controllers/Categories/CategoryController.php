<?php

declare(strict_types=1);

namespace App\Http\Controllers\Categories;

use App\Actions\Categories\DeleteCategory;
use App\Actions\Categories\ListCategories;
use App\Actions\Categories\SaveAnnualEstimate;
use App\Actions\Categories\SaveMonthlyEstimate;
use App\Actions\Categories\StoreCategory;
use App\Actions\Categories\UpdateCategory;
use App\Data\Categories\CategoryFormOptions;
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
        $listCategories->handle($request->user());

        return Inertia::render('categories/Index', [
            'categories' => CategoryResource::collection($listCategories->getCategories())->resolve(),
        ]);
    }

    public function create(CategoryFormOptions $options): Response
    {
        return Inertia::render('categories/Create', $options->toArray());
    }

    public function store(StoreCategoryRequest $request, StoreCategory $storeCategory): RedirectResponse
    {
        $storeCategory->handle($request->user(), $request->validated());

        return to_route('categories.index')->with('toast', [
            'type' => 'success',
            'message_key' => 'categories.toast.created',
        ]);
    }

    public function edit(Category $category, CategoryFormOptions $options): Response
    {
        return Inertia::render('categories/Edit', [
            'category' => CategoryResource::make($category)->resolve(),
            'has_transactions' => $category->transactions()->exists(),
            ...$options->toArray(),
        ]);
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category,
        UpdateCategory $updateCategory,
    ): RedirectResponse {
        $updateCategory->handle($category, $request->validated());

        if ($request->has('sort_order') && ! $request->has('name') && ! $request->has('icon') && ! $request->has('color')) {
            return back()->with('toast', [
                'type' => 'success',
                'message_key' => 'categories.toast.updated',
            ]);
        }

        return to_route('categories.index')->with('toast', [
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
