<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CloudflareService
{
    public const CACHE_KEY = 'services:cf:';

    public static function ips()
    {
        return Cache::remember(self::CACHE_KEY . 'ips', 19800, function() {
            return self::mapIps();
        });
    }

    public static function mapIps()
    {
        $ips = self::fetchIps();
        if(!isset($ips['success'], $ips['result']) || !$ips['success']) {
            return [];
        }

        return array_merge($ips['result']['ipv4_cidrs'], $ips['result']['ipv6_cidrs']);
    }

    public static function fetchIps()
    {
        return Http::get('https://api.cloudflare.com/client/v4/ips')->json();
    }
}
