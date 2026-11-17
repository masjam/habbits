<?php

namespace App\Jobs;

use App\Models\LoginLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ResolveLoginLocationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $loginLogId;
    public string $ip;
    public ?float $lat;
    public ?float $lon;
    public ?string $source;

    /**
     * Create a new job instance.
     */
    public function __construct(int $loginLogId, string $ip, ?float $lat = null, ?float $lon = null, ?string $source = null)
    {
        $this->loginLogId = $loginLogId;
        $this->ip = $ip;
        $this->lat = $lat;
        $this->lon = $lon;
        $this->source = $source;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $log = LoginLog::find($this->loginLogId);
        if (!$log) {
            return;
        }

        $ip = $this->ip;
        $isPublicIp = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        $city = null;
        $region = null;
        $country = null;
        $isp = null;
        $lat = $this->lat;
        $lon = $this->lon;
        $source = $this->source;

        if ($isPublicIp) {
            try {
                // Use secure HTTPS endpoint (ipwho.is)
                $response = Http::timeout(5)->get("https://ipwho.is/{$ip}");
                if ($response->successful() && $response->json('success') === true) {
                    $city = $response->json('city');
                    $region = $response->json('region');
                    $country = $response->json('country');
                    $isp = $response->json('connection.isp') ?: $response->json('connection.org');

                    if (!$lat || !$lon) {
                        $lat = $response->json('latitude');
                        $lon = $response->json('longitude');
                        $source = 'ip';
                    }
                }
            } catch (\Throwable $e) {
                // Ignore external API failure; location will remain basic/fallback
            }
        } else {
            $city = ($ip === '127.0.0.1' || $ip === '::1') ? 'Lokal (Localhost)' : 'Jaringan Lokal (LAN)';
            if (!$source) {
                $source = 'local';
            }
        }

        $locationData = array_filter([
            'city' => Str::limit($city, 50, ''),
            'region' => Str::limit($region, 50, ''),
            'country' => Str::limit($country, 40, ''),
            'isp' => Str::limit($isp, 50, ''),
            'lat' => $lat ? round((float) $lat, 6) : null,
            'lon' => $lon ? round((float) $lon, 6) : null,
            'source' => $source,
        ], fn($val) => !is_null($val) && $val !== '');

        $location = !empty($locationData) ? json_encode($locationData, JSON_UNESCAPED_UNICODE) : null;
        if ($location && strlen($location) > 255) {
            $fallback = array_filter([$city, $region, $country]);
            $location = !empty($fallback) ? implode(', ', $fallback) : null;
        }

        if ($location) {
            $log->update(['location' => $location]);
        }
    }
}
