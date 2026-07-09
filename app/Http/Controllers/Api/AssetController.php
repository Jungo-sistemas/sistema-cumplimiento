<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request, Company $company): JsonResponse
    {
        if ((int) $company->group_id !== (int) $request->attributes->get('api_group_id')) {
            return response()->json(['error' => 'No autorizado para esta empresa.'], 403);
        }

        $perPage = min((int) $request->integer('per_page', 50), 200);

        $assets = $company->assets()
            ->with(['assetType:id,name,slug', 'responsible:id,name,email'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn ($asset) => [
                'id' => $asset->id,
                'code' => $asset->code,
                'name' => $asset->name,
                'status' => $asset->status,
                'asset_type' => $asset->assetType ? [
                    'name' => $asset->assetType->name,
                    'slug' => $asset->assetType->slug,
                ] : null,
                'location' => [
                    'street_address' => $asset->street_address,
                    'colonia' => $asset->colonia,
                    'municipality' => $asset->municipality,
                    'postal_code' => $asset->postal_code,
                    'state' => $asset->location,
                ],
                'responsible' => $asset->responsible ? [
                    'name' => $asset->responsible->name,
                    'email' => $asset->responsible->email,
                ] : null,
                'compliance_start_date' => optional($asset->compliance_start_date)->toDateString(),
                'compliance_due_date' => optional($asset->compliance_due_date)->toDateString(),
            ]);

        return response()->json($assets);
    }
}
