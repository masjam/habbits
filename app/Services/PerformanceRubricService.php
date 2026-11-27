<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PerformanceRubricService
{
    public const SETTING_KEY = 'performance_rubric_override';

    public function getRubric(): array
    {
        $base = config('performance_indicators', []);

        try {
            $raw = Setting::get(self::SETTING_KEY);
        } catch (\Throwable) {
            return $base;
        }

        if (!$raw) {
            return $base;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['categories'])) {
            return $base;
        }

        return $decoded;
    }

    /**
     * Simpan perubahan indikator per aspek untuk kategori guru & karyawan.
     *
     * @param  array<string, array<string, array<int, array{code?: ?string, label: string, description?: ?string}>>>  $categoriesInput
     */
    public function saveIndicators(array $categoriesInput): array
    {
        $rubric = $this->getRubric();

        foreach (['guru', 'karyawan'] as $catKey) {
            if (!isset($categoriesInput[$catKey]) || !isset($rubric['categories'][$catKey]['aspects'])) {
                continue;
            }

            foreach ($rubric['categories'][$catKey]['aspects'] as $aspectKey => &$aspectMeta) {
                if (!isset($categoriesInput[$catKey][$aspectKey])) {
                    continue;
                }

                $submittedList = $categoriesInput[$catKey][$aspectKey];
                if (!is_array($submittedList) || count($submittedList) < 1) {
                    throw new InvalidArgumentException("Aspek '{$aspectMeta['label']}' wajib memiliki minimal 1 komponen indikator.");
                }

                $usedCodes = [];
                $cleaned = [];
                $prefix = substr($catKey, 0, 1) . '_' . substr($aspectKey, 0, 3) . '_';

                foreach ($submittedList as $item) {
                    $label = trim((string) ($item['label'] ?? ''));
                    if ($label === '') {
                        throw new InvalidArgumentException("Nama komponen indikator pada aspek '{$aspectMeta['label']}' tidak boleh kosong.");
                    }

                    $code = trim((string) ($item['code'] ?? ''));
                    if ($code === '' || !preg_match('/^[a-z0-9_]+$/', $code)) {
                        $baseCode = $prefix . Str::slug($label, '_');
                        $code = substr($baseCode, 0, 48);
                    }

                    // Pastikan unik dalam aspek
                    $uniqueCode = $code;
                    $suffix = 2;
                    while (in_array($uniqueCode, $usedCodes, true)) {
                        $uniqueCode = substr($code, 0, 44) . '_' . $suffix++;
                    }
                    $usedCodes[] = $uniqueCode;

                    $cleaned[] = [
                        'code'        => $uniqueCode,
                        'label'       => mb_substr($label, 0, 120),
                        'description' => mb_substr(trim((string) ($item['description'] ?? '')), 0, 255),
                    ];
                }

                $aspectMeta['indicators'] = $cleaned;
            }
            unset($aspectMeta);
        }

        $rubric['version'] = 'custom-' . now()->format('YmdHis');

        Setting::updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($rubric, JSON_UNESCAPED_UNICODE)]
        );
        Setting::clearCache();

        return $rubric;
    }

    public function resetToDefault(): void
    {
        Setting::where('key', self::SETTING_KEY)->delete();
        Setting::clearCache();
    }
}
