<?php

namespace App\Modules\Tenancy\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required_without:is_active', 'string', Rule::in(['manager', 'member'])],
            'is_active' => ['required_without:role', 'boolean'],
        ];
    }
}
