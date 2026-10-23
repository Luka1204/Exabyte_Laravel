<?php

namespace App\Modules\Identity\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefreshSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string', 'size:64'],
        ];
    }
}
