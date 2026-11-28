<?php

namespace App\Services\Dashboard;

use App\Models\Disposisi;
use App\Models\EmployeeDocument;
use App\Models\Notulen;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;

class TataUsahaDashboardBuilder extends AbstractDashboardBuilder
{
    public function roleKey(): string
    {
        return 'tata_usaha';
    }

    public function build(User $user, DashboardContext $ctx): array
    {
        $suratMasukPending = (int) SuratMasuk::where(function ($q) {
            $q->where('status', '!=', 'selesai')->orWhereNull('status');
        })->count();

        $suratKeluarBulanIni = (int) SuratKeluar::whereBetween('tanggal_surat', [$ctx->monthStartStr, $ctx->monthEndStr])->count();

        $disposisiPending = (int) Disposisi::where('status', '!=', 'selesai')->count();

        $notulenPending = (int) Notulen::where('is_approved', false)->count();

        $arsipPending = $ctx->feature('feature_arsip')
            ? (int) EmployeeDocument::where('is_verified', false)->count()
            : 0;

        return [
            'roleKey' => $this->roleKey(),
            'personal' => $this->personalSummary($user, $ctx),
            'tu' => [
                'surat_masuk_pending' => $suratMasukPending,
                'surat_keluar_bulan_ini' => $suratKeluarBulanIni,
                'disposisi_pending' => $disposisiPending,
                'notulen_pending' => $notulenPending,
                'arsip_pending' => $arsipPending,
            ],
        ];
    }

    public function buildDeferred(User $user, DashboardContext $ctx): array
    {
        $recentMasuk = SuratMasuk::latest('created_at')
            ->limit(3)
            ->get(['id', 'nomor_surat', 'perihal', 'pengirim', 'status', 'tanggal_diterima'])
            ->map(fn ($s) => [
                'id' => 'sm-' . $s->id,
                'type' => 'Surat Masuk',
                'nomor' => $s->nomor_surat,
                'subject' => $s->perihal,
                'party' => $s->pengirim,
                'status' => $s->status ?? 'baru',
                'date' => (string) $s->tanggal_diterima,
            ]);

        $recentKeluar = SuratKeluar::latest('created_at')
            ->limit(3)
            ->get(['id', 'nomor_surat', 'perihal', 'tujuan', 'status', 'tanggal_surat'])
            ->map(fn ($s) => [
                'id' => 'sk-' . $s->id,
                'type' => 'Surat Keluar',
                'nomor' => $s->nomor_surat,
                'subject' => $s->perihal,
                'party' => $s->tujuan,
                'status' => $s->status ?? 'draft',
                'date' => (string) $s->tanggal_surat,
            ]);

        $activities = $recentMasuk->concat($recentKeluar)
            ->sortByDesc('date')
            ->take(5)
            ->values()
            ->all();

        $pendingNotulens = Notulen::where('is_approved', false)
            ->latest('tanggal_waktu')
            ->limit(5)
            ->get(['id', 'judul_rapat', 'tanggal_waktu', 'pimpinan_rapat'])
            ->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->judul_rapat,
                'subtitle' => $n->pimpinan_rapat,
                'date' => $n->tanggal_waktu?->format('d M Y'),
            ])
            ->all();

        return [
            'recent_letters' => $activities,
            'pending_notulens' => $pendingNotulens,
            'habit_trend' => $this->personalHabitTrend($user, $ctx),
        ];
    }
}
