<?php

namespace App\Http\Controllers;

use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserDetailController extends Controller
{
    // 🟢 CREATE
    public function store(Request $req)
    {
        $data = $req->validate([
            'device_token' => 'required|string|unique:user_details,device_token',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'lon' => 'nullable|numeric',
        ]);

        $user = UserDetail::create($data);

        // Register device token with OneSignal
        $this->registerWithOneSignal($user);

        return response()->json(['message' => 'User added', 'data' => $user]);
    }

    /**
     * Register/Update device token with OneSignal
     * The device_token is assumed to be a OneSignal player_id from the client SDK
     * We update the player with external_user_id for better tracking
     */
    private function registerWithOneSignal(UserDetail $user)
    {
        $appId = env('ONESIGNAL_APP_ID');
        $apiKey = env('ONESIGNAL_API_KEY');

        if (!$appId || !$apiKey) {
            Log::warning('OneSignal credentials not configured');
            return;
        }

        try {
            // Update existing player with external_user_id
            // The device_token is already a OneSignal player_id from the client SDK
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Basic ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->put("https://onesignal.com/api/v1/players/{$user->device_token}", [
                'app_id' => $appId,
                'external_user_id' => (string) $user->id,
            ]);

            if ($response->successful()) {
                Log::info("Device token updated in OneSignal: {$user->device_token}", [
                    'user_id' => $user->id,
                    'external_user_id' => (string) $user->id,
                ]);
            } else {
                // If update fails, try to create a new player (in case it doesn't exist)
                $createResponse = Http::timeout(10)->withHeaders([
                    'Authorization' => 'Basic ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])->post('https://onesignal.com/api/v1/players', [
                    'app_id' => $appId,
                    'device_type' => 2, // 2 = Web (default), can be adjusted based on your app
                    'identifier' => $user->device_token,
                    'external_user_id' => (string) $user->id,
                ]);

                if ($createResponse->successful()) {
                    Log::info("Device token created in OneSignal: {$user->device_token}");
                } else {
                    Log::error("Failed to register device token with OneSignal", [
                        'device_token' => $user->device_token,
                        'update_response' => $response->json(),
                        'create_response' => $createResponse->json(),
                        'update_status' => $response->status(),
                        'create_status' => $createResponse->status(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Exception while registering device token with OneSignal", [
                'device_token' => $user->device_token,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // 🟡 READ (all users)
    public function index()
    {
        return response()->json(UserDetail::all());
    }

    // 🔵 UPDATE by id
    public function update(Request $req, $id)
    {
        $data = $req->validate([
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'lon' => 'nullable|numeric',
        ]);

        $user = UserDetail::findOrFail($id);
        $user->update($data);

        return response()->json(['message' => 'Updated successfully', 'data' => $user]);
    }

    // 🔴 DELETE
    public function destroy($id)
    {
        $user = UserDetail::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
