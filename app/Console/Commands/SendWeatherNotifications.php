<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class SendWeatherNotifications extends Command
{
    protected $signature = 'weather:notify';
    protected $description = 'Send weather notifications to users by city';

    public function handle()
    {
        $this->info("🔁 Starting weather notification process...");

        // Step 1: Get all unique cities where users have device_token
        $cities = User::whereNotNull('device_token')
            ->whereNotNull('city')
            ->groupBy('city')
            ->pluck('city');

        foreach ($cities as $city) {
            $this->sendCityWeatherNotification($city);
        }

        $this->info("✅ Weather notifications sent successfully!");
    }

    private function sendCityWeatherNotification($city)
    {
        $apiKey = env('WEATHER_API_KEY');

        $response = Http::get("https://api.openweathermap.org/data/2.5/weather", [
            'q' => $city,
            'appid' => $apiKey,
            'units' => 'metric'
        ]);

        if ($response->failed()) {
            $this->error("❌ Failed to fetch weather for {$city}");
            return;
        }

        $data = $response->json();
        $weatherMain = $data['weather'][0]['main'] ?? 'Unknown';
        $temp = $data['main']['temp'] ?? '';
        $humidity = $data['main']['humidity'] ?? '';
        $wind = $data['wind']['speed'] ?? '';

        // Step 2: Create weather message
        $message = $this->createMessage($weatherMain, $temp, $humidity, $wind);

        // Step 3: Send OneSignal notification (using filters by city tag)
        $this->sendOneSignalNotification($city, $message);
    }

    private function createMessage($weather, $temp, $humidity, $wind)
    {
        $advice = match (strtolower($weather)) {
            'rain', 'drizzle', 'thunderstorm' => "☔ It's rainy! Don't forget your umbrella!",
            'clear' => "☀️ It's sunny today! Stay hydrated and wear sunglasses.",
            'clouds' => "⛅ It's cloudy, but a good day for a walk.",
            'snow' => "❄️ Snowy weather! Keep yourself warm.",
            default => "🌤️ Stay prepared for changing weather conditions."
        };

        return "{$advice}\n🌡 Temp: {$temp}°C | 💧 Humidity: {$humidity}% | 🌬 Wind: {$wind} m/s";
    }

    private function sendOneSignalNotification($city, $message)
    {
        $appId = env('ONESIGNAL_APP_ID');
        $apiKey = env('ONESIGNAL_API_KEY');

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', [
            'app_id' => $appId,
            'filters' => [
                ['field' => 'tag', 'key' => 'city', 'relation' => '=', 'value' => $city]
            ],
            'headings' => ['en' => "Weather Update - {$city}"],
            'contents' => ['en' => $message],
        ]);

        if ($response->successful()) {
            $this->info("📩 Notification sent for {$city}");
        } else {
            $this->error("⚠️ Failed to send notification for {$city}");
        }
    }
}
