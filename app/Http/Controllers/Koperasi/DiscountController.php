<?php

namespace App\Http\Controllers\Koperasi;

use App\Http\Controllers\Controller;
use App\Models\CoopDiscount;
use App\Models\CoopProduct;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', 'all');

        $query = CoopDiscount::query()->orderByDesc('is_active')->orderByDesc('created_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('product_category', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->activeOn();
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $discounts = $query->paginate(20)->withQueryString();
        $categories = CoopProduct::distinct()->pluck('category')->filter()->sort()->values()->all();

        return Inertia::render('Koperasi/POS/Discounts', [
            'discounts'  => $discounts,
            'categories' => $categories,
            'stats'      => [
                'total'      => CoopDiscount::count(),
                'active_now' => CoopDiscount::activeOn()->count(),
                'event_only' => CoopDiscount::whereNotNull('start_date')->orWhereNotNull('end_date')->count(),
            ],
            'filters'    => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateDiscount($request);
        $data['created_by'] = auth()->id();

        CoopDiscount::create($data);

        return redirect()->back()->with('success', "Promo diskon \"{$data['name']}\" berhasil ditambahkan.");
    }

    public function update(Request $request, CoopDiscount $discount)
    {
        $data = $this->validateDiscount($request);

        $discount->update($data);

        return redirect()->back()->with('success', "Promo diskon \"{$discount->name}\" berhasil diperbarui.");
    }

    public function toggle(CoopDiscount $discount)
    {
        $discount->update(['is_active' => !$discount->is_active]);

        $label = $discount->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->back()->with('success', "Promo \"{$discount->name}\" berhasil {$label}.");
    }

    public function destroy(CoopDiscount $discount)
    {
        $name = $discount->name;
        $discount->delete();

        return redirect()->back()->with('success', "Promo \"{$name}\" berhasil dihapus.");
    }

    private function validateDiscount(Request $request): array
    {
        $rules = [
            'name'             => 'required|string|max:120',
            'discount_type'    => 'required|in:percentage,fixed',
            'discount_value'   => [
                'required',
                'numeric',
                'min:0.01',
                $request->input('discount_type') === 'percentage' ? 'max:100' : 'max:100000000',
            ],
            'max_discount'     => 'nullable|numeric|min:0',
            'min_purchase'     => 'nullable|numeric|min:0',
            'member_type'      => 'nullable|in:pegawai,wali_murid,siswa,umum,registered',
            'product_category' => 'nullable|string|max:100',
            'start_date'       => 'nullable|date',
            'end_date'         => 'nullable|date|after_or_equal:start_date',
            'is_active'        => 'boolean',
            'notes'            => 'nullable|string|max:255',
        ];

        $validated = $request->validate($rules);
        $validated['min_purchase'] = (float) ($validated['min_purchase'] ?? 0);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['member_type'] = $validated['member_type'] ?: null;
        $validated['product_category'] = $validated['product_category'] ? trim($validated['product_category']) : null;

        if ($validated['discount_type'] === 'fixed') {
            $validated['max_discount'] = null;
        }

        return $validated;
    }
}
