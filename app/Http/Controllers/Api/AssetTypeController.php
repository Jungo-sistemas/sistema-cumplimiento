<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetType;
use App\Models\RequirementTemplate;
use Illuminate\Http\JsonResponse;

class AssetTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = AssetType::select('name', 'slug')
            ->whereNotNull('slug')
            ->distinct()
            ->orderBy('name')
            ->get()
            ->unique('slug')
            ->map(fn ($t) => ['slug' => $t->slug, 'name' => $t->name])
            ->values();

        return response()->json($types);
    }

    public function requirements(string $slug): JsonResponse
    {
        $typeIds = AssetType::where('slug', $slug)->pluck('id');

        if ($typeIds->isEmpty()) {
            return response()->json(['error' => 'Tipo de activo no encontrado.'], 404);
        }

        $name = AssetType::where('slug', $slug)->value('name');

        $templates = RequirementTemplate::whereIn('asset_type_id', $typeIds)
            ->orderBy('name')
            ->get(['name', 'category', 'compliance_scope'])
            ->unique('name');

        $hasCategories = $templates->whereNotNull('category')->where('category', '!=', '')->isNotEmpty();

        if ($hasCategories) {
            $grouped = collect(RequirementTemplate::CATEGORIES)
                ->mapWithKeys(fn ($label, $key) => [
                    $label => $templates->where('category', $key)->pluck('name')->values(),
                ])
                ->filter(fn ($items) => $items->isNotEmpty());

            return response()->json([
                'slug'         => $slug,
                'asset_type'   => $name,
                'requirements' => $grouped,
            ]);
        }

        $scopeLabels = [
            'project'   => 'Normativa de proyecto',
            'operation' => 'Normativa de operación',
        ];

        $grouped = collect($scopeLabels)
            ->mapWithKeys(fn ($label, $key) => [
                $label => $templates->where('compliance_scope', $key)->pluck('name')->values(),
            ])
            ->filter(fn ($items) => $items->isNotEmpty());

        return response()->json([
            'slug'         => $slug,
            'asset_type'   => $name,
            'requirements' => $grouped->isNotEmpty() ? $grouped : $templates->pluck('name')->values(),
        ]);
    }
}
