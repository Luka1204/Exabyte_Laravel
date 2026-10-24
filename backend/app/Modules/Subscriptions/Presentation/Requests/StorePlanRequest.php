<?php

namespace App\Modules\Subscriptions\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    public function rules(): array
    {
        $planId = $this->route('plan');

        return [
            'code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('plans', 'code')->ignore($planId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'benefits' => ['required', 'array', 'max:30'],
            'benefits.*' => ['required', 'string', 'max:240'],
            'currency' => ['required', 'string', 'size:3', Rule::in(['ARS'])],
            'monthly_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'annual_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'limits' => ['required', 'array'],
            'limits.users' => ['required', 'integer', 'min:1', 'max:1000000'],
            'limits.branches' => ['required', 'integer', 'min:1', 'max:1000000'],
            'limits.cash_registers' => ['required', 'integer', 'min:1', 'max:1000000'],
            'limits.products' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'entitlements' => ['required', 'array'],
            'entitlements.*' => ['required', 'boolean'],
            'trial_days' => ['required', 'integer', Rule::in([0])],
            'grace_days' => ['required', 'integer', 'between:0,90'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
