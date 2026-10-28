<?php

declare(strict_types=1);

namespace App\Http\Controllers\Product;

use App\Actions\Product\CreateUnit;
use App\Actions\Product\UpdateUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreUnitRequest;
use App\Http\Requests\Product\UpdateUnitRequest;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class UnitController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Unit::class);

        $units = Unit::withCount('products')->orderBy('name')->get();

        return view('units.index', compact('units'));
    }

    public function store(StoreUnitRequest $request, CreateUnit $createUnit): RedirectResponse
    {
        try {
            $data = $request->validated();
            $data['is_fractional'] = $request->boolean('is_fractional', false);

            $createUnit->execute($data);

            return redirect()->route('units.index')->with('status', __('products.unit_created'));
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update(UpdateUnitRequest $request, Unit $unit, UpdateUnit $updateUnit): RedirectResponse
    {
        try {
            $data = $request->validated();
            $data['is_fractional'] = $request->boolean('is_fractional', false);

            $updateUnit->execute($unit, $data);

            return redirect()->route('units.index')->with('status', __('products.unit_updated'));
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
