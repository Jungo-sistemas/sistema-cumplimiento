<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetType;
use App\Models\RequirementTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('api_company_id');

        $types = AssetType::where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($types);
    }

    public function requirements(Request $request, AssetType $assetType): JsonResponse
    {
        $companyId = $request->attributes->get('api_company_id');

        if ((int) $assetType->company_id !== (int) $companyId) {
            return response()->json(['error' => 'No encontrado.'], 404);
        }

        $requirements = RequirementTemplate::where('asset_type_id', $assetType->id)
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->pluck('name');

        return response()->json([
            'asset_type'   => $assetType->name,
            'requirements' => $requirements,
        ]);
    }
}
