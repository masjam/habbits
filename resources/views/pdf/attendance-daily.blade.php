<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekap Presensi Harian - {{ $formattedDate }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 10mm 12mm 10mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            color: #1e293b;
            line-height: 1.2;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 10px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 6px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-title {
            font-size: 13pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
        }
        .header-subtitle {
            font-size: 9pt;
            color: #475569;
            margin-top: 2px;
        }
        .header-meta {
            text-align: right;
            font-size: 7.5pt;
            color: #64748b;
        }
        /* BANNER KHUSUS JIKA TANGGAL KHUSUS AKTIF */
        .special-banner {
            background-color: #ede9fe;
            border: 1.5px solid #8b5cf6;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 10px;
        }
        .special-banner-title {
            font-size: 9pt;
            font-weight: bold;
            color: #5b21b6;
        }
        .special-banner-detail {
            font-size: 7.5pt;
            color: #6d28d9;
            margin-top: 2px;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }
        .table-data th, .table-data td {
            border: 0.5px solid #cbd5e1;
            padding: 4px 5px;
            text-align: left;
            vertical-align: middle;
        }
        .table-data th {
            background-color: #059669;
            color: #ffffff;
            font-weight: bold;
            font-size: 8pt;
            text-align: center;
        }
        .table-data th.special-theme {
            background-color: #7c3aed !important;
        }
        .text-center {
            text-align: center !important;
        }
        .text-bold {
            font-weight: bold;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 7pt;
            text-transform: uppercase;
        }
        .status-hadir { background-color: #d1fae5; color: #065f46; }
        .status-terlambat { background-color: #fef3c7; color: #92400e; }
        .status-dinas_luar { background-color: #dbeafe; color: #1e40af; }
        .status-sakit { background-color: #e0e7ff; color: #3730a3; }
        .status-izin { background-color: #ede9fe; color: #5b21b6; }
        .status-cuti { background-color: #ccfbf1; color: #115e59; }
        .status-belum_hadir { background-color: #f1f5f9; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="header-title">REKAP PRESENSI HARIAN PEGAWAI</div>
                    <div class="header-subtitle">Hari / Tanggal: <strong>{{ $formattedDate }}</strong> | Divisi: <strong>{{ $divisionFilter ?: 'Semua Divisi' }}</strong></div>
                </td>
                <td class="header-meta">
                    <div>Dicetak pada: {{ now()->locale('id')->isoFormat('D MMMM Y, HH:mm') }} WIB</div>
                    <div>Habit Tracking &amp; HR System</div>
                </td>
            </tr>
        </table>
    </div>

    @if($selectedDateSpecial)
        <div class="special-banner">
            <div class="special-banner-title">✨ TANGGAL KHUSUS AKTIF: {{ $selectedDateSpecial['name'] }}</div>
            <div class="special-banner-detail">
                Jam Kerja Khusus: <strong>{{ $selectedDateSpecial['time_in'] }} - {{ $selectedDateSpecial['time_out'] }} WIB</strong>
                @if(!empty($selectedDateSpecial['late_tolerance']))
                    | Toleransi Keterlambatan: <strong>{{ $selectedDateSpecial['late_tolerance'] }} menit</strong>
                @endif
            </div>
        </div>
    @endif

    <table class="table-data">
        <thead>
            <tr>
                <th class="{{ $selectedDateSpecial ? 'special-theme' : '' }}" style="width: 25px;">No</th>
                <th class="{{ $selectedDateSpecial ? 'special-theme' : '' }}" style="width: 140px;">Nama Pegawai</th>
                <th class="{{ $selectedDateSpecial ? 'special-theme' : '' }}" style="width: 75px;">Divisi</th>
                <th class="{{ $selectedDateSpecial ? 'special-theme' : '' }}" style="width: 80px;">Jadwal</th>
                <th class="{{ $selectedDateSpecial ? 'special-theme' : '' }}" style="width: 60px;">Masuk</th>
                <th class="{{ $selectedDateSpecial ? 'special-theme' : '' }}" style="width: 60px;">Pulang</th>
                <th class="{{ $selectedDateSpecial ? 'special-theme' : '' }}" style="width: 75px;">Status</th>
                <th class="{{ $selectedDateSpecial ? 'special-theme' : '' }}">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dailyReportData as $idx => $row)
                <tr style="{{ $idx % 2 == 1 ? 'background-color: #f8fafc;' : '' }}">
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="text-bold">{{ $row['name'] }}</td>
                    <td style="font-size: 7pt; color: #475569;">{{ $row['divisi'] ?: '-' }}</td>
                    <td class="text-center" style="font-size: 7pt;">{{ $row['work_schedule'] }}</td>
                    <td class="text-center font-bold">{{ $row['time_in'] ?: '-' }}</td>
                    <td class="text-center font-bold">{{ $row['time_out'] ?: '-' }}</td>
                    <td class="text-center">
                        <span class="status-badge status-{{ $row['status'] }}">
                            {{ str_replace('_', ' ', $row['status']) }}
                        </span>
                    </td>
                    <td style="font-size: 7pt; color: #475569;">
                        {{ $row['notes'] ?: '-' }}
                        @if(!empty($row['is_pulang_cepat']))
                            <span style="color: #9333ea; font-weight: bold;">(Pulang Cepat)</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 15px; color: #94a3b8;">
                        Tidak ada data pegawai yang sesuai.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
