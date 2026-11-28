<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class VoucherFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'superadmin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'period' => 'nullable|date_format:Y-m',
            'search' => 'nullable|string|max:100',
            'division' => 'nullable|string|max:100',
            'status' => 'nullable|in:all,qualified,not_qualified,generated,not_generated,redeemed,unredeemed,expired',
            'per_page' => 'nullable|integer|in:10,25,50,100',
        ];
    }

    public function validatedFilters(): array
    {
        $v = $this->validated();
        $today = Carbon::today('Asia/Jakarta');

        return [
            'period' => $v['period'] ?? $today->format('Y-m'),
            'search' => $v['search'] ?? null,
            'division' => $v['division'] ?? null,
            'status' => $v['status'] ?? 'all',
            'per_page' => (int) ($v['per_page'] ?? 15),
        ];
    }
}
