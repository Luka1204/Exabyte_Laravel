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
            'role' => ['sometimes', 'string', Rule::in(['manager', 'member'])],
            'is_active' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array', 'max:8'],
            'permissions.*' => ['string', Rule::in([
                'catalog.view', 'catalog.manage', 'inventory.view', 'inventory.manage',
                'sales.operate', 'sales.manage', 'reports.view', 'activity.view',
            ])],
        ];
    }
}
