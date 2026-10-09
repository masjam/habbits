<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExportReadyNotification extends Notification
{
    use Queueable;

    public $fileName;
    public $fileUrl;
    public $type;

    /**
     * Create a new notification instance.
     */
    public function __construct($fileName, $fileUrl, $type)
    {
        $this->fileName = $fileName;
        $this->fileUrl = $fileUrl;
        $this->type = $type;
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
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $judul = "Laporan {$this->type} siap diunduh";
        return [
            'title' => $judul,
            'message' => "File {$this->fileName} telah berhasil digenerate.",
            'url' => $this->fileUrl,
            'icon' => 'download',
        ];
    }
}
