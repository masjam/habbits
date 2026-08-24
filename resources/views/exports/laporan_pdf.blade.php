<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Capaian Ibadah - {{ $namaBulan }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #2d3748;
            padding-bottom: 10px;
        }
        .header h2 {
            margin: 0;
            color: #1a202c;
            font-size: 18px;
        }
        .header p {
            margin: 5px 0 0 0;
            color: #4a5568;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #cbd5e0;
            padding: 8px 10px;
            text-align: left;
        }
        th {
            background-color: #f7fafc;
            color: #4a5568;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .footer {
            margin-top: 30px;
            font-size: 10px;
            color: #718096;
            text-align: right;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>

    @php
    function getRowColor($persentase, $targetBulanan) {
        if ($persentase <= 30) return '#fff1f2';
        if ($persentase <= 50) return '#fffbeb';
        if ($persentase < $targetBulanan) return '#ecfdf5';
        return '#d1fae5';
    }
    function getBadgeStyle($persentase, $targetBulanan) {
        if ($persentase <= 30) return 'background-color: #fecdd3; color: #9f1239;';
        if ($persentase <= 50) return 'background-color: #fde68a; color: #92400e;';
        if ($persentase < $targetBulanan) return 'background-color: #a7f3d0; color: #065f46;';
        return 'background-color: #059669; color: #ffffff;';
    }
    @endphp

    <div class="header">
        <h2>Laporan Capaian Ibadah Pegawai</h2>
        <p>Periode: {{ $namaBulan }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 5%">No</th>
                <th style="width: 35%">Nama Pegawai</th>
                <th class="text-center" style="width: 15%">Jenis Kelamin</th>
                <th class="text-center" style="width: 15%">Skor Diperoleh</th>
                <th class="text-center" style="width: 15%">Skor Maksimal</th>
                <th class="text-center" style="width: 15%">Persentase</th>
            </tr>
        </thead>
        <tbody>
            @forelse($leaderboard as $index => $row)
            <tr style="background-color: {{ getRowColor($row['persentase'], $targetBulanan) }};">
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="font-bold">{{ $row['name'] }}</td>
                <td class="text-center">{{ $row['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' }}</td>
                <td class="text-center">{{ $row['skor'] }}</td>
                <td class="text-center">{{ $skorMaksimal }}</td>
                <td class="text-center">
                    <span class="badge" style="{{ getBadgeStyle($row['persentase'], $targetBulanan) }}">
                        {{ $row['persentase'] }}%
                    </span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center">Tidak ada data pegawai pada periode ini.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Dicetak pada: {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }}
    </div>

</body>
</html>
