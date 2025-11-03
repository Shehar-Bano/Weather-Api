<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SendWeatherNotifications extends Command
{
    protected $signature = 'weather:notify';

    protected $description = 'Send hourly weather notifications city-wise using OneSignal segments';

    public function handle()
    {
        $appId = env('ONESIGNAL_APP_ID');
        $apiKey = env('ONESIGNAL_API_KEY');
        $weatherKey = env('WEATHER_API_KEY');

        $cities = UserDetail::select('city')
            ->whereNotNull('city')
            ->distinct()
            ->pluck('city');

        foreach ($cities as $city) {
            // Fetch weather for city
            $weather = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                'q' => $city,
                'appid' => $weatherKey,
                'units' => 'metric',
            ])->json();

            if (! isset($weather['main'])) {
                continue;
            }

            $temp = $weather['main']['temp'] ?? 0;
            $humidity = $weather['main']['humidity'] ?? 0;
            $wind = $weather['wind']['speed'] ?? 0;
            $condition = $weather['weather'][0]['main'] ?? 'Clear';

            // Dynamic message
            $message = match (strtolower($condition)) {
                'rain', 'drizzle' => "🌧 Rain expected in $city! Don’t forget your umbrella ☔",
                'clear' => "☀️ It's sunny in $city! Stay hydrated 💧",
                'clouds' => "☁️ Cloudy weather in $city — nice day for a walk!",
                'snow' => "❄️ Snowfall alert in $city! Keep warm 🧤",
                default => "🌦 $city: $condition, Temp $temp°C, Humidity $humidity%, Wind $wind km/h",
            };

            // Send via OneSignal segment (city tag)
            Http::withHeaders([
                'Authorization' => 'Basic '.$apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://onesignal.com/api/v1/notifications', [
                'app_id' => $appId,
                'filters' => [
                    ['field' => 'tag', 'key' => 'city', 'relation' => '=', 'value' => $city],
                ],
                'headings' => ['en' => "Weather Update for $city"],
                'contents' => ['en' => $message],
            ]);

            $this->info("Notification sent to city: $city");
        }
    }
}
