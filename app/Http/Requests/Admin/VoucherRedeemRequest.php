<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VoucherRedeemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'superadmin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'voucher_id' => 'nullable|integer|exists:habit_vouchers,id|required_without:voucher_code',
            'voucher_code' => 'nullable|string|max:64|required_without:voucher_id',
            'notes' => 'nullable|string|max:255',
        ];
    }
}
