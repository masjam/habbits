<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Notulen;

class SendNotulenApprovalNotification implements ShouldQueue
{
    use Queueable;

    public $notulen;

    /**
     * Create a new job instance.
     */
    public function __construct(Notulen $notulen)
    {
        $this->notulen = $notulen;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Di masa depan, ini adalah tempat untuk mengirim Email atau pesan WhatsApp 
        // secara asynchronous (melalui API seperti fonnte/twilio/smtp).
        // Untuk sekarang, kita catat ke log saja.
        \Illuminate\Support\Facades\Log::info("Notifikasi Notulen Disetujui dikirim ke seluruh peserta. ID Rapat: " . $this->notulen->id);
    }
}
