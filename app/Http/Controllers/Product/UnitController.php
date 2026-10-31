<?php

declare(strict_types=1);

namespace App\Http\Controllers\Product;

use App\Actions\Product\QueryUnits;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(QueryUnits $queryUnits): View
    {
        $this->authorize('viewAny', Unit::class);

        $units = $queryUnits->index();

        return view('units.index', compact('units'));
    }
}
