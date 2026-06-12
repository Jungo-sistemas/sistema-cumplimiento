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
        $groupId = $request->attributes->get('api_group_id');

        $types = AssetType::whereHas('company', fn ($q) => $q->where('group_id', $groupId))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($types);
    }

    public function requirements(Request $request, AssetType $assetType): JsonResponse
    {
        $groupId = $request->attributes->get('api_group_id');

        $belongsToGroup = $assetType->company()
            ->where('group_id', $groupId)
            ->exists();

        if (! $belongsToGroup) {
            return response()->json(['error' => 'No encontrado.'], 404);
        }

        $requirements = RequirementTemplate::where('asset_type_id', $assetType->id)
            ->orderBy('name')
            ->pluck('name');

        return response()->json([
            'asset_type'   => $assetType->name,
            'requirements' => $requirements,
        ]);
    }
}
