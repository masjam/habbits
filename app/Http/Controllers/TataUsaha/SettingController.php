<?php

namespace App\Http\Controllers\TataUsaha;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::whereIn('key', [
            'kop_logo_kiri',
            'kop_logo_kanan',
            'kop_baris_1',
            'kop_baris_2',
            'kop_baris_3',
            'kop_alamat',
            'kop_kontak',
            'kepsek_nama',
            'kepsek_nbm'
        ])->pluck('value', 'key')->toArray();

        // set defaults if empty
        $settings['kop_baris_1'] = $settings['kop_baris_1'] ?? 'MUHAMMADIYAH MAJELIS PENDIDIKAN DASAR MENENGAH DAN PNF';
        $settings['kop_baris_2'] = $settings['kop_baris_2'] ?? 'SD MUH. AL MUJAHIDIN WONOSARI';
        $settings['kop_baris_3'] = $settings['kop_baris_3'] ?? 'BOARDING AND FULLDAY ELEMENTARY SCHOOL';
        $settings['kop_alamat'] = $settings['kop_alamat'] ?? 'Kampus : Jl. Mayang Gadungsari, Wonosari, Gunungkidul, DIY Telp/Fax (0274)391147';
        $settings['kop_kontak'] = $settings['kop_kontak'] ?? 'e-mail : admin@sdmujahidin-wns.sch.id http://www.sdmujahidin-wns.sch.id';
        $settings['kepsek_nama'] = $settings['kepsek_nama'] ?? 'Joko Kiswanto, S.Pd.I, M.Pd';
        $settings['kepsek_nbm'] = $settings['kepsek_nbm'] ?? '1.032.440';

        return Inertia::render('TataUsaha/Settings/Index', [
            'settings' => $settings
        ]);
    }

    public function update(Request $request)
    {
        $rules = [
            'kop_baris_1' => 'nullable|string',
            'kop_baris_2' => 'nullable|string',
            'kop_baris_3' => 'nullable|string',
            'kop_alamat' => 'nullable|string',
            'kop_kontak' => 'nullable|string',
            'kepsek_nama' => 'nullable|string',
            'kepsek_nbm' => 'nullable|string',
            'logo_kiri' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'logo_kanan' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ];

        $data = $request->validate($rules);

        $keysToUpdate = ['kop_baris_1', 'kop_baris_2', 'kop_baris_3', 'kop_alamat', 'kop_kontak', 'kepsek_nama', 'kepsek_nbm'];
        foreach ($keysToUpdate as $key) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $data[$key] ?? '']
            );
        }

        if ($request->hasFile('logo_kiri')) {
            $path = $request->file('logo_kiri')->store('logos', 'public');
            Setting::updateOrCreate(
                ['key' => 'kop_logo_kiri'],
                ['value' => $path]
            );
        }

        if ($request->hasFile('logo_kanan')) {
            $path = $request->file('logo_kanan')->store('logos', 'public');
            Setting::updateOrCreate(
                ['key' => 'kop_logo_kanan'],
                ['value' => $path]
            );
        }

        if ($request->boolean('remove_logo_kiri')) {
            Setting::where('key', 'kop_logo_kiri')->delete();
        }
        if ($request->boolean('remove_logo_kanan')) {
            Setting::where('key', 'kop_logo_kanan')->delete();
        }

        return redirect()->back()->with('success', 'Pengaturan Kop Surat berhasil diperbarui.');
    }
}
