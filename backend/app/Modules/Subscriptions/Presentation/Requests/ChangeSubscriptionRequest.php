<?php

namespace App\Modules\Subscriptions\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeSubscriptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_period' => ['required', 'string', Rule::in(['monthly', 'annual'])],
        ];
    }
}
