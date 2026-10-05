<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Keluar</title>
    <style>
        @font-face {
            font-family: 'Amiri';
            src: url("data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(public_path('fonts/Amiri-Regular.ttf'))) }}") format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        @page {
            margin: 1.5cm 2cm 2.5cm 2cm;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
        }
        .kop-surat {
            text-align: center;
            border-bottom: 3px solid #000;
            padding-bottom: 10px;
            margin-bottom: 5px;
            position: relative;
            padding: 0 75px; /* Prevent text overlapping with absolute logos */
        }
        .kop-surat img.logo-left {
            position: absolute;
            left: 0;
            top: 0;
            width: 75px;
        }
        .kop-surat img.logo-right {
            position: absolute;
            right: 0;
            top: 0;
            width: 75px;
        }
        .kop-header-1 {
            font-size: 10pt;
            font-weight: normal;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }
        .kop-header-2 {
            font-size: 16pt;
            font-weight: bold;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }
        .kop-header-3 {
            font-size: 10pt;
            font-weight: bold;
            margin: 0;
            padding: 0;
            letter-spacing: 1px;
            line-height: 1.2;
        }
        .kop-address {
            font-size: 9pt;
            margin-top: 2px;
            font-style: italic;
            line-height: 1.3;
        }
        .bismillah {
            text-align: center;
            font-size: 16pt;
            font-family: 'Amiri', serif;
            margin-top: 0px;
            margin-bottom: 15px;
        }
        .surat-info {
            width: 100%;
            margin-bottom: 20px;
        }
        .surat-info td {
            vertical-align: top;
        }
        .tujuan {
            margin-bottom: 20px;
        }
        .salam-pembuka {
            margin-bottom: 15px;
        }
        .arabic-text {
            font-size: 14pt;
            font-family: 'Amiri', serif;
            text-align: right;
            margin-bottom: 10px;
        }
        .isi-surat {
            text-align: justify;
            margin-bottom: 15px;
        }
        .salam-penutup {
            margin-top: 20px;
            margin-bottom: 30px;
        }
        .signature-area {
            float: right;
            width: 250px;
            text-align: left; /* Rata Kiri sesuai request */
            margin-top: 10px;
        }
        .signature-space {
            height: 80px;
        }
        .kepsek-name {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 2px;
        }
        
        /* TinyMCE content fixes */
        .isi-surat p {
            margin-top: 0;
            margin-bottom: 10px;
        }
        .isi-surat ol, .isi-surat ul {
            margin-top: 0;
            margin-bottom: 10px;
            padding-left: 20px;
        }
    </style>
</head>
<body>

    @php
        $settings = \App\Models\Setting::whereIn('key', ['kop_logo_kiri', 'kop_logo_kanan', 'kop_baris_1', 'kop_baris_2', 'kop_baris_3', 'kop_alamat', 'kop_kontak'])->pluck('value', 'key')->toArray();
        $logoKiri = $settings['kop_logo_kiri'] ?? null;
        $logoKanan = $settings['kop_logo_kanan'] ?? null;
    @endphp

    <div class="kop-surat">
        @if($logoKiri && file_exists(public_path('storage/' . $logoKiri)))
            <img src="{{ public_path('storage/' . $logoKiri) }}" class="logo-left" alt="Logo Kiri">
        @endif
        @if($logoKanan && file_exists(public_path('storage/' . $logoKanan)))
            <img src="{{ public_path('storage/' . $logoKanan) }}" class="logo-right" alt="Logo Kanan">
        @endif
        
        <p class="kop-header-1">{{ $settings['kop_baris_1'] ?? 'MUHAMMADIYAH MAJELIS PENDIDIKAN DASAR MENENGAH DAN PNF' }}</p>
        <p class="kop-header-2">{{ $settings['kop_baris_2'] ?? 'SD MUH. AL MUJAHIDIN WONOSARI' }}</p>
        <p class="kop-header-3">{{ $settings['kop_baris_3'] ?? 'BOARDING AND FULLDAY ELEMENTARY SCHOOL' }}</p>
        <div class="kop-address">
            {{ $settings['kop_alamat'] ?? 'Kampus : Jl. Mayang Gadungsari, Wonosari, Gunungkidul, DIY Telp/Fax (0274)391147' }}<br>
            <span style="color: blue; text-decoration: none;">{!! $settings['kop_kontak'] ?? 'e-mail : admin@sdmujahidin-wns.sch.id http://www.sdmujahidin-wns.sch.id' !!}</span>
        </div>
    </div>
    
    <div class="bismillah" dir="rtl">
        {{ $bismillah }}
    </div>

    <table class="surat-info">
        <tr>
            <td width="60%">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="60">Nomor</td>
                        <td width="10">:</td>
                        <td>{{ $nomor_surat }}</td>
                    </tr>
                    <tr>
                        <td>Lamp</td>
                        <td>:</td>
                        <td>{{ $lampiran }}</td>
                    </tr>
                    <tr>
                        <td>Hal</td>
                        <td>:</td>
                        <td>{{ $perihal }}</td>
                    </tr>
                </table>
            </td>
            <td width="40%" style="text-align: right; padding-top: 5px;">
                <div style="border-bottom: 1px solid #000; display: inline-block;">{{ $tanggal_hijriah }}</div><br>
                <div style="display: inline-block;">{{ $tanggal_masehi }}</div>
            </td>
        </tr>
    </table>

    <div class="tujuan">
        Kepada:<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Yth. {{ $kepada }}<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;di {{ $di }}
    </div>

    <div class="salam-pembuka">
        <div class="arabic-text" dir="rtl">
            {{ $salam_pembuka }}
        </div>
        <p style="text-align: justify; margin: 0;">
            Alhamdulillah, puji dan syukur hanya bagi Allah SWT dengan segala limpahan nikmat dan rahmat-Nya. Salam dan shalawat semoga senantiasa tercurah kepada Nabi Muhammad SAW.
        </p>
    </div>

    <div class="isi-surat">
        {!! $isi_surat !!}
    </div>

    <div class="salam-penutup">
        <p style="text-align: justify; margin-bottom: 10px;">
            Demikian surat edaran ini kami sampaikan, atas perhatiannya diucapkan <i>jazakumullahu khairan katsiran.</i>
        </p>
        <div class="arabic-text" dir="rtl">
            {{ $salam_penutup }}
        </div>
    </div>

    <div class="signature-area">
        <p style="margin: 0;">Kepala Sekolah</p>
        <div class="signature-space">
            <!-- Tempat tanda tangan -->
        </div>
        <div class="kepsek-name">{{ $nama_kepsek }}</div>
        <div style="margin: 0;">NBM. {{ $nbm_kepsek }}</div>
    </div>

</body>
</html>
