<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\TeacherAdministration;

class AcademicYearService
{
    public const DEFAULT_YEARS = [
        '2024/2025',
        '2025/2026',
        '2026/2027',
        '2027/2028',
    ];

    /**
     * Dapatkan daftar seluruh tahun ajaran yang terdaftar.
     */
    public function getAcademicYears(): array
    {
        $settingValue = Setting::where('key', 'academic_years')->value('value');
        $years = [];

        if ($settingValue) {
            $decoded = json_decode($settingValue, true);
            if (is_array($decoded)) {
                $years = $decoded;
            }
        }

        if (empty($years)) {
            $years = self::DEFAULT_YEARS;
        }

        // Sertakan juga tahun ajaran yang mungkin sudah ada di database berkas
        $dbYears = TeacherAdministration::distinct()->pluck('academic_year')->filter()->toArray();
        $years = array_unique(array_merge($years, $dbYears));

        // Pastikan tahun aktif kalender ada di daftar
        [$autoYear] = $this->calculateAutoPeriod();
        if (!in_array($autoYear, $years)) {
            $years[] = $autoYear;
        }

        // Urutkan tahun ajaran secara kronologis
        usort($years, function ($a, $b) {
            $yearA = (int) explode('/', $a)[0];
            $yearB = (int) explode('/', $b)[0];
            return $yearA <=> $yearB;
        });

        return array_values($years);
    }

    /**
     * Hitung periode otomatis kalender berdasarkan tanggal saat ini.
     */
    public function calculateAutoPeriod(): array
    {
        $now = now();
        $year = $now->year;
        $month = $now->month;

        if ($month >= 7) {
            $academicYear = $year . '/' . ($year + 1);
            $semester = 'ganjil';
        } else {
            $academicYear = ($year - 1) . '/' . $year;
            $semester = 'genap';
        }

        return [$academicYear, $semester];
    }

    /**
     * Dapatkan tahun ajaran aktif dari pengaturan sistem (fallback ke auto-calculate).
     */
    public function getActiveYear(): string
    {
        $active = Setting::where('key', 'active_academic_year')->value('value');
        if (!empty($active)) {
            return $active;
        }

        [$autoYear] = $this->calculateAutoPeriod();
        return $autoYear;
    }

    /**
     * Dapatkan semester aktif dari pengaturan sistem (fallback ke auto-calculate).
     */
    public function getActiveSemester(): string
    {
        $active = Setting::where('key', 'active_semester')->value('value');
        if (!empty($active) && in_array($active, ['ganjil', 'genap'])) {
            return $active;
        }

        [, $autoSemester] = $this->calculateAutoPeriod();
        return $autoSemester;
    }

    /**
     * Dapatkan daftar tahun ajaran yang diarsipkan.
     */
    public function getArchivedYears(): array
    {
        $settingValue = Setting::where('key', 'archived_academic_years')->value('value');
        if ($settingValue) {
            $decoded = json_decode($settingValue, true);
            if (is_array($decoded)) {
                return array_values($decoded);
            }
        }

        return [];
    }

    /**
     * Cek apakah suatu tahun ajaran berstatus diarsipkan.
     */
    public function isArchived(string $year): bool
    {
        return in_array($year, $this->getArchivedYears(), true);
    }

    /**
     * Tambahkan tahun ajaran baru.
     */
    public function addYear(string $year): array
    {
        $year = trim($year);
        if (!preg_match('/^\d{4}\/\d{4}$/', $year)) {
            throw new \InvalidArgumentException('Format tahun ajaran tidak valid. Gunakan format YYYY/YYYY (contoh: 2026/2027).');
        }

        [$startYear, $endYear] = explode('/', $year);
        if ((int)$endYear !== (int)$startYear + 1) {
            throw new \InvalidArgumentException('Tahun akhir harus tepat 1 tahun setelah tahun awal (contoh: 2026/2027).');
        }

        $years = $this->getAcademicYears();
        if (!in_array($year, $years, true)) {
            $years[] = $year;
            usort($years, function ($a, $b) {
                return (int) explode('/', $a)[0] <=> (int) explode('/', $b)[0];
            });

            Setting::updateOrCreate(
                ['key' => 'academic_years'],
                ['value' => json_encode(array_values($years))]
            );
        }

        return $years;
    }

    /**
     * Hapus tahun ajaran dari daftar (jika bukan tahun aktif).
     */
    public function removeYear(string $year): void
    {
        $activeYear = $this->getActiveYear();
        if ($year === $activeYear) {
            throw new \InvalidArgumentException("Tahun ajaran {$year} tidak dapat dihapus karena saat ini berstatus aktif.");
        }

        $years = array_values(array_diff($this->getAcademicYears(), [$year]));
        Setting::updateOrCreate(
            ['key' => 'academic_years'],
            ['value' => json_encode($years)]
        );

        // Hapus juga dari daftar arsip jika ada
        $archived = array_values(array_diff($this->getArchivedYears(), [$year]));
        Setting::updateOrCreate(
            ['key' => 'archived_academic_years'],
            ['value' => json_encode($archived)]
        );
    }

    /**
     * Tetapkan tahun ajaran & semester aktif.
     */
    public function setActivePeriod(string $year, string $semester): void
    {
        $years = $this->getAcademicYears();
        if (!in_array($year, $years, true)) {
            $this->addYear($year);
        }

        if (!in_array($semester, ['ganjil', 'genap'], true)) {
            throw new \InvalidArgumentException('Semester harus berupa ganjil atau genap.');
        }

        Setting::updateOrCreate(['key' => 'active_academic_year'], ['value' => $year]);
        Setting::updateOrCreate(['key' => 'active_semester'], ['value' => $semester]);

        // Jika tahun ajaran ini sebelumnya diarsipkan, lepaskan arsipnya karena dijadikan aktif
        if ($this->isArchived($year)) {
            $this->setArchived($year, false);
        }
    }

    /**
     * Atur status arsip tahun ajaran.
     */
    public function setArchived(string $year, bool $isArchived): void
    {
        $activeYear = $this->getActiveYear();
        if ($isArchived && $year === $activeYear) {
            throw new \InvalidArgumentException("Tahun ajaran aktif ({$year}) tidak dapat diarsipkan. Ubah tahun ajaran aktif terlebih dahulu jika ingin mengarsipkan tahun ini.");
        }

        $archived = $this->getArchivedYears();
        if ($isArchived) {
            if (!in_array($year, $archived, true)) {
                $archived[] = $year;
            }
        } else {
            $archived = array_values(array_diff($archived, [$year]));
        }

        Setting::updateOrCreate(
            ['key' => 'archived_academic_years'],
            ['value' => json_encode(array_values($archived))]
        );
    }

    /**
     * Dapatkan data ringkasan lengkap untuk UI Admin.
     */
    public function getAdminSummary(): array
    {
        $years = $this->getAcademicYears();
        $activeYear = $this->getActiveYear();
        $activeSemester = $this->getActiveSemester();
        $archivedYears = $this->getArchivedYears();

        // Hitung statistik dokumen per tahun ajaran
        $docCounts = TeacherAdministration::selectRaw('academic_year, count(*) as count')
            ->groupBy('academic_year')
            ->pluck('count', 'academic_year')
            ->toArray();

        $items = [];
        foreach ($years as $year) {
            $isAct = ($year === $activeYear);
            $isArch = in_array($year, $archivedYears, true);
            $items[] = [
                'year'       => $year,
                'is_active'  => $isAct,
                'is_archived'=> $isArch,
                'doc_count'  => $docCounts[$year] ?? 0,
            ];
        }

        return [
            'years'           => $items,
            'years_list'      => $years,
            'active_year'     => $activeYear,
            'active_semester' => $activeSemester,
            'archived_years'  => $archivedYears,
        ];
    }
}
