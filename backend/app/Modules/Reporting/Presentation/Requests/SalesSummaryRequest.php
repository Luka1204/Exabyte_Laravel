<?php

namespace App\Modules\Reporting\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->input('from', now()->subDays(29)->toDateString()),
            'to' => $this->input('to', now()->toDateString()),
        ]);
    }
}
