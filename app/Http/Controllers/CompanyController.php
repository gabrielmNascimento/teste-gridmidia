<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CompanyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Company::query()->orderBy('name')->get()]);
    }

    public function store(StoreCompanyRequest $request): JsonResponse|RedirectResponse
    {
        $company = Company::create($request->validated());

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['data' => $company], 201);
        }

        return redirect()->route('dashboard')->with('status', 'Empresa cadastrada com sucesso.');
    }
}
