<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companies = Company::where('group_id', $request->attributes->get('api_group_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($companies);
    }
}
