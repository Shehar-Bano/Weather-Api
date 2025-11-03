<?php

namespace App\Observers;

use App\Models\UserDetail;
use Illuminate\Support\Facades\Http;

class UserDetailObserver
{
    public function saved(UserDetail $user)
    {
        if ($user->device_token && $user->city) {
            Http::withHeaders([
                'Authorization' => 'Basic '.env('ONESIGNAL_API_KEY'),
                'Content-Type' => 'application/json',
            ])->put("https://onesignal.com/api/v1/players/{$user->device_token}", [
                'app_id' => env('ONESIGNAL_APP_ID'),
                'tags' => ['city' => $user->city],
            ]);
        }
    }
}
