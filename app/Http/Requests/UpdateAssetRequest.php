<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isOperative();
    }

    public function rules(): array
    {
        $asset     = $this->route('asset');
        $user      = $this->user();

        // Use the submitted company_id if present (admin changing company), otherwise keep current
        $companyId = $this->filled('company_id')
            ? (int) $this->input('company_id')
            : (int) $asset->company_id;

        $companyRule = Rule::exists('companies', 'id');
        if ($user->hasGroupScope()) {
            $companyRule = $companyRule->where('group_id', $user->group_id);
        } elseif (! $user->isGlobalScope()) {
            $companyRule = $companyRule->where('id', $user->company_id);
        }

        return [
            'company_id' => ['nullable', 'integer', $companyRule],

            'asset_type_id' => [
                'required',
                'integer',
                Rule::exists('asset_types', 'id'),
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('assets', 'code')
                    ->ignore($asset->id)
                    ->where(function ($query) use ($companyId) {
                        $query->where('company_id', $companyId);
                    }),
            ],

            'parent_asset_id' => [
                'nullable',
                'integer',
                Rule::exists('assets', 'id')->where(function ($query) use ($companyId, $asset) {
                    $query->where('company_id', $companyId)
                          ->where('id', '!=', $asset->id);
                }),
            ],

            'location' => [
                'nullable',
                'string',
                'max:255',
            ],

            'street_address' => ['nullable', 'string', 'max:255'],
            'colonia' => ['nullable', 'string', 'max:150'],
            'municipality' => ['nullable', 'string', 'max:150'],
            'postal_code' => ['nullable', 'string', 'max:10'],

            'responsible_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($companyId, $user) {
                    if ($user->isGlobalScope()) {
                        // Global: cualquier usuario activo
                        return;
                    }
                    if ($user->hasGroupScope()) {
                        // Admin de grupo: usuarios de cualquier empresa del grupo o admins del grupo
                        $groupId = \App\Models\Company::find($companyId)?->group_id ?? $user->group_id;
                        $query->where(function ($q) use ($groupId) {
                            $q->where('group_id', $groupId);
                        });
                    } else {
                        // Operativo: solo usuarios de su empresa
                        $query->where('company_id', $user->company_id);
                    }
                }),
            ],

            // Vehicle-specific fields
            'no_economico'     => ['nullable', 'string', 'max:100'],
            'numero_serie'     => ['nullable', 'string', 'max:100'],
            'marca'            => ['nullable', 'string', 'max:100'],
            'modelo'           => ['nullable', 'string', 'max:100'],
            'placas'           => ['nullable', 'string', 'max:20'],
            'marca_recipiente' => ['nullable', 'string', 'max:100'],
            'capacidad_litros' => ['nullable', 'integer', 'min:1'],
            'serie_recipiente' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('location')) {
            $this->merge([
                'location' => strtoupper(trim((string) $this->location)),
            ]);
        }
    }
}