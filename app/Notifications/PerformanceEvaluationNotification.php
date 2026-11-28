<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PerformanceEvaluationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $eventType, // assignment_created, evaluation_submitted, revision_requested, evaluation_approved, evaluation_edited_by_approver, submission_reminder
        public string $titleText,
        public string $bodyText,
        public string $targetUrl,
        public array $meta = []
    ) {}

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->titleText)
            ->body($this->bodyText)
            ->icon('/img/gh.png')
            ->badge('/img/gh.png')
            ->data(['url' => $this->targetUrl])
            ->action('Buka KPI', 'open');
    }

    public function toArray($notifiable): array
    {
        return [
            'type'  => 'performance_' . $this->eventType,
            'title' => $this->titleText,
            'body'  => $this->bodyText,
            'url'   => $this->targetUrl,
            'icon'  => '/img/gh.png',
            'meta'  => $this->meta,
        ];
    }
}
