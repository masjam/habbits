<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExportReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $fileName;
    public string $fileUrl;
    public string $label;

    public function __construct(int $userId, string $fileName, string $fileUrl, string $label)
    {
        $this->userId   = $userId;
        $this->fileName = $fileName;
        $this->fileUrl  = $fileUrl;
        $this->label    = $label;
    }

    /**
     * Broadcast ke private channel milik user yang meminta export.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('exports.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ready';
    }

    public function broadcastWith(): array
    {
        return [
            'fileName' => $this->fileName,
            'fileUrl'  => $this->fileUrl,
            'label'    => $this->label,
        ];
    }
}
