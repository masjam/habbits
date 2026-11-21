<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Private channel: hanya user yang bersangkutan boleh subscribe
Broadcast::channel('exports.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
