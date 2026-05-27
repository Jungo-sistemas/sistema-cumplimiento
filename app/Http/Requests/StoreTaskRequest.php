<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check()
            && (auth()->user()->isAdmin() || auth()->user()->isOperative());
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'title' => ['required', 'string', 'max:160'],

            'type' => ['nullable', 'string', 'in:manual,initial,renewal,checkout,checkin,review'],

            'description' => ['nullable', 'string', 'max:2000'],

            'due_date' => ['nullable', 'date'],

            'responsible_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($user) {
                    if ($user->hasGroupScope()) {
                        $query->where('group_id', $user->group_id);
                    } else {
                        $query->where('company_id', $user->company_id);
                    }
                }),
            ],
        ];
    }
}