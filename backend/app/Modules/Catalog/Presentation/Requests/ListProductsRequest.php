<?php

namespace App\Modules\Catalog\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
