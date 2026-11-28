<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VoucherGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'superadmin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'period' => 'nullable|date_format:Y-m',
            'user_id' => 'nullable|integer|exists:users,id',
        ];
    }
}
