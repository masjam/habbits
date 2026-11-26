<?php

namespace App\Http\Controllers\Koperasi;

use App\Http\Controllers\Controller;
use App\Models\CoopBonPayment;
use App\Models\CoopMember;
use App\Models\CoopTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MemberBonController extends Controller
{
    /**
     * Halaman Manajemen Member Koperasi (Pegawai & Non-Pegawai).
     */
    public function members(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $type = $request->query('type', 'all');

        $rows = collect();

        // 1. Pegawai / Guru (tabel users, tanpa admin & superadmin)
        if (in_array($type, ['all', 'pegawai'], true)) {
            $userQuery = User::nonAdmin()->orderBy('name');
            if ($search !== '') {
                $userQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('nip', 'like', "%{$search}%")
                      ->orWhere('divisi', 'like', "%{$search}%")
                      ->orWhere('rfid_uid', 'like', "%{$search}%");
                });
            }

            $userBonMap = CoopTransaction::whereNotNull('user_id')
                ->whereIn('bon_status', ['unpaid', 'partial'])
                ->selectRaw('user_id, SUM(bon_amount - bon_paid_amount) as active_bon')
                ->groupBy('user_id')
                ->pluck('active_bon', 'user_id');

            $rows = $rows->concat($userQuery->get()->map(fn($u) => [
                'id'           => $u->id,
                'source'       => 'user',
                'member_code'  => $u->nip ?: '-',
                'name'         => $u->name,
                'type'         => 'pegawai',
                'type_label'   => $u->divisi ?: 'Pegawai',
                'phone'        => $u->phone ?? '-',
                'rfid_uid'     => $u->rfid_uid,
                'coop_balance' => (float) $u->coop_balance,
                'bon_limit'    => (float) ($u->coop_bon_limit ?? 0),
                'active_bon'   => (float) ($userBonMap[$u->id] ?? 0),
                'notes'        => null,
                'is_active'    => true,
            ]));
        }

        // 2. Member Non-Pegawai (tabel coop_members)
        if ($type !== 'pegawai') {
            $memberQuery = CoopMember::query()->orderByDesc('created_at');
            if ($type !== 'all') {
                $memberQuery->where('type', $type);
            }
            if ($search !== '') {
                $memberQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('member_code', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('rfid_uid', 'like', "%{$search}%");
                });
            }

            $memberRows = $memberQuery->withSum(['transactions as active_bon' => function ($q) {
                $q->whereIn('bon_status', ['unpaid', 'partial']);
            }], DB::raw('bon_amount - bon_paid_amount'))->get();

            $rows = $rows->concat($memberRows->map(fn($m) => [
                'id'           => $m->id,
                'source'       => 'member',
                'member_code'  => $m->member_code,
                'name'         => $m->name,
                'type'         => $m->type,
                'type_label'   => $m->type_label,
                'phone'        => $m->phone,
                'rfid_uid'     => $m->rfid_uid,
                'coop_balance' => (float) $m->coop_balance,
                'bon_limit'    => (float) $m->bon_limit,
                'active_bon'   => (float) ($m->active_bon ?? 0),
                'notes'        => $m->notes,
                'is_active'    => (bool) $m->is_active,
            ]));
        }

        $perPage = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $sorted = $rows->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $members = (new LengthAwarePaginator(
            $sorted->forPage($currentPage, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        ));

        $stats = [
            'total_members' => User::nonAdmin()->count() + CoopMember::where('is_active', true)->count(),
            'total_balance' => (float) User::nonAdmin()->sum('coop_balance') + (float) CoopMember::where('is_active', true)->sum('coop_balance'),
            'total_bon'     => (float) CoopTransaction::whereIn('bon_status', ['unpaid', 'partial'])
                ->sum(DB::raw('bon_amount - bon_paid_amount')),
        ];

        return Inertia::render('Koperasi/POS/Members', [
            'members' => $members,
            'stats'   => $stats,
            'filters' => [
                'search' => $search,
                'type'   => $type,
            ],
        ]);
    }

    /**
     * Tambah atau Update Member Non-Pegawai.
     */
    public function storeMember(Request $request)
    {
        $id = $request->input('id');

        $validated = $request->validate([
            'id'          => 'nullable|exists:coop_members,id',
            'member_code' => ['nullable', 'string', 'max:40', Rule::unique('coop_members', 'member_code')->ignore($id)],
            'name'        => 'required|string|max:255',
            'type'        => 'required|in:wali_murid,siswa,umum',
            'phone'       => 'nullable|string|max:30',
            'rfid_uid'    => ['nullable', 'string', 'max:100', Rule::unique('coop_members', 'rfid_uid')->ignore($id)],
            'bon_limit'   => 'nullable|numeric|min:0',
            'notes'       => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ]);

        if (empty($validated['member_code'])) {
            $nextNumber = (CoopMember::max('id') ?? 0) + 1;
            $validated['member_code'] = 'MBR-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
        }

        $validated['bon_limit'] = (float) ($validated['bon_limit'] ?? 0);
        $validated['rfid_uid'] = $validated['rfid_uid'] ? trim($validated['rfid_uid']) : null;

        if ($id) {
            $member = CoopMember::findOrFail($id);
            $member->update($validated);
            $msg = "Data member {$member->name} berhasil diperbarui.";
        } else {
            $member = CoopMember::create($validated);
            $msg = "Member baru {$member->name} ({$member->member_code}) berhasil ditambahkan.";
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Topup Saldo Kartu / Deposit Member Non-Pegawai.
     */
    public function topupMember(Request $request, CoopMember $member)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1000',
            'notes'  => 'nullable|string|max:255',
        ]);

        $amount = (float) $validated['amount'];
        $member->addCoopBalance(
            $amount,
            'topup',
            'TOPUP-' . now()->format('YmdHis'),
            $validated['notes'] ?: "Topup saldo member {$member->name}",
            auth()->user()
        );

        return redirect()->back()->with('success', "Topup Rp " . number_format($amount, 0, ',', '.') . " untuk {$member->name} berhasil.");
    }

    /**
     * Topup Saldo Kartu RFID Pegawai.
     */
    public function topupUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1000',
            'notes'  => 'nullable|string|max:255',
        ]);

        $amount = (float) $validated['amount'];
        $user->addCoopBalance(
            $amount,
            'topup',
            'TOPUP-' . now()->format('YmdHis'),
            $validated['notes'] ?: "Topup saldo RFID pegawai {$user->name}",
            auth()->user()
        );

        return redirect()->back()->with('success', "Topup Rp " . number_format($amount, 0, ',', '.') . " untuk {$user->name} berhasil.");
    }

    /**
     * Update Batas Maksimal BON (Piutang) Pegawai.
     */
    public function updateUserBonLimit(Request $request, User $user)
    {
        $validated = $request->validate([
            'bon_limit' => 'required|numeric|min:0',
        ]);

        $limit = (float) $validated['bon_limit'];
        $user->update(['coop_bon_limit' => $limit]);

        $limitText = $limit > 0 ? 'Rp ' . number_format($limit, 0, ',', '.') : 'Tanpa batas';
        return redirect()->back()->with('success', "Limit BON {$user->name} berhasil diubah menjadi {$limitText}.");
    }

    /**
     * Nonaktifkan atau hapus member jika belum pernah bertransaksi.
     */
    public function destroyMember(CoopMember $member)
    {
        if ($member->transactions()->exists()) {
            $member->update(['is_active' => false]);
            return redirect()->back()->with('success', "Member {$member->name} dinonaktifkan (riwayat transaksi tetap tersimpan).");
        }

        $member->delete();
        return redirect()->back()->with('success', "Member {$member->name} berhasil dihapus.");
    }

    /**
     * Halaman Daftar BON / Piutang Belanja Koperasi.
     */
    public function bonIndex(Request $request)
    {
        $status = $request->query('status', 'unpaid'); // unpaid (belum lunas: unpaid+partial), paid, all
        $search = trim((string) $request->query('search', ''));

        $query = CoopTransaction::with(['customer', 'member', 'cashier', 'bonPayments.operator'])
            ->where('bon_amount', '>', 0)
            ->orderByDesc('created_at');

        if ($status === 'unpaid') {
            $query->whereIn('bon_status', ['unpaid', 'partial']);
        } elseif ($status === 'paid') {
            $query->where('bon_status', 'paid');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_code', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('nip', 'like', "%{$search}%"))
                  ->orWhereHas('member', fn($mq) => $mq->where('name', 'like', "%{$search}%")->orWhere('member_code', 'like', "%{$search}%"));
            });
        }

        $transactions = $query->paginate(20)->withQueryString();

        $transactions->getCollection()->transform(function ($t) {
            $isMember = $t->member_id !== null;
            $remaining = max(0, (float) $t->bon_amount - (float) $t->bon_paid_amount);

            return [
                'id'               => $t->id,
                'transaction_code' => $t->transaction_code,
                'customer_type'    => $isMember ? 'member' : 'user',
                'customer_id'      => $isMember ? $t->member_id : $t->user_id,
                'customer_name'    => $t->customer?->name ?? $t->member?->name ?? 'Pelanggan Umum',
                'customer_code'    => $t->customer?->nip ?? $t->member?->member_code ?? '-',
                'customer_group'   => $t->customer ? ($t->customer->divisi ?: 'Pegawai') : ($t->member?->type_label ?? 'Member'),
                'customer_balance' => (float) ($t->customer?->coop_balance ?? $t->member?->coop_balance ?? 0),
                'bon_amount'       => (float) $t->bon_amount,
                'bon_paid_amount'  => (float) $t->bon_paid_amount,
                'remaining_amount' => $remaining,
                'bon_status'       => $t->bon_status,
                'items'            => $t->items,
                'created_at'       => $t->created_at->format('d M Y, H:i'),
                'cashier_name'     => $t->cashier?->name ?: 'Kasir',
                'payments'         => $t->bonPayments->map(fn($p) => [
                    'amount'         => (float) $p->amount,
                    'payment_method' => $p->payment_method,
                    'paid_at'        => $p->paid_at->format('d M Y, H:i'),
                    'operator_name'  => $p->operator?->name ?: 'Kasir',
                    'notes'          => $p->notes,
                ]),
            ];
        });

        $activeBonQuery = CoopTransaction::where('bon_amount', '>', 0)->whereIn('bon_status', ['unpaid', 'partial']);
        $totalActiveBon = (float) (clone $activeBonQuery)->sum(DB::raw('bon_amount - bon_paid_amount'));
        $totalPaidBon = (float) CoopTransaction::where('bon_amount', '>', 0)->sum('bon_paid_amount');
        $activeDebtorCount = (clone $activeBonQuery)->count();

        return Inertia::render('Koperasi/POS/Bon', [
            'transactions' => $transactions,
            'stats'        => [
                'total_active_bon'    => $totalActiveBon,
                'total_paid_bon'      => $totalPaidBon,
                'active_transactions' => $activeDebtorCount,
            ],
            'filters'      => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Bayar / Cicil satu transaksi BON.
     */
    public function payBon(Request $request, CoopTransaction $transaction)
    {
        $validated = $request->validate([
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:cash,qris,rfid_card',
            'notes'          => 'nullable|string|max:255',
        ]);

        if (!in_array($transaction->bon_status, ['unpaid', 'partial'], true)) {
            return redirect()->back()->with('error', 'Transaksi BON ini sudah lunas.');
        }

        $remaining = max(0, (float) $transaction->bon_amount - (float) $transaction->bon_paid_amount);
        $payAmount = min((float) $validated['amount'], $remaining);
        $cashier = auth()->user();

        DB::beginTransaction();
        try {
            if ($validated['payment_method'] === 'rfid_card') {
                $owner = $transaction->customer ?? $transaction->member;
                if (!$owner || (float) $owner->coop_balance < $payAmount) {
                    DB::rollBack();
                    return redirect()->back()->with('error', 'Saldo Kartu RFID pelanggan tidak mencukupi untuk pelunasan BON.');
                }

                $owner->deductCoopBalance(
                    $payAmount,
                    'bon_payment',
                    $transaction->transaction_code,
                    "Pelunasan BON {$transaction->transaction_code}",
                    $cashier
                );
            }

            CoopBonPayment::create([
                'transaction_id' => $transaction->id,
                'user_id'        => $transaction->user_id,
                'member_id'      => $transaction->member_id,
                'amount'         => $payAmount,
                'payment_method' => $validated['payment_method'],
                'notes'          => $validated['notes'] ?: 'Pembayaran BON Koperasi',
                'operator_id'    => $cashier->id,
                'paid_at'        => now(),
            ]);

            $newPaid = (float) $transaction->bon_paid_amount + $payAmount;
            $newStatus = $newPaid >= (float) $transaction->bon_amount ? 'paid' : 'partial';

            $transaction->update([
                'bon_paid_amount' => $newPaid,
                'bon_status'      => $newStatus,
            ]);

            DB::commit();
            return redirect()->back()->with('success', "Pembayaran BON {$transaction->transaction_code} sebesar Rp " . number_format($payAmount, 0, ',', '.') . " berhasil dicatat.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses pembayaran BON: ' . $e->getMessage());
        }
    }

    /**
     * Lunasi seluruh BON aktif milik satu Pegawai atau Member sekaligus.
     */
    public function payBulkBon(Request $request)
    {
        $validated = $request->validate([
            'customer_type'  => 'required|in:user,member',
            'customer_id'    => 'required|integer',
            'payment_method' => 'required|in:cash,qris',
            'notes'          => 'nullable|string|max:255',
        ]);

        $column = $validated['customer_type'] === 'member' ? 'member_id' : 'user_id';
        $unpaidList = CoopTransaction::where($column, $validated['customer_id'])
            ->whereIn('bon_status', ['unpaid', 'partial'])
            ->get();

        if ($unpaidList->isEmpty()) {
            return redirect()->back()->with('error', 'Tidak ada BON aktif untuk pelanggan tersebut.');
        }

        $cashier = auth()->user();
        $totalSettled = 0;

        DB::beginTransaction();
        try {
            foreach ($unpaidList as $trx) {
                $remaining = max(0, (float) $trx->bon_amount - (float) $trx->bon_paid_amount);
                if ($remaining <= 0) {
                    continue;
                }

                CoopBonPayment::create([
                    'transaction_id' => $trx->id,
                    'user_id'        => $trx->user_id,
                    'member_id'      => $trx->member_id,
                    'amount'         => $remaining,
                    'payment_method' => $validated['payment_method'],
                    'notes'          => $validated['notes'] ?: 'Pelunasan Sekaligus BON Koperasi',
                    'operator_id'    => $cashier->id,
                    'paid_at'        => now(),
                ]);

                $trx->update([
                    'bon_paid_amount' => $trx->bon_amount,
                    'bon_status'      => 'paid',
                ]);

                $totalSettled += $remaining;
            }

            DB::commit();
            return redirect()->back()->with('success', "Seluruh BON berhasil dilunasi! Total: Rp " . number_format($totalSettled, 0, ',', '.'));
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal melunasi BON: ' . $e->getMessage());
        }
    }
}
