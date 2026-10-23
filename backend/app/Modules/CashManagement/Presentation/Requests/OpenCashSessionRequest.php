<?php

namespace App\Modules\CashManagement\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'opening_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ];
    }
}
