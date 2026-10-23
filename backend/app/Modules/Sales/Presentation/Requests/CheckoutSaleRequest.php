<?php

namespace App\Modules\Sales\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CheckoutSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => [
                'required',
                'numeric',
                'decimal:0,3',
                'gt:0',
                'max:9000',
            ],
            'cash_received' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:9999999999.99',
            ],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $items = $this->input('items');
                if (! is_array($items)) {
                    return;
                }

                foreach ($items as $index => $item) {
                    if (! is_array($item) || ! isset($item['quantity'])) {
                        continue;
                    }
                    $quantity = (string) $item['quantity'];
                    if (
                        preg_match('/^\d+(\.\d{1,3})?$/', $quantity)
                        && trim(str_replace('.', '', $quantity), '0') === ''
                    ) {
                        $validator->errors()->add(
                            "items.{$index}.quantity",
                            'La cantidad debe ser mayor a cero.'
                        );
                    }
                }
            },
        ];
    }
}
