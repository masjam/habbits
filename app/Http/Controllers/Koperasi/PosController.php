<?php

namespace App\Http\Controllers\Koperasi;

use App\Exports\CoopMonthlySalesReport;
use App\Exports\CoopProductTemplateExport;
use App\Exports\CoopStockOpnameReport;
use App\Http\Controllers\Controller;
use App\Imports\CoopProductImport;
use App\Models\CoopBalanceLog;
use App\Models\CoopMember;
use App\Models\CoopProduct;
use App\Models\CoopStockLog;
use App\Models\CoopTransaction;
use App\Models\HabitVoucher;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PosController extends Controller
{
    /**
     * Ambil informasi identitas toko koperasi untuk POS dan struk belanja.
     */
    private function storeInfo(): array
    {
        return [
            'name'           => Setting::get('koperasi_name', 'Koperasi Rabbani'),
            'address'        => Setting::get('koperasi_address', 'Jl. Raden Bagus No. 1, Krian, Sidoarjo, Jawa Timur'),
            'phone'          => Setting::get('koperasi_phone', '0812-3456-7890'),
            'receipt_footer' => Setting::get('koperasi_receipt_footer', "Terima kasih telah berbelanja di Koperasi Rabbani.\nSemoga berkah & bernilai ibadah."),
        ];
    }

    /**
     * Tampilkan halaman utama Sistem POS Kasir Koperasi Rabbani.
     */
    public function index(Request $request)
    {
        $today = Carbon::today();
        $cashier = auth()->user();

        // 1. Ambil katalog produk koperasi aktif
        $products = CoopProduct::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $categories = CoopProduct::where('is_active', true)
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values()
            ->toArray();

        // 2. Statistik Kasir Hari Ini
        $todayTrxQuery = CoopTransaction::whereDate('created_at', $today);
        $todaySalesCount = (clone $todayTrxQuery)->count();
        $todayTotalGross = (clone $todayTrxQuery)->sum('total_amount');
        $todayVoucherAmount = (clone $todayTrxQuery)->sum('voucher_amount');
        $todayRfidAmount = (clone $todayTrxQuery)->sum('rfid_paid_amount');
        $todayCashAmount = (clone $todayTrxQuery)->sum('cash_paid_amount');

        // Tambahkan juga jika ada voucher redeemed langsung lama
        $legacyVoucherCount = HabitVoucher::whereNotNull('claimed_at')
            ->whereDate('claimed_at', $today)
            ->count();

        // 3. Transaksi Terakhir Kasir
        $recentTransactions = CoopTransaction::with(['customer', 'cashier'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($t) {
                return [
                    'id'               => $t->id,
                    'transaction_code' => $t->transaction_code,
                    'customer_name'    => $t->customer?->name ?: 'Umum / Tunai',
                    'customer_nip'     => $t->customer?->nip ?: '-',
                    'customer_divisi'  => $t->customer?->divisi ?: '-',
                    'payment_method'   => $t->payment_method,
                    'total_amount'     => (float) $t->total_amount,
                    'discount_amount'  => (float) $t->discount_amount,
                    'voucher_amount'   => (float) $t->voucher_amount,
                    'rfid_paid_amount' => (float) $t->rfid_paid_amount,
                    'cash_paid_amount' => (float) $t->cash_paid_amount,
                    'change_amount'    => (float) $t->change_amount,
                    'items_count'      => is_array($t->items) ? count($t->items) : 1,
                    'items'            => $t->items,
                    'created_at'       => $t->created_at->format('d M Y, H:i'),
                    'time_only'        => $t->created_at->format('H:i:s'),
                    'cashier_name'     => $t->cashier?->name ?: 'Kasir',
                ];
            });

        // 4. Informasi Koperasi
        $storeInfo = $this->storeInfo();

        return Inertia::render('Koperasi/POS/Index', [
            'cashier'            => [
                'id'    => $cashier->id,
                'name'  => $cashier->name,
                'email' => $cashier->email,
                'role'  => $cashier->getRoleNames()->first() ?? 'Petugas Koperasi',
                'is_admin' => $cashier->hasRole('superadmin'),
            ],
            'products'           => $products,
            'categories'         => $categories,
            'todayStats'         => [
                'sales_count'     => $todaySalesCount,
                'total_gross'     => (float) $todayTotalGross,
                'voucher_used'    => (float) $todayVoucherAmount,
                'rfid_used'       => (float) $todayRfidAmount,
                'cash_received'   => (float) $todayCashAmount,
                'voucher_claimed' => max($todaySalesCount, $legacyVoucherCount),
                'date'            => $today->locale('id')->isoFormat('dddd, D MMMM Y'),
            ],
            'recentTransactions' => $recentTransactions,
            'storeInfo'          => $storeInfo,
            'lastReceipt'        => session('lastReceipt'),
        ]);
    }

    /**
     * Scan / Cari Pegawai Berdasarkan Kartu RFID (Kartu Presensi Guru) atau NIP.
     */
    public function scanRfid(Request $request): JsonResponse
    {
        $input = trim((string) $request->input('rfid_uid'));

        if (!$input) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan tap kartu RFID atau masukkan NIP.',
            ], 422);
        }

        $user = User::nonAdmin()
            ->where(function ($q) use ($input) {
                $q->where('rfid_uid', $input)
                  ->orWhere('nip', $input);
            })
            ->first();

        // Jika belum ketemu, coba cari nama pegawai
        if (!$user && strlen($input) >= 3) {
            $user = User::nonAdmin()->where('name', 'like', "%{$input}%")->first();
        }

        if ($user) {
            // Ambil voucher aktif milik pegawai ini (jika ada)
            $activeVouchers = HabitVoucher::where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNull('claimed_at')
                ->get()
                ->map(fn($v) => [
                    'id'           => $v->id,
                    'code'         => $v->voucher_code,
                    'title'        => $v->title,
                    'reward_title' => $v->reward_title,
                    'reward_value' => $v->reward_value,
                    'reward_num'   => $v->getRewardNumericValue(),
                    'period'       => $v->period,
                    'valid_until'  => $v->valid_until ? $v->valid_until->format('d M Y') : '-',
                ]);

            $currentBon = (float) CoopTransaction::where('user_id', $user->id)
                ->whereIn('bon_status', ['unpaid', 'partial'])
                ->sum(DB::raw('bon_amount - bon_paid_amount'));

            return response()->json([
                'success'  => true,
                'message'  => "Kartu RFID milik {$user->name} berhasil terdeteksi!",
                'customer' => [
                    'customer_type'     => 'user',
                    'id'                => $user->id,
                    'name'              => $user->name,
                    'nip'               => $user->nip ?: '-',
                    'divisi'            => $user->divisi ?: 'Pegawai',
                    'rfid_uid'          => $user->rfid_uid ?: '-',
                    'coop_balance'      => (float) $user->coop_balance,
                    'formatted_balance' => 'Rp ' . number_format($user->coop_balance, 0, ',', '.'),
                    'bon_limit'         => (float) ($user->coop_bon_limit ?? 0),
                    'current_bon'       => $currentBon,
                    'active_vouchers'   => $activeVouchers,
                ],
            ]);
        }

        // Cari pada Member Non-Pegawai (Wali Murid / Siswa / Umum)
        $member = CoopMember::where('is_active', true)
            ->where(function ($q) use ($input) {
                $q->where('rfid_uid', $input)
                  ->orWhere('member_code', $input)
                  ->orWhere('phone', $input);
                if (strlen($input) >= 3) {
                    $q->orWhere('name', 'like', "%{$input}%");
                }
            })
            ->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => "Kartu RFID, Pegawai, atau Member '{$input}' tidak terdaftar di sistem.",
            ], 404);
        }

        $currentBon = (float) CoopTransaction::where('member_id', $member->id)
            ->whereIn('bon_status', ['unpaid', 'partial'])
            ->sum(DB::raw('bon_amount - bon_paid_amount'));

        return response()->json([
            'success'  => true,
            'message'  => "Member {$member->name} ({$member->type_label}) berhasil terdeteksi!",
            'customer' => [
                'customer_type'     => 'member',
                'id'                => $member->id,
                'name'              => $member->name,
                'nip'               => $member->member_code,
                'divisi'            => $member->type_label,
                'rfid_uid'          => $member->rfid_uid ?: '-',
                'coop_balance'      => (float) $member->coop_balance,
                'formatted_balance' => 'Rp ' . number_format($member->coop_balance, 0, ',', '.'),
                'bon_limit'         => (float) $member->bon_limit,
                'current_bon'       => $currentBon,
                'active_vouchers'   => [],
            ],
        ]);
    }

    /**
     * API Cek / Scan Voucher Apresiasi Habit.
     */
    public function checkVoucher(Request $request): JsonResponse
    {
        $queryInput = trim((string) $request->input('code'));

        if (!$queryInput) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan masukkan atau scan kode voucher.',
            ], 422);
        }

        // Parse QR payload JSON jika diperlukan
        if (str_starts_with($queryInput, '{') && str_ends_with($queryInput, '}')) {
            $decoded = json_decode($queryInput, true);
            if (is_array($decoded) && !empty($decoded['code'])) {
                $queryInput = trim($decoded['code']);
            }
        }

        $voucher = HabitVoucher::with('user')
            ->where(function ($q) use ($queryInput) {
                $q->where('voucher_code', $queryInput)
                  ->orWhere('id', $queryInput);
            })
            ->first();

        // Cari berdasarkan NIP jika belum ketemu
        if (!$voucher) {
            $voucher = HabitVoucher::with('user')
                ->whereHas('user', function ($uq) use ($queryInput) {
                    $uq->where('nip', $queryInput)
                       ->orWhere('rfid_uid', $queryInput);
                })
                ->where('status', 'active')
                ->whereNull('claimed_at')
                ->latest()
                ->first();
        }

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'status'  => 'not_found',
                'message' => "Voucher dengan kata kunci '{$queryInput}' tidak ditemukan.",
            ], 404);
        }

        if ($voucher->isExpired()) {
            return response()->json([
                'success' => false,
                'status'  => 'expired',
                'message' => "Voucher {$voucher->voucher_code} telah kedaluwarsa pada " . ($voucher->valid_until ? $voucher->valid_until->format('d M Y') : '-') . ".",
                'voucher' => [
                    'code'        => $voucher->voucher_code,
                    'user_name'   => $voucher->user?->name,
                    'valid_until' => $voucher->valid_until ? $voucher->valid_until->format('d M Y') : '-',
                ],
            ], 400);
        }

        if ($voucher->isRedeemed()) {
            $redeemedBy = $voucher->redeemer?->name ?: 'Petugas Koperasi';
            $claimedAt = $voucher->claimed_at ? $voucher->claimed_at->format('d M Y, H:i') : '-';
            return response()->json([
                'success' => false,
                'status'  => 'already_redeemed',
                'message' => "Voucher {$voucher->voucher_code} sudah pernah digunakan pada {$claimedAt} ({$voucher->redemption_notes}).",
                'voucher' => [
                    'id'               => $voucher->id,
                    'code'             => $voucher->voucher_code,
                    'user_name'        => $voucher->user?->name,
                    'user_nip'         => $voucher->user?->nip ?: '-',
                    'claimed_at'       => $claimedAt,
                    'cashier_name'     => $redeemedBy,
                    'transaction_code' => $voucher->transaction_code ?: 'TRX-' . $voucher->voucher_code,
                ],
            ], 400);
        }

        $numericValue = $voucher->getRewardNumericValue();

        return response()->json([
            'success' => true,
            'status'  => 'valid',
            'message' => "Voucher {$voucher->voucher_code} valid senilai Rp " . number_format($numericValue, 0, ',', '.') . "!",
            'voucher' => [
                'id'                  => $voucher->id,
                'code'                => $voucher->voucher_code,
                'title'               => $voucher->title,
                'reward_title'        => $voucher->reward_title,
                'reward_value'        => $voucher->reward_value,
                'reward_numeric'      => $numericValue,
                'formatted_value'     => 'Rp ' . number_format($numericValue, 0, ',', '.'),
                'period'              => $voucher->period,
                'achieved_percentage' => $voucher->achieved_percentage,
                'target_percentage'   => $voucher->target_percentage,
                'valid_until'         => $voucher->valid_until ? $voucher->valid_until->format('d M Y') : '-',
                'user'                => [
                    'id'           => $voucher->user?->id,
                    'name'         => $voucher->user?->name,
                    'nip'          => $voucher->user?->nip ?: '-',
                    'divisi'       => $voucher->user?->divisi ?: '-',
                    'rfid_uid'     => $voucher->user?->rfid_uid ?: '-',
                    'coop_balance' => (float) $voucher->user?->coop_balance,
                ],
            ],
        ]);
    }

    /**
     * Konversi / Transfer Voucher ke Saldo Kartu RFID Presensi Guru oleh Kasir.
     */
    public function convertVoucherToRfid(Request $request): JsonResponse
    {
        $request->validate([
            'voucher_code' => 'required|string',
            'user_id'      => 'nullable|exists:users,id',
        ]);

        $code = trim($request->voucher_code);
        $voucher = HabitVoucher::with('user')
            ->where('voucher_code', $code)
            ->first();

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => "Voucher {$code} tidak ditemukan.",
            ], 404);
        }

        if ($voucher->isRedeemed()) {
            return response()->json([
                'success' => false,
                'message' => "Voucher {$code} sudah pernah digunakan atau dikonversi.",
            ], 400);
        }

        if ($voucher->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => "Voucher {$code} telah kedaluwarsa.",
            ], 400);
        }

        $user = $voucher->user;
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pemilik voucher tidak ditemukan.',
            ], 404);
        }

        $cashier = auth()->user();
        $success = $user->convertVoucherToBalance($voucher, $cashier);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mentransfer voucher ke saldo kartu.',
            ], 500);
        }

        $user->refresh();
        $rewardNum = $voucher->getRewardNumericValue();

        return response()->json([
            'success'           => true,
            'message'           => "Alhamdulillah! Voucher {$voucher->voucher_code} senilai Rp " . number_format($rewardNum, 0, ',', '.') . " berhasil dimasukkan ke Saldo Kartu RFID Presensi milik {$user->name}!",
            'new_balance'       => (float) $user->coop_balance,
            'formatted_balance' => 'Rp ' . number_format($user->coop_balance, 0, ',', '.'),
            'customer'          => [
                'id'             => $user->id,
                'name'           => $user->name,
                'nip'            => $user->nip ?: '-',
                'coop_balance'   => (float) $user->coop_balance,
                'formatted_balance' => 'Rp ' . number_format($user->coop_balance, 0, ',', '.'),
            ],
        ]);
    }

    /**
     * Proses Transaksi Belanja POS Koperasi (Checkout Keranjang).
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'nullable',
            'items.*.name'     => 'required|string',
            'items.*.price'    => 'required|numeric|min:0',
            'items.*.qty'      => 'required|integer|min:1',
            'items.*.subtotal' => 'required|numeric|min:0',
            'payment_method'   => 'required|in:cash,rfid_card,voucher,split,qris,bon',
            'customer_type'    => 'nullable|in:user,member',
            'customer_id'      => 'nullable|integer',
            'voucher_code'     => 'nullable|string',
            'cash_paid'        => 'nullable|numeric|min:0',
            'notes'            => 'nullable|string|max:255',
        ]);

        $cashier = auth()->user();
        $items = $request->input('items', []);
        $totalGross = (float) collect($items)->sum('subtotal');
        $paymentMethod = $request->payment_method;

        $voucher = null;
        $voucherAmount = 0.0;

        // 1. Validasi jika ada Voucher Habit yang digunakan
        if ($request->filled('voucher_code')) {
            $vCode = trim($request->voucher_code);
            $voucher = HabitVoucher::where('voucher_code', $vCode)->first();
            if ($voucher && !$voucher->isRedeemed() && !$voucher->isExpired()) {
                $voucherVal = $voucher->getRewardNumericValue();
                $voucherAmount = min($voucherVal, $totalGross);
            }
        }

        // Sisa tagihan setelah dipotong voucher
        $payableAfterVoucher = max(0.0, $totalGross - $voucherAmount);

        $rfidPaidAmount = 0.0;
        $cashPaidAmount = 0.0;
        $changeAmount = 0.0;
        $bonAmount = 0.0;
        $bonStatus = null;
        $customerType = $request->input('customer_type', 'user');
        $customer = null;

        if ($request->filled('customer_id')) {
            $customer = $customerType === 'member'
                ? CoopMember::find($request->customer_id)
                : User::nonAdmin()->find($request->customer_id);
        }

        // 2. Pemrosesan Berdasarkan Metode Pembayaran
        if ($paymentMethod === 'bon') {
            if (!$customer) {
                return redirect()->back()->with('error', 'Pegawai atau Member wajib dipilih untuk transaksi BON / Kasbon.');
            }

            $bonLimit = $customer instanceof CoopMember
                ? (float) $customer->bon_limit
                : (float) ($customer->coop_bon_limit ?? 0);

            if ($bonLimit > 0) {
                $ownerCol = $customer instanceof CoopMember ? 'member_id' : 'user_id';
                $currentBon = (float) CoopTransaction::where($ownerCol, $customer->id)
                    ->whereIn('bon_status', ['unpaid', 'partial'])
                    ->sum(DB::raw('bon_amount - bon_paid_amount'));

                if (($currentBon + $payableAfterVoucher) > $bonLimit) {
                    return redirect()->back()->with('error', "Transaksi melebihi batas BON {$customer->name} (Limit: Rp " . number_format($bonLimit, 0, ',', '.') . ").");
                }
            }

            $bonAmount = $payableAfterVoucher;
            $bonStatus = $bonAmount > 0 ? 'unpaid' : 'paid';
        } elseif ($paymentMethod === 'rfid_card') {
            if (!$customer) {
                return redirect()->back()->with('error', 'Kartu RFID pegawai / data pelanggan wajib terdeteksi untuk pembayaran kartu RFID.');
            }

            if ($customer->coop_balance < $payableAfterVoucher) {
                return redirect()->back()->with('error', "Saldo Kartu RFID {$customer->name} (Rp " . number_format($customer->coop_balance, 0, ',', '.') . ") tidak mencukupi untuk tagihan Rp " . number_format($payableAfterVoucher, 0, ',', '.') . ". Silakan pilih metode Split atau Tunai.");
            }

            $rfidPaidAmount = $payableAfterVoucher;
        } elseif ($paymentMethod === 'voucher') {
            if ($voucherAmount < $totalGross) {
                return redirect()->back()->with('error', "Voucher tidak mencukupi untuk pembayaran penuh. Silakan pilih metode Tunai atau Kartu RFID.");
            }
        } elseif ($paymentMethod === 'split') {
            // Kombinasi: Saldo Kartu RFID + Tunai
            if (!$customer) {
                return redirect()->back()->with('error', 'Data pelanggan kartu RFID wajib dipilih untuk metode split.');
            }

            // Gunakan seluruh sisa saldo kartu RFID
            $rfidPaidAmount = min((float) $customer->coop_balance, $payableAfterVoucher);
            $remainingToPay = max(0.0, $payableAfterVoucher - $rfidPaidAmount);

            $cashReceived = (float) ($request->cash_paid ?: $remainingToPay);
            if ($cashReceived < $remainingToPay) {
                return redirect()->back()->with('error', "Uang tunai kurang. Sisa tagihan setelah saldo RFID adalah Rp " . number_format($remainingToPay, 0, ',', '.') . ".");
            }

            $cashPaidAmount = $cashReceived;
            $changeAmount = max(0.0, $cashReceived - $remainingToPay);
        } else {
            // Tunai / Cash atau QRIS
            $cashReceived = (float) ($request->cash_paid ?: $payableAfterVoucher);
            if ($cashReceived < $payableAfterVoucher && $paymentMethod === 'cash') {
                return redirect()->back()->with('error', "Uang tunai yang diterima (Rp " . number_format($cashReceived, 0, ',', '.') . ") kurang dari total tagihan (Rp " . number_format($payableAfterVoucher, 0, ',', '.') . ").");
            }

            $cashPaidAmount = $cashReceived;
            $changeAmount = max(0.0, $cashReceived - $payableAfterVoucher);
        }

        DB::beginTransaction();
        try {
            $datePrefix = Carbon::now()->format('Ymd');
            $randomSuffix = strtoupper(Str::random(4));
            $transactionCode = 'WRG-' . $datePrefix . '-' . $randomSuffix;

            // Ambil produk sekaligus untuk mencatat cost_price historis & mengurangi stok
            $productIds = collect($items)->pluck('id')->filter()->unique()->values();
            $productsMap = $productIds->isNotEmpty()
                ? CoopProduct::whereIn('id', $productIds)->get()->keyBy('id')
                : collect();

            $items = collect($items)->map(function ($item) use ($productsMap) {
                $prod = !empty($item['id']) ? $productsMap->get($item['id']) : null;
                $item['cost_price'] = (float) ($prod?->cost_price ?? 0);
                return $item;
            })->all();

            // Simpan Transaksi POS
            $transaction = CoopTransaction::create([
                'transaction_code' => $transactionCode,
                'cashier_id'       => $cashier->id,
                'user_id'          => $customer instanceof User ? $customer->id : null,
                'member_id'        => $customer instanceof CoopMember ? $customer->id : null,
                'voucher_id'       => $voucher?->id,
                'payment_method'   => $paymentMethod,
                'total_amount'     => $totalGross,
                'discount_amount'  => 0,
                'voucher_amount'   => $voucherAmount,
                'rfid_paid_amount' => $rfidPaidAmount,
                'cash_paid_amount' => $cashPaidAmount,
                'change_amount'    => $changeAmount,
                'bon_amount'       => $bonAmount,
                'bon_paid_amount'  => 0,
                'bon_status'       => $bonStatus,
                'items'            => $items,
                'notes'            => $request->notes,
            ]);

            // Potong Saldo Kartu RFID jika ada penggunaan saldo
            if ($rfidPaidAmount > 0 && $customer) {
                $customer->deductCoopBalance(
                    $rfidPaidAmount,
                    'pos_purchase',
                    $transactionCode,
                    "Pembelian di Koperasi Rabbani ({$transactionCode})",
                    $cashier
                );
            }

            // Tandai Voucher Habit sebagai claimed jika digunakan
            if ($voucher && $voucherAmount > 0) {
                $voucher->update([
                    'status'             => 'claimed',
                    'claimed_at'         => now(),
                    'redeemed_by'        => $cashier->id,
                    'redemption_notes'   => "Digunakan pada transaksi {$transactionCode}",
                    'transaction_code'   => $transactionCode,
                    'transaction_amount' => $totalGross,
                    'discount_amount'    => $voucherAmount,
                ]);
            }

            // Kurangi stok produk jika item terhubung dengan database
            foreach ($items as $item) {
                if (!empty($item['id'])) {
                    $prod = $productsMap->get($item['id']);
                    if ($prod && $prod->stock >= $item['qty']) {
                        $prod->decrement('stock', $item['qty']);
                    }
                }
            }

            DB::commit();

            $storeInfo = $this->storeInfo();

            // Siapkan payload struk thermal
            $receipt = [
                'transaction_code' => $transactionCode,
                'date_time'        => Carbon::now()->locale('id')->isoFormat('D MMMM Y, HH:mm:ss'),
                'cashier_name'     => $cashier->name,
                'customer_name'    => $customer?->name ?: 'Pelanggan Umum',
                'customer_nip'     => $customer instanceof CoopMember ? $customer->member_code : ($customer?->nip ?: '-'),
                'payment_method'   => $paymentMethod,
                'items'            => $items,
                'total_gross'      => $totalGross,
                'voucher_code'     => $voucher?->voucher_code,
                'voucher_amount'   => $voucherAmount,
                'payable_amount'   => $payableAfterVoucher,
                'rfid_paid_amount' => $rfidPaidAmount,
                'cash_paid_amount' => $cashPaidAmount,
                'change_amount'    => $changeAmount,
                'bon_amount'       => $bonAmount,
                'customer_balance' => $customer ? (float) $customer->fresh()->coop_balance : null,
                'store_name'       => $storeInfo['name'],
                'store_address'    => $storeInfo['address'],
                'store_phone'      => $storeInfo['phone'],
                'receipt_footer'   => $storeInfo['receipt_footer'],
            ];

            return redirect()->route('koperasi.pos')
                ->with('success', "Transaksi {$transactionCode} berhasil! Struk siap dicetak.")
                ->with('lastReceipt', $receipt);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan saat memproses transaksi: ' . $e->getMessage());
        }
    }

    /**
     * Backward-compatibility quick redeem method (langsung tukar voucher penuh tanpa keranjang barang).
     */
    public function redeem(Request $request)
    {
        $request->validate([
            'voucher_id'         => 'required|exists:habit_vouchers,id',
            'transaction_amount' => 'nullable|numeric|min:0',
            'paid_amount'        => 'nullable|numeric|min:0',
            'items_summary'      => 'nullable|string|max:500',
            'notes'              => 'nullable|string|max:255',
        ]);

        $voucher = HabitVoucher::with('user')->findOrFail($request->voucher_id);

        if ($voucher->isRedeemed()) {
            return redirect()->back()->with('error', "Voucher {$voucher->voucher_code} sudah pernah digunakan.");
        }

        if ($voucher->isExpired()) {
            return redirect()->back()->with('error', "Voucher {$voucher->voucher_code} sudah kedaluwarsa.");
        }

        $cashier = auth()->user();
        $voucherValue = $voucher->getRewardNumericValue();

        $transactionAmount = (float) ($request->transaction_amount ?: $voucherValue);
        $discountAmount = min($voucherValue, $transactionAmount);
        $payableAmount = max(0, $transactionAmount - $discountAmount);
        $paidAmount = (float) ($request->paid_amount ?: $payableAmount);
        $changeAmount = max(0, $paidAmount - $payableAmount);

        $datePrefix = Carbon::now()->format('Ymd');
        $randomSuffix = strtoupper(Str::random(4));
        $transactionCode = 'WRG-VCR-' . $datePrefix . '-' . $randomSuffix;

        $itemsSummary = $request->items_summary ?: 'Penukaran ' . $voucher->reward_value;

        $voucher->update([
            'status'             => 'claimed',
            'claimed_at'         => now(),
            'redeemed_by'        => $cashier->id,
            'redemption_notes'   => $request->notes ?: 'Penukaran Langsung Voucher di POS Koperasi',
            'transaction_code'   => $transactionCode,
            'transaction_amount' => $transactionAmount,
            'discount_amount'    => $discountAmount,
            'paid_amount'        => $paidAmount,
            'change_amount'      => $changeAmount,
            'items_summary'      => $itemsSummary,
        ]);

        $storeInfo = $this->storeInfo();

        $receipt = [
            'transaction_code' => $transactionCode,
            'voucher_code'     => $voucher->voucher_code,
            'date_time'        => Carbon::now()->locale('id')->isoFormat('D MMMM Y, HH:mm:ss'),
            'cashier_name'     => $cashier->name,
            'customer_name'    => $voucher->user?->name ?: 'Pegawai',
            'customer_nip'     => $voucher->user?->nip ?: '-',
            'customer_divisi'  => $voucher->user?->divisi ?: '-',
            'items'            => [
                ['name' => $itemsSummary, 'qty' => 1, 'price' => $transactionAmount, 'subtotal' => $transactionAmount],
            ],
            'total_gross'      => $transactionAmount,
            'voucher_amount'   => $discountAmount,
            'payable_amount'   => $payableAmount,
            'rfid_paid_amount' => 0,
            'cash_paid_amount' => $paidAmount,
            'change_amount'    => $changeAmount,
            'store_name'       => $storeInfo['name'],
            'store_address'    => $storeInfo['address'],
            'store_phone'      => $storeInfo['phone'],
            'receipt_footer'   => $storeInfo['receipt_footer'],
        ];

        return redirect()->route('koperasi.pos')
            ->with('success', "Transaksi {$transactionCode} berhasil! Voucher {$voucher->voucher_code} telah ditukarkan.")
            ->with('lastReceipt', $receipt);
    }

    /**
     * Batalkan / Void transaksi penukaran kasir.
     */
    public function cancel(Request $request, $id)
    {
        $trx = CoopTransaction::find($id);
        if ($trx) {
            DB::beginTransaction();
            try {
                // Kembalikan saldo RFID jika terpotong (Pegawai atau Member)
                $rfidOwner = $trx->customer ?? $trx->member;
                if ($trx->rfid_paid_amount > 0 && $rfidOwner) {
                    $rfidOwner->addCoopBalance(
                        $trx->rfid_paid_amount,
                        'refund',
                        $trx->transaction_code,
                        "Pengembalian saldo (Void transaksi {$trx->transaction_code})",
                        auth()->user()
                    );
                }

                // Kembalikan voucher jika ada
                if ($trx->voucher) {
                    $trx->voucher->update([
                        'status'           => 'active',
                        'claimed_at'       => null,
                        'redeemed_by'      => null,
                        'redemption_notes' => null,
                        'transaction_code' => null,
                    ]);
                }

                // Kembalikan stok
                if (is_array($trx->items)) {
                    foreach ($trx->items as $it) {
                        if (!empty($it['id'])) {
                            CoopProduct::where('id', $it['id'])->increment('stock', $it['qty']);
                        }
                    }
                }

                $code = $trx->transaction_code;
                $trx->delete();
                DB::commit();

                return redirect()->back()->with('success', "Transaksi {$code} berhasil dibatalkan (Void). Saldo & status voucher telah dikembalikan.");
            } catch (\Exception $e) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Gagal membatalkan transaksi: ' . $e->getMessage());
            }
        }

        // Fallback untuk model HabitVoucher lama
        $voucher = HabitVoucher::find($id);
        if ($voucher) {
            $voucher->update([
                'status'             => 'active',
                'claimed_at'         => null,
                'redeemed_by'        => null,
                'redemption_notes'   => null,
                'transaction_code'   => null,
                'transaction_amount' => 0,
                'discount_amount'    => 0,
                'paid_amount'        => 0,
                'change_amount'      => 0,
            ]);
            return redirect()->back()->with('success', "Voucher {$voucher->voucher_code} berhasil di-void.");
        }

        return redirect()->back()->with('error', 'Data transaksi tidak ditemukan.');
    }

    /**
     * Halaman Riwayat Transaksi POS.
     */
    public function history(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->endOfMonth()->format('Y-m-d'));
        $search = $request->query('search');

        $query = CoopTransaction::with(['customer', 'member', 'cashier', 'voucher'])
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->orderByDesc('created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_code', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%")
                         ->orWhere('rfid_uid', 'like', "%{$search}%");
                  })
                  ->orWhereHas('member', function ($mq) use ($search) {
                      $mq->where('name', 'like', "%{$search}%")
                         ->orWhere('member_code', 'like', "%{$search}%")
                         ->orWhere('rfid_uid', 'like', "%{$search}%");
                  });
            });
        }

        $totalTransactions = (clone $query)->count();
        $totalSales = (clone $query)->sum('total_amount');
        $totalVouchers = (clone $query)->sum('voucher_amount');
        $totalRfid = (clone $query)->sum('rfid_paid_amount');

        $transactions = $query->paginate(20)->withQueryString();

        $transactions->getCollection()->transform(function ($t) {
            return [
                'id'               => $t->id,
                'transaction_code' => $t->transaction_code,
                'voucher_code'     => $t->voucher?->voucher_code,
                'customer_name'    => $t->customer?->name ?? $t->member?->name ?? 'Pelanggan Umum',
                'customer_nip'     => $t->customer?->nip ?? $t->member?->member_code ?? '-',
                'customer_divisi'  => $t->customer?->divisi ?? $t->member?->type_label ?? '-',
                'payment_method'   => $t->payment_method,
                'total_amount'     => (float) $t->total_amount,
                'discount_amount'  => (float) $t->discount_amount,
                'voucher_amount'   => (float) $t->voucher_amount,
                'rfid_paid_amount' => (float) $t->rfid_paid_amount,
                'cash_paid_amount' => (float) $t->cash_paid_amount,
                'change_amount'    => (float) $t->change_amount,
                'bon_amount'       => (float) $t->bon_amount,
                'bon_paid_amount'  => (float) $t->bon_paid_amount,
                'bon_status'       => $t->bon_status,
                'items'            => $t->items,
                'created_at'       => $t->created_at->format('d M Y, H:i'),
                'cashier_name'     => $t->cashier?->name ?: 'Kasir',
            ];
        });

        return Inertia::render('Koperasi/POS/History', [
            'transactions' => $transactions,
            'summary'      => [
                'total_transactions' => $totalTransactions,
                'total_sales'        => (float) $totalSales,
                'total_vouchers'     => (float) $totalVouchers,
                'total_rfid'         => (float) $totalRfid,
            ],
            'filters'      => [
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'search'     => $search,
            ],
            'storeInfo'    => $this->storeInfo(),
            'cashier'      => auth()->user(),
        ]);
    }

    /**
     * Manajemen Katalog Produk Koperasi Sekolah.
     */
    public function products(Request $request)
    {
        $search = $request->query('search');
        $category = $request->query('category');

        $query = CoopProduct::orderBy('category')->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        $products = $query->paginate(20)->withQueryString();
        $categories = CoopProduct::distinct()->pluck('category')->filter()->values();

        return Inertia::render('Koperasi/POS/Products', [
            'products'   => $products,
            'categories' => $categories,
            'filters'    => ['search' => $search, 'category' => $category],
            'cashier'    => auth()->user(),
        ]);
    }

    /**
     * Tambah / Edit Produk Koperasi.
     */
    public function storeProduct(Request $request)
    {
        $request->validate([
            'id'       => 'nullable|exists:coop_products,id',
            'name'     => 'required|string|max:255',
            'barcode'  => 'nullable|string|max:100',
            'category' => 'required|string|max:100',
            'price'    => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock'    => 'required|integer|min:0',
            'unit'     => 'nullable|string|max:50',
        ]);

        CoopProduct::updateOrCreate(
            ['id' => $request->id],
            [
                'name'       => $request->name,
                'barcode'    => $request->barcode,
                'category'   => $request->category,
                'price'      => $request->price,
                'cost_price' => $request->cost_price ?: 0,
                'stock'      => $request->stock,
                'unit'       => $request->unit ?: 'Pcs',
                'is_active'  => true,
            ]
        );

        return redirect()->back()->with('success', 'Produk koperasi berhasil disimpan.');
    }

    /**
     * Download template Excel untuk impor katalog barang.
     */
    public function exportTemplate()
    {
        return Excel::download(new CoopProductTemplateExport, 'Template_Katalog_Barang_Koperasi_SDAM.xlsx');
    }

    /**
     * Impor massal katalog barang dari file Excel.
     */
    public function importProducts(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $import = new CoopProductImport();
        Excel::import($import, $request->file('file'));

        return redirect()->back()->with('success', "Alhamdulillah! Berhasil mengimpor {$import->importedCount} produk baru dan memperbarui {$import->updatedCount} produk koperasi.");
    }

    /**
     * Unduh Laporan Penjualan Bulanan Koperasi (Excel).
     */
    public function exportMonthlySales(Request $request)
    {
        $period = $request->query('period', Carbon::today()->format('Y-m'));
        $filename = "Laporan_Penjualan_Koperasi_SDAM_{$period}.xlsx";

        return Excel::download(new CoopMonthlySalesReport($period), $filename);
    }

    /**
     * Unduh Laporan Stock Opname Koperasi (Excel).
     */
    public function exportStockOpname()
    {
        $dateStr = Carbon::today()->format('Ymd');
        $filename = "Laporan_Stock_Opname_Koperasi_SDAM_{$dateStr}.xlsx";

        return Excel::download(new CoopStockOpnameReport(), $filename);
    }

    /**
     * Dashboard Monitoring & Stock Opname Koperasi.
     */
    public function stockOpname(Request $request)
    {
        $allProducts = CoopProduct::where('is_active', true)->get();

        // 1. Metrik Keseluruhan (Ringkasan Eksekutif)
        $totalProducts = $allProducts->count();
        $totalUnits = (int) $allProducts->sum('stock');
        $totalCostValuation = (float) $allProducts->sum(fn($p) => $p->cost_price * $p->stock);
        $totalPotentialRevenue = (float) $allProducts->sum(fn($p) => $p->price * $p->stock);
        $potentialProfit = max(0, $totalPotentialRevenue - $totalCostValuation);

        $outOfStockCount = $allProducts->where('stock', '<=', 0)->count();
        $lowStockCount = $allProducts->filter(fn($p) => $p->stock > 0 && $p->stock <= 10)->count();
        $safeStockCount = $allProducts->where('stock', '>', 10)->count();

        // 2. Breakdown per Kategori
        $categoryBreakdown = $allProducts->groupBy('category')->map(function ($items, $category) {
            $stockCount = (int) $items->sum('stock');
            $costVal = (float) $items->sum(fn($p) => $p->cost_price * $p->stock);
            $sellVal = (float) $items->sum(fn($p) => $p->price * $p->stock);
            $outOfStock = $items->where('stock', '<=', 0)->count();
            $lowStock = $items->filter(fn($p) => $p->stock > 0 && $p->stock <= 10)->count();

            return [
                'category'       => $category,
                'items_count'    => $items->count(),
                'total_stock'    => $stockCount,
                'cost_valuation' => $costVal,
                'sell_valuation' => $sellVal,
                'out_of_stock'   => $outOfStock,
                'low_stock'      => $lowStock,
            ];
        })->values();

        // 3. Filter & Pencarian Daftar Produk untuk Stock Opname
        $query = CoopProduct::where('is_active', true);

        if ($request->filled('search')) {
            $q = trim($request->search);
            $query->where(function ($sq) use ($q) {
                $sq->where('name', 'like', "%{$q}%")
                   ->orWhere('barcode', 'like', "%{$q}%");
            });
        }

        if ($request->filled('category') && $request->category !== 'Semua') {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            if ($request->status === 'out_of_stock') {
                $query->where('stock', '<=', 0);
            } elseif ($request->status === 'low_stock') {
                $query->where('stock', '>', 0)->where('stock', '<=', 10);
            } elseif ($request->status === 'safe') {
                $query->where('stock', '>', 10);
            }
        }

        // Sorting
        $sort = $request->query('sort', 'stock_asc');
        if ($sort === 'stock_desc') {
            $query->orderByDesc('stock');
        } elseif ($sort === 'stock_asc') {
            $query->orderBy('stock')->orderBy('name');
        } elseif ($sort === 'valuation_desc') {
            $query->orderByRaw('(cost_price * stock) DESC');
        } elseif ($sort === 'name_asc') {
            $query->orderBy('name');
        } else {
            $query->orderBy('stock')->orderBy('name');
        }

        $products = $query->paginate(25)->withQueryString()->through(function ($p) {
            return [
                'id'             => $p->id,
                'name'           => $p->name,
                'barcode'        => $p->barcode,
                'category'       => $p->category,
                'unit'           => $p->unit,
                'price'          => (float) $p->price,
                'cost_price'     => (float) $p->cost_price,
                'stock'          => (int) $p->stock,
                'cost_valuation' => (float) ($p->cost_price * $p->stock),
                'sell_valuation' => (float) ($p->price * $p->stock),
                'status'         => $p->stock <= 0 ? 'habis' : ($p->stock <= 10 ? 'menipis' : 'aman'),
            ];
        });

        // 4. Riwayat Penyesuaian Stok Terakhir (Audit Logs)
        $recentLogs = CoopStockLog::with(['product', 'staff'])
            ->latest()
            ->limit(15)
            ->get()
            ->map(function ($log) {
                return [
                    'id'           => $log->id,
                    'product_name' => $log->product?->name ?: 'Produk Dihapus',
                    'barcode'      => $log->product?->barcode ?: '-',
                    'staff_name'   => $log->staff?->name ?: 'Sistem',
                    'type'         => $log->type,
                    'stock_before' => $log->stock_before,
                    'stock_after'  => $log->stock_after,
                    'difference'   => $log->difference,
                    'notes'        => $log->notes,
                    'created_at'   => $log->created_at->format('d/m/Y H:i'),
                ];
            });

        $categories = CoopProduct::where('is_active', true)->distinct()->pluck('category')->sort()->values();

        return Inertia::render('Koperasi/POS/StockOpname', [
            'metrics' => [
                'total_products'          => $totalProducts,
                'total_units'             => $totalUnits,
                'total_cost_valuation'    => $totalCostValuation,
                'total_potential_revenue' => $totalPotentialRevenue,
                'potential_profit'        => $potentialProfit,
                'out_of_stock_count'      => $outOfStockCount,
                'low_stock_count'         => $lowStockCount,
                'safe_stock_count'        => $safeStockCount,
            ],
            'categoryBreakdown' => $categoryBreakdown,
            'products'          => $products,
            'recentLogs'        => $recentLogs,
            'categories'        => $categories,
            'filters'           => [
                'search'   => $request->search ?? '',
                'category' => $request->category ?? '',
                'status'   => $request->status ?? 'all',
                'sort'     => $sort,
            ],
        ]);
    }

    /**
     * Penyesuaian stok produk (Stock Adjustment).
     */
    public function adjustStock(Request $request)
    {
        $request->validate([
            'product_id'   => 'required|exists:coop_products,id',
            'actual_stock' => 'required|integer|min:0',
            'type'         => 'required|string|in:opname,restock,damage,expired,correction,other',
            'notes'        => 'nullable|string|max:255',
        ]);

        $product = CoopProduct::findOrFail($request->product_id);
        $previousStock = (int) $product->stock;
        $newStock = (int) $request->actual_stock;
        $difference = $newStock - $previousStock;

        // Update stok produk
        $product->update(['stock' => $newStock]);

        // Catat ke log audit
        CoopStockLog::create([
            'coop_product_id' => $product->id,
            'user_id'         => auth()->id(),
            'type'            => $request->type,
            'stock_before'    => $previousStock,
            'stock_after'     => $newStock,
            'difference'      => $difference,
            'notes'           => $request->notes ?: 'Penyesuaian stok manual',
        ]);

        return redirect()->back()->with('success', "Stok {$product->name} berhasil disesuaikan dari {$previousStock} menjadi {$newStock} {$product->unit}.");
    }

    /**
     * Batch simpan hasil hitung fisik opname cepat.
     */
    public function quickBatchOpname(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:coop_products,id',
            'items.*.physical_stock' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        $updatedCount = 0;
        DB::transaction(function () use ($request, &$updatedCount) {
            foreach ($request->items as $item) {
                $product = CoopProduct::find($item['product_id']);
                if (!$product) continue;

                $prev = (int) $product->stock;
                $new = (int) $item['physical_stock'];
                if ($prev !== $new) {
                    $product->update(['stock' => $new]);

                    CoopStockLog::create([
                        'coop_product_id' => $product->id,
                        'user_id'         => auth()->id(),
                        'type'            => 'opname',
                        'stock_before'    => $prev,
                        'stock_after'     => $new,
                        'difference'      => $new - $prev,
                        'notes'           => $request->notes ?: 'Hasil audit fisik stock opname berkala',
                    ]);
                    $updatedCount++;
                }
            }
        });

        return redirect()->back()->with('success', "Alhamdulillah! Sebanyak {$updatedCount} barang berhasil disesuaikan stok fisiknya.");
    }
}
