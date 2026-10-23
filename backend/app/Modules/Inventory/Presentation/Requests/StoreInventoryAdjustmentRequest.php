<?php

namespace App\Modules\Inventory\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $companyId = (int) $this->attributes->get('active_company_id');

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(
                    fn ($query) => $query->where('company_id', $companyId)
                ),
            ],
            'quantity_delta' => [
                'required',
                'numeric',
                'decimal:0,3',
                'min:-99999999999.999',
                'max:99999999999.999',
            ],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    is_scalar($this->input('quantity_delta'))
                    && preg_match('/^[+-]?[\d.]+$/', (string) $this->input('quantity_delta'))
                    && trim(
                        str_replace(['+', '-', '.'], '', (string) $this->input('quantity_delta')),
                        '0'
                    ) === ''
                ) {
                    $validator->errors()->add(
                        'quantity_delta',
                        'La cantidad del ajuste no puede ser cero.'
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['quantity_delta', 'reason'] as $field) {
            if (is_string($this->input($field))) {
                $values[$field] = trim($this->input($field));
            }
        }

        $this->merge($values);
    }
}
