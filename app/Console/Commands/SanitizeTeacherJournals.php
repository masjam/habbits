<?php

namespace App\Console\Commands;

use App\Models\TeacherJournal;
use Illuminate\Console\Command;
use Stevebauman\Purify\Facades\Purify;

class SanitizeTeacherJournals extends Command
{
    protected $signature = 'journals:sanitize {--dry-run : Hanya hitung, tanpa mengubah data}';

    protected $description = 'Sanitasi kolom material jurnal guru lama (hapus script/atribut berbahaya).';

    public function handle(): int
    {
        $changed = 0;

        TeacherJournal::query()->chunkById(200, function ($journals) use (&$changed) {
            foreach ($journals as $journal) {
                $raw = (string) $journal->getRawOriginal('material');
                $clean = Purify::clean($raw);

                if ($clean !== $raw) {
                    $changed++;
                    if (!$this->option('dry-run')) {
                        // Tulis langsung agar tidak disanitasi dua kali & tanpa event/cache
                        TeacherJournal::whereKey($journal->getKey())->update(['material' => $clean]);
                    }
                }
            }
        });

        $this->info(($this->option('dry-run') ? '[dry-run] ' : '') . "{$changed} jurnal diubah.");

        return self::SUCCESS;
    }
}
