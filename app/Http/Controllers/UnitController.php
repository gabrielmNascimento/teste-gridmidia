<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnitRequest;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class UnitController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Unit::query()->with('company')->orderBy('name')->paginate(20),
        ]);
    }

    public function store(StoreUnitRequest $request): JsonResponse|RedirectResponse
    {
        $unit = Unit::create($request->validated())->load('company');

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['data' => $unit], 201);
        }

        return redirect()->route('dashboard')->with('status', 'Unidade cadastrada com sucesso.');
    }
}
