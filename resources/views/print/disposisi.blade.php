<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Lembar Disposisi</title>
    <style>
        body { font-family: 'Times New Roman', Times, serif; font-size: 14px; line-height: 1.5; color: #000; margin: 0; padding: 20px; }
        .kop-surat { text-align: center; border-bottom: 3px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .kop-surat h2, .kop-surat h3, .kop-surat p { margin: 0; }
        .kop-surat h2 { font-size: 18px; text-transform: uppercase; }
        .kop-surat h3 { font-size: 16px; margin-top: 5px; }
        .kop-surat p { font-size: 12px; margin-top: 5px; }
        .title { text-align: center; font-size: 16px; font-weight: bold; margin-bottom: 20px; text-decoration: underline; text-transform: uppercase; }
        .table-info { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table-info td { padding: 8px; border: 1px solid #000; vertical-align: top; }
        .label { width: 150px; font-weight: bold; background-color: #f9f9f9; }
        .signatures { margin-top: 50px; display: flex; justify-content: space-between; }
        .signature-box { text-align: center; width: 250px; }
        .signature-name { margin-top: 80px; font-weight: bold; text-decoration: underline; }
        @media print {
            body { padding: 0; }
            button { display: none; }
        }
        .btn-print { margin-bottom: 20px; padding: 10px 20px; font-size: 16px; cursor: pointer; }
    </style>
</head>
<body onload="window.print()">
    <button class="btn-print" onclick="window.print()">Cetak</button>

    <div class="kop-surat">
        <h2>LEMBAR DISPOSISI</h2>
        <h3>{{ config('app.name', 'Instansi') }}</h3>
    </div>

    <div class="title">LEMBAR DISPOSISI</div>

    <table class="table-info">
        <tr>
            <td class="label">Pemberi Disposisi</td>
            <td>{{ $disposisi->pemberi->name ?? '-' }}</td>
            <td class="label">Penerima Disposisi</td>
            <td>{{ $disposisi->penerima->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Nomor Surat</td>
            <td>{{ $surat->nomor_surat ?? '-' }}</td>
            <td class="label">Tanggal Diterima</td>
            <td>{{ $surat->tanggal_diterima ? \Carbon\Carbon::parse($surat->tanggal_diterima)->format('d-m-Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Pengirim Surat</td>
            <td colspan="3">{{ $surat->pengirim ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Perihal</td>
            <td colspan="3">{!! strip_tags($surat->perihal ?? '-') !!}</td>
        </tr>
        <tr>
            <td class="label">Batas Waktu</td>
            <td colspan="3">{{ $disposisi->batas_waktu ? \Carbon\Carbon::parse($disposisi->batas_waktu)->format('d-m-Y') : 'Tidak ada' }}</td>
        </tr>
        <tr>
            <td class="label" style="height: 100px;">Instruksi / Pesan</td>
            <td colspan="3">{{ $disposisi->instruksi }}</td>
        </tr>
    </table>

    <div class="signatures">
        <div class="signature-box">
            <p>Penerima Disposisi,</p>
            <div class="signature-name">{{ $disposisi->penerima->name ?? '-' }}</div>
        </div>
        <div class="signature-box">
            <p>Pemberi Disposisi,</p>
            <div class="signature-name">{{ $disposisi->pemberi->name ?? '-' }}</div>
        </div>
    </div>
</body>
</html>
