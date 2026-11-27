<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Evaluasi Kinerja (KPI)</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        h2 { margin: 0 0 4px 0; font-size: 14px; }
        p.meta { margin: 0 0 12px 0; color: #475569; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; }
        th { background-color: #f1f5f9; font-weight: bold; font-size: 9px; }
        td.num { text-align: right; }
    </style>
</head>
<body>
    <h2>Laporan Evaluasi Kinerja Pegawai (KPI)</h2>
    <p class="meta">Periode: {{ $periodLabel }} | Tahun Ajaran: {{ $academicYear }} | Dicetak: {{ now()->format('d/m/Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Pegawai</th>
                <th>Divisi</th>
                <th>Kategori</th>
                <th>Aspek 1</th>
                <th>Aspek 2</th>
                <th>Aspek 3</th>
                <th>Aspek 4</th>
                <th>Nilai Akhir</th>
                <th>Predikat</th>
                <th>Status</th>
                <th>Penilai</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $r)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $r['name'] }}</td>
                <td>{{ $r['divisi'] ?: '-' }}</td>
                <td>{{ strtoupper($r['category']) }}</td>
                <td class="num">{{ $r['aspect_1'] }}</td>
                <td class="num">{{ $r['aspect_2'] }}</td>
                <td class="num">{{ $r['aspect_3'] }}</td>
                <td class="num">{{ $r['aspect_4'] }}</td>
                <td class="num"><strong>{{ $r['final_score'] }}</strong></td>
                <td>{{ $r['predicate'] }}</td>
                <td>{{ $r['status_label'] }}</td>
                <td>{{ $r['evaluator_name'] ?: '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="12" style="text-align:center;">Tidak ada data evaluasi pada filter ini.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
