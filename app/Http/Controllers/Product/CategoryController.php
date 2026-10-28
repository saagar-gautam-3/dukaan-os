<?php

declare(strict_types=1);

namespace App\Http\Controllers\Product;

use App\Actions\Product\CreateCategory;
use App\Actions\Product\DeleteCategory;
use App\Actions\Product\UpdateCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreCategoryRequest;
use App\Http\Requests\Product\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class CategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::withCount('products')->orderBy('name')->get();

        return view('categories.index', compact('categories'));
    }

    public function store(StoreCategoryRequest $request, CreateCategory $createCategory): RedirectResponse
    {
        try {
            $createCategory->execute($request->validated());

            return redirect()->route('categories.index')->with('status', __('products.category_created'));
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $updateCategory): RedirectResponse
    {
        try {
            $updateCategory->execute($category, $request->validated());

            return redirect()->route('categories.index')->with('status', __('products.category_updated'));
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(Category $category, DeleteCategory $deleteCategory): RedirectResponse
    {
        $this->authorize('delete', $category);

        try {
            $deleteCategory->execute($category);

            return redirect()->route('categories.index')->with('status', __('products.category_deleted'));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
