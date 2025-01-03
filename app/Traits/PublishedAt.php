<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

trait PublishedAt
{
    /**
     * Attempt to get the the timezone form the IP address of an incoming request
     * Cache the timezone for the IP addresss for seven days if successful
     *
     * @param string $time
     * @param string $ip
     * @return string
     */
    public function getPublishedAtTime(string $time, string $ip): string
    {
        $cacheExpiresAt = now()->addDays(7); // cache expires in 7.
        $timezone = 'UTC';

        if (Cache::has('ip_timezone/'.$ip)) {
            $timezone = Cache::get('ip_timezone/'.$ip);
        } else {
            //make api call to fetch request time zone
            $timezoneRequest = Http::get('https://ipinfo.io/'.$ip.'/json?token='.env('IP_INFO_TOKEN'));
            $responseInJson = $timezoneRequest->json();
            if (array_key_exists('timezone', $responseInJson)) {
                $timezone = $responseInJson['timezone'];
                Cache::put('ip_timezone/'.$ip, $timezone, $cacheExpiresAt);
            }
        }
        $convertedTime = Carbon::createFromFormat('Y-m-d H:i:s', $time, $timezone)
            ->setTimezone('UTC')->format('Y-m-d H:i:s');

        return $convertedTime;
    }
}
