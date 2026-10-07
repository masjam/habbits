<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisposisiNotification extends Notification
{
    use Queueable;

    protected $disposisi;

    /**
     * Create a new notification instance.
     */
    public function __construct($disposisi)
    {
        $this->disposisi = $disposisi;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $surat = $this->disposisi->suratMasuk;
        $instruksi = $this->disposisi->instruksi;
        $pemberi = $this->disposisi->pemberi->name ?? 'Pimpinan';
        
        $action_links = [
            [
                'label' => 'Lihat Disposisi',
                'url' => route('tata-usaha.disposisi.print', $this->disposisi->id)
            ]
        ];

        if ($surat->file_path) {
            $action_links[] = [
                'label' => 'Buka Surat Asli',
                'url' => '/storage/' . $surat->file_path
            ];
        }

        return [
            'type' => 'disposisi',
            'title' => 'Disposisi Baru: ' . $surat->perihal,
            'body' => "Instruksi dari {$pemberi}: \"{$instruksi}\"",
            'url' => null, // don't redirect the whole card if there are action links
            'action_links' => $action_links
        ];
    }
}
