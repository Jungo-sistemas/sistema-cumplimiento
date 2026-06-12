<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isOperative();
    }

    public function rules(): array
    {
        $user = $this->user();

        $companyId = $this->filled('company_id')
            ? (int) $this->input('company_id')
            : (int) $user->company_id;

        $companyRule = Rule::exists('companies', 'id');

        if ($user->hasGroupScope()) {
            $companyRule = $companyRule->where(function ($query) use ($user) {
                $query->where('group_id', $user->group_id);
            });
        } else {
            $companyRule = $companyRule->where(function ($query) use ($user) {
                $query->where('id', $user->company_id);
            });
        }

        return [
            'company_id' => [
                'required',
                'integer',
                $companyRule,
            ],

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

            'parent_asset_id' => [
                'nullable',
                'integer',
                Rule::exists('assets', 'id')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                }),
            ],

            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('assets', 'code')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
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
                Rule::exists('users', 'id')->where(function ($query) use ($companyId, $user) {
                    if ($user->isGlobalScope()) {
                        return;
                    }
                    if ($user->hasGroupScope()) {
                        $groupId = \App\Models\Company::find($companyId)?->group_id ?? $user->group_id;
                        $query->where('group_id', $groupId);
                    } else {
                        $query->where('company_id', $user->company_id);
                    }
                }),
            ],

            'compliance_start_date' => ['required', 'date'],
            'compliance_due_date'   => ['required', 'date', 'after_or_equal:compliance_start_date'],

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
        $data = [];

        if ($this->has('location')) {
            $data['location'] = strtoupper(trim((string) $this->location));
        }

        if (! $this->filled('company_id') && $this->user()) {
            $data['company_id'] = (int) $this->user()->company_id;
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }
}