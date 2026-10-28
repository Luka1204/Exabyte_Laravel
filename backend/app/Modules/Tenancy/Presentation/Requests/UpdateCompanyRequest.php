<?php

namespace App\Modules\Tenancy\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required_without:is_active', 'string', 'max:160'],
            'is_active' => ['required_without:name', 'boolean'],
        ];
    }
}
