<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class GenericWebPushNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = '/dashboard',
        public ?string $icon = '/img/gh.png'
    ) {}

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon($this->icon ?: '/img/gh.png')
            ->badge('/img/gh.png')
            ->data(['url' => $this->url ?: '/dashboard'])
            ->action('Buka Aplikasi', 'open');
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url ?: '/dashboard',
            'icon' => $this->icon ?: '/img/gh.png',
        ];
    }
}
