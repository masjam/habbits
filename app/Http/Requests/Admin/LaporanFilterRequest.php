<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class LaporanFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'superadmin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2024|max:2035',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|in:10,25,50,100',
            'kategori_skor' => 'nullable|in:0-30,30-50,50-target,tercapai',
            'divisi' => 'nullable|string|max:100',
            'sort' => 'nullable|in:skor,name',
            'dir' => 'nullable|in:asc,desc',
            'type' => 'nullable|in:excel,pdf',
        ];
    }

    public function validatedFilters(): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $v = $this->validated();

        return [
            'month' => (int) ($v['month'] ?? $now->month),
            'year' => (int) ($v['year'] ?? $now->year),
            'search' => $v['search'] ?? null,
            'per_page' => (int) ($v['per_page'] ?? 10),
            'kategori_skor' => $v['kategori_skor'] ?? null,
            'divisi' => $v['divisi'] ?? null,
            'sort' => $v['sort'] ?? 'skor',
            'dir' => $v['dir'] ?? 'desc',
            'type' => $v['type'] ?? 'excel',
        ];
    }
}
