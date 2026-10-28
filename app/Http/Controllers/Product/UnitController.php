<?php

declare(strict_types=1);

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Unit::class);

        $units = Unit::withCount('products')->orderBy('name')->get();

        return view('units.index', compact('units'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Unit::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:20', 'unique:units,symbol'],
            'is_fractional' => ['nullable', 'boolean'],
        ]);

        $validated['is_fractional'] = $request->boolean('is_fractional', false);

        Unit::create($validated);

        return redirect()->route('units.index')->with('status', __('products.unit_created'));
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        Gate::authorize('update', $unit);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_fractional' => ['nullable', 'boolean'],
        ]);

        $validated['is_fractional'] = $request->boolean('is_fractional', false);

        $unit->update($validated);

        return redirect()->route('units.index')->with('status', __('products.unit_updated'));
    }
}
