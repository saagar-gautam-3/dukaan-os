<?php

declare(strict_types=1);

namespace App\Http\Controllers\Product;

use App\Actions\Product\CreateProduct;
use App\Actions\Product\DeleteProduct;
use App\Actions\Product\QueryProducts;
use App\Actions\Product\UpdateProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource with server-side search, filtering, and pagination.
     */
    public function index(Request $request, QueryProducts $queryProducts): View
    {
        $this->authorize('viewAny', Product::class);

        $products = $queryProducts->execute($request->all());
        $categories = Category::orderBy('name')->get();

        return view('products.index', compact('products', 'categories'));
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): View
    {
        $this->authorize('create', Product::class);

        $categories = Category::orderBy('name')->get();
        $units = Unit::orderBy('name')->get();

        return view('products.form', compact('categories', 'units'));
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request, CreateProduct $createProduct): RedirectResponse
    {
        try {
            $data = $request->validated();
            if ($request->has('is_active')) {
                $data['is_active'] = $request->boolean('is_active');
            }

            $createProduct->execute($data);

            return redirect()->route('products.index')->with('status', __('products.created_success'));
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        $categories = Category::orderBy('name')->get();
        $units = Unit::orderBy('name')->get();

        return view('products.form', compact('product', 'categories', 'units'));
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, Product $product, UpdateProduct $updateProduct): RedirectResponse
    {
        try {
            $data = $request->validated();
            if ($request->has('is_active')) {
                $data['is_active'] = $request->boolean('is_active');
            }

            $updateProduct->execute($product, $data);

            return redirect()->route('products.index')->with('status', __('products.updated_success'));
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product, DeleteProduct $deleteProduct): RedirectResponse
    {
        $this->authorize('delete', $product);

        try {
            $deleteProduct->execute($product);

            return redirect()->route('products.index')->with('status', __('products.deleted_success'));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
