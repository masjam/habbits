<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekap Presensi Bulanan - {{ $monthName }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 7mm 7mm 8mm 7mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7pt;
            color: #1e293b;
            line-height: 1.15;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 8px;
            border-bottom: 1.5px solid #0f172a;
            padding-bottom: 5px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-title {
            font-size: 11pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-subtitle {
            font-size: 8pt;
            color: #475569;
            margin-top: 1px;
        }
        .header-meta {
            text-align: right;
            font-size: 7pt;
            color: #64748b;
        }
        .legend-box {
            margin-bottom: 6px;
            font-size: 6.5pt;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 3px;
            padding: 4px 6px;
        }
        .legend-item {
            display: inline-block;
            margin-right: 12px;
        }
        .badge {
            display: inline-block;
            width: 8px;
            height: 8px;
            vertical-align: middle;
            margin-right: 3px;
            border-radius: 1px;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.5pt;
        }
        .table-data th, .table-data td {
            border: 0.5px solid #cbd5e1;
            padding: 2px 1.5px;
            text-align: center;
            vertical-align: middle;
        }
        .table-data th {
            font-weight: bold;
            background-color: #f1f5f9;
            color: #334155;
            font-size: 6.5pt;
        }
        .col-header-regular {
            background-color: #059669 !important;
            color: #ffffff !important;
        }
        .col-header-weekend {
            background-color: #ef4444 !important;
            color: #ffffff !important;
        }
        /* BLOK WARNA KHUSUS UNTUK TANGGAL KHUSUS */
        .col-header-special {
            background-color: #7c3aed !important;
            color: #ffffff !important;
            font-weight: bold;
        }
        .col-cell-special {
            background-color: #ede9fe !important;
            border-left: 1px solid #8b5cf6 !important;
            border-right: 1px solid #8b5cf6 !important;
        }
        .col-cell-weekend {
            background-color: #fee2e2 !important;
        }
        .text-left {
            text-align: left !important;
            padding-left: 4px !important;
        }
        .text-bold {
            font-weight: bold;
        }
        .text-time {
            font-size: 5.5pt;
            line-height: 1;
            display: block;
        }
        .status-badge {
            font-size: 5.5pt;
            font-weight: bold;
            display: block;
            padding: 0.5px;
            border-radius: 2px;
        }
        .status-sakit { color: #4338ca; }
        .status-izin { color: #6d28d9; }
        .status-cuti { color: #0f766e; }
        .status-alpa { color: #be123c; font-weight: bold; }
        .footer-note {
            margin-top: 6px;
            font-size: 6.5pt;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="text-align: left;">
                    <div class="header-title">REKAP PRESENSI BULANAN PEGAWAI</div>
                    <div class="header-subtitle">Periode: <strong>{{ $monthName }}</strong> | Divisi: <strong>{{ $divisionFilter ?: 'Semua Divisi' }}</strong></div>
                </td>
                <td class="header-meta">
                    <div>Dicetak pada: {{ now()->locale('id')->isoFormat('D MMMM Y, HH:mm') }} WIB</div>
                    <div>Habit Tracking &amp; HR System</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Legenda & Keterangan Warna Kolom -->
    <div class="legend-box">
        <div class="legend-item">
            <span class="badge" style="background-color: #059669;"></span> <strong>Hijau:</strong> Hari Kerja Reguler
        </div>
        <div class="legend-item">
            <span class="badge" style="background-color: #ef4444;"></span> <strong>Merah:</strong> Akhir Pekan (Weekend)
        </div>
        <div class="legend-item">
            <span class="badge" style="background-color: #7c3aed;"></span> <strong style="color: #6d28d9;">Ungu: Tanggal Khusus (Jadwal Khusus Aktif)</strong>
        </div>
        @if(count($specialSchedulesList ?? []) > 0)
        <div style="margin-top: 2px; color: #6d28d9; font-weight: bold;">
            ✨ Jadwal Khusus Terdaftar: 
            @foreach($specialSchedulesList as $s)
                {{ $s['name'] }} ({{ $s['time_in'] }}-{{ $s['time_out'] }}){{ !$loop->last ? '; ' : '' }}
            @endforeach
        </div>
        @endif
    </div>

    <table class="table-data">
        <thead>
            <tr>
                <th rowspan="2" style="width: 18px;">No</th>
                <th rowspan="2" style="width: 110px;">Nama Pegawai</th>
                <th rowspan="2" style="width: 55px;">Divisi</th>
                <!-- Kolom Tanggal 1 s/d DaysInMonth -->
                @foreach($daysList as $d)
                    <th class="{{ $d['is_special'] ? 'col-header-special' : ($d['is_weekend'] ? 'col-header-weekend' : 'col-header-regular') }}" style="width: 17px;">
                        {{ $d['day_name'] }}
                    </th>
                @endforeach
                <!-- Kolom Ringkasan -->
                <th rowspan="2" style="width: 20px;">⭐</th>
                <th rowspan="2" style="width: 20px;">H</th>
                <th rowspan="2" style="width: 20px;">T</th>
                <th rowspan="2" style="width: 22px;">DL</th>
                <th rowspan="2" style="width: 22px;">PC</th>
                <th rowspan="2" style="width: 20px;">S</th>
                <th rowspan="2" style="width: 20px;">I</th>
                <th rowspan="2" style="width: 20px;">C</th>
                <th rowspan="2" style="width: 20px;">A</th>
                <th rowspan="2" style="width: 26px;">Total</th>
                <th rowspan="2" style="width: 28px;">%</th>
            </tr>
            <tr>
                @foreach($daysList as $d)
                    <th class="{{ $d['is_special'] ? 'col-header-special' : ($d['is_weekend'] ? 'col-header-weekend' : 'col-header-regular') }}">
                        {{ $d['day'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($monthlyReportData as $idx => $user)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td class="text-left text-bold" style="white-space: nowrap; overflow: hidden;">
                        {{ $user['name'] }}
                    </td>
                    <td style="white-space: nowrap; overflow: hidden; font-size: 6pt;">
                        {{ $user['divisi'] ?: '-' }}
                    </td>

                    <!-- Cells per Tanggal -->
                    @foreach($daysList as $d)
                        @php
                            $rec = $user['daily_records'][$d['day']] ?? null;
                            $cellClass = '';
                            if ($d['is_special']) {
                                $cellClass = 'col-cell-special';
                            } elseif ($d['is_weekend']) {
                                $cellClass = 'col-cell-weekend';
                            }
                        @endphp
                        <td class="{{ $cellClass }}">
                            @if($rec)
                                @if(in_array($rec['status'], ['sakit', 'izin', 'cuti', 'alpa']))
                                    <span class="status-badge status-{{ $rec['status'] }}">
                                        {{ strtoupper(substr($rec['status'], 0, 1)) }}
                                    </span>
                                @else
                                    <span class="text-time">{{ $rec['time_in'] ?: '--:--' }}</span>
                                    <span class="text-time" style="color: #64748b;">{{ $rec['time_out'] ?: '--:--' }}</span>
                                @endif
                            @else
                                <span style="color: #94a3b8;">-</span>
                            @endif
                        </td>
                    @endforeach

                    <!-- Kolom Akumulasi -->
                    <td style="color: #d97706; font-weight: bold;">{{ $user['total_pure_ontime'] ?? 0 }}</td>
                    <td style="color: #059669; font-weight: bold;">{{ $user['total_hadir'] }}</td>
                    <td style="color: #d97706; font-weight: bold;">{{ $user['total_terlambat'] }}</td>
                    <td style="color: #2563eb; font-weight: bold;">{{ $user['total_dinas_luar'] }}</td>
                    <td style="color: #9333ea; font-weight: bold;">{{ $user['total_pulang_cepat'] }}</td>
                    <td style="color: #4f46e5;">{{ $user['total_sakit'] ?? 0 }}</td>
                    <td style="color: #7c3aed;">{{ $user['total_izin'] ?? 0 }}</td>
                    <td style="color: #0d9488;">{{ $user['total_cuti'] ?? 0 }}</td>
                    <td style="color: #e11d48; font-weight: bold;">{{ $user['total_alpa'] ?? 0 }}</td>
                    <td class="text-bold">{{ $user['total_kehadiran'] }}</td>
                    <td class="text-bold" style="color: #059669;">{{ $user['attendance_rate'] }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($daysList) + 14 }}" style="padding: 15px; color: #94a3b8;">
                        Tidak ada data pegawai yang sesuai.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer-note">
        * Keterangan Singkatan: ⭐ (Tepat Waktu Murni), H (Hadir/Tepat Waktu), T (Terlambat), DL (Dinas Luar), PC (Pulang Mendahului), S (Sakit), I (Izin), C (Cuti), A (Alpa).
    </div>
</body>
</html>
