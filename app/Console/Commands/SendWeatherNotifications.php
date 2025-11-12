<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\UserDetail;

class SendWeatherNotifications extends Command
{
    protected $signature = 'weather:notify';
    protected $description = 'Send hourly weather notifications city-wise using OneSignal segments';

    public function handle()
    {
        $appId = env('ONESIGNAL_APP_ID');
        $apiKey = env('ONESIGNAL_API_KEY');
        $weatherKey = env('WEATHER_API_KEY');

        // Validate API keys
        if (!$appId || !$apiKey) {
            $this->error('OneSignal credentials not configured. Please set ONESIGNAL_APP_ID and ONESIGNAL_API_KEY in .env');
            Log::error('OneSignal credentials missing');
            return 1;
        }

        if (!$weatherKey) {
            $this->error('Weather API key not configured. Please set WEATHER_API_KEY in .env');
            Log::error('Weather API key missing');
            return 1;
        }

        $cities = UserDetail::select('city')
            ->whereNotNull('city')
            ->distinct()
            ->pluck('city');

        if ($cities->isEmpty()) {
            $this->warn('No cities found in user_details table');
            return 0;
        }

        $this->info("Processing weather notifications for " . $cities->count() . " cities...");

        $successCount = 0;
        $failureCount = 0;

        foreach ($cities as $city) {
            try {
                // Fetch weather data
                $weatherResponse = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', [
                    'q' => $city . ',PK',
                    'appid' => $weatherKey,
                    'units' => 'metric',
                ]);

                if (!$weatherResponse->successful()) {
                    $this->warn("Failed to fetch weather for $city: HTTP {$weatherResponse->status()}");
                    Log::warning("Weather API failed for city: $city", [
                        'status' => $weatherResponse->status(),
                        'response' => $weatherResponse->body(),
                    ]);
                    $failureCount++;
                    continue;
                }

                $weather = $weatherResponse->json();

                if (!isset($weather['main']) || !isset($weather['weather'][0])) {
                    $this->warn("Skipping $city — invalid weather data structure.");
                    Log::warning("Invalid weather data for city: $city", ['response' => $weather]);
                    $failureCount++;
                    continue;
                }

                $temp = $weather['main']['temp'] ?? 0;
                $humidity = $weather['main']['humidity'] ?? 0;
                $wind = $weather['wind']['speed'] ?? 0;
                $condition = strtolower($weather['weather'][0]['main'] ?? 'clear');

                $message = match ($condition) {
                    'rain', 'drizzle' => "🌧 Rain expected in $city! Don't forget your umbrella ☔",
                    'clear' => "☀️ It's sunny in $city! Stay hydrated 💧",
                    'clouds' => "☁️ Cloudy weather in $city — nice day for a walk!",
                    'snow' => "❄️ Snowfall alert in $city! Keep warm 🧤",
                    default => "🌦 $city: $condition, Temp $temp°C, Humidity $humidity%, Wind $wind km/h",
                };

                // Get all device tokens for this city
                $tokens = UserDetail::where('city', $city)
                    ->whereNotNull('device_token')
                    ->pluck('device_token')
                    ->toArray();

                if (count($tokens) === 0) {
                    $this->warn("No device tokens found for $city");
                    continue;
                }

                // Send notification via OneSignal
                $notificationResponse = Http::timeout(10)->withHeaders([
                    'Authorization' => 'Basic ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])->post('https://onesignal.com/api/v1/notifications', [
                    'app_id' => $appId,
                    'include_player_ids' => $tokens,
                    'headings' => ['en' => "Weather Update for $city"],
                    'contents' => ['en' => $message],
                ]);

                if ($notificationResponse->successful()) {
                    $responseData = $notificationResponse->json();
                    $recipients = $responseData['recipients'] ?? count($tokens);
                    $this->info("✅ Notification sent to $city users! (Recipients: $recipients)");
                    Log::info("Weather notification sent successfully", [
                        'city' => $city,
                        'recipients' => $recipients,
                        'tokens_count' => count($tokens),
                    ]);
                    $successCount++;
                } else {
                    $this->error("❌ Failed to send notification for $city: HTTP {$notificationResponse->status()}");
                    Log::error("OneSignal notification failed for city: $city", [
                        'status' => $notificationResponse->status(),
                        'response' => $notificationResponse->json(),
                        'tokens_count' => count($tokens),
                    ]);
                    $failureCount++;
                }
            } catch (\Exception $e) {
                $this->error("Exception processing $city: " . $e->getMessage());
                Log::error("Exception in weather notification for city: $city", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $failureCount++;
            }
        }

        $this->info("\n📊 Summary: $successCount successful, $failureCount failed");
        return $failureCount > 0 ? 1 : 0;
    }
}
