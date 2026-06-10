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
        $asset = $this->route('asset');
        $companyId = (int) $asset->company_id;

        return [
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

            'vault_location' => [
                'nullable',
                'string',
                'max:255',
            ],

            'responsible_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
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