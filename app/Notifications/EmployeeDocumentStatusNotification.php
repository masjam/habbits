<?php

namespace App\Notifications;

use App\Models\EmployeeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class EmployeeDocumentStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public EmployeeDocument $document,
        public string $status, // 'verified', 'pending', 'rejected'
        public ?string $notes = null
    ) {}

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $statusText = match ($this->status) {
            'verified' => 'Valid & Disetujui',
            'rejected' => 'Ditolak',
            default => 'Menunggu Verifikasi',
        };

        $body = "Status dokumen '{$this->document->title}' telah diperbarui menjadi {$statusText}.";
        if ($this->notes) {
            $body .= " Catatan: {$this->notes}";
        }

        return (new WebPushMessage)
            ->title('Pembaruan Status Arsip Dokumen')
            ->body($body)
            ->icon('/img/gh.png')
            ->badge('/img/gh.png')
            ->data(['url' => route('employee-documents.index')])
            ->action('Buka Arsip', 'open');
    }

    public function toArray($notifiable): array
    {
        $statusText = match ($this->status) {
            'verified' => 'Valid & Disetujui',
            'rejected' => 'Ditolak',
            default => 'Menunggu Verifikasi',
        };

        $body = "Status dokumen '{$this->document->title}' telah diperbarui menjadi {$statusText}.";
        if ($this->notes) {
            $body .= " Catatan: {$this->notes}";
        }

        return [
            'type' => 'employee_document_status',
            'document_id' => $this->document->id,
            'status' => $this->status,
            'title' => 'Pembaruan Status Arsip Dokumen: ' . $this->document->title,
            'body' => $body,
            'url' => route('employee-documents.index'),
            'icon' => '/img/gh.png',
        ];
    }
}
