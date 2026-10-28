<?php

namespace App\Modules\Catalog\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $companyId = (int) $this->attributes->get('active_company_id');
        $productId = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:160'],
            'sku' => [
                'required',
                'string',
                'max:64',
                Rule::unique('products', 'sku')->where(
                    fn ($query) => $query->where('company_id', $companyId)
                )->ignore($productId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('products', 'barcode')->where(
                    fn ($query) => $query->where('company_id', $companyId)
                )->ignore($productId),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query
                        ->where('company_id', $companyId)
                        ->where('is_active', true)
                ),
            ],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'tax_rate' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['name', 'sku', 'barcode'] as $field) {
            if (is_string($this->input($field))) {
                $values[$field] = trim($this->input($field));
            }
        }

        if (($values['barcode'] ?? null) === '') {
            $values['barcode'] = null;
        }

        $this->merge($values);
    }
}
