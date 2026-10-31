<?php

declare(strict_types=1);

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Unit::class);

        $units = Unit::withCount('products')->orderBy('name')->get();

        return view('units.index', compact('units'));
    }
}
