<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ForgeService
{
    public const CACHE_KEY = 'services:forge:';

    public static function rules()
    {
        $serverId = config('app.forge_server_id');
        $forgeKey = config('app.forge_api_key');
        if (! $serverId || ! $forgeKey) {
            return [];
        }
        $res = Http::withToken($forgeKey)->get('https://forge.laravel.com/api/v1/servers/'.$serverId.'/firewall-rules');

        if (! $res->ok()) {
            return [];
        }

        $collection = collect($res['rules'])
            ->filter(function ($rule) {
                return $rule['type'] === 'allow' && in_array($rule['port'], [443, 80]) && $rule['ip_address'];
            })
            ->map(function ($rule) {
                return [
                    'id' => $rule['id'],
                    'port' => $rule['port'],
                    'ip_address' => $rule['ip_address'],
                ];
            });

        return $collection->values()->toArray();
    }

    public static function compare()
    {
        $cf = CloudflareService::ips();
        if (! $cf || ! count($cf)) {
            return [];
        }
        $existing = self::rules();
        $existingPort80 = collect($existing)->filter(fn ($rule) => $rule['port'] == 80)->map(fn ($rule) => $rule['ip_address'])->values()->toArray();
        $existingPort443 = collect($existing)->filter(fn ($rule) => $rule['port'] == 443)->map(fn ($rule) => $rule['ip_address'])->values()->toArray();
        $diff80 = [];
        $diff443 = [];
        $exist80 = [];
        $exist443 = [];
        $canRemove = [];

        foreach ($cf as $ip) {
            $port80 = in_array($ip, $existingPort80);
            $port80 ? array_push($exist80, $ip) : array_push($diff80, $ip);
            $port443 = in_array($ip, $existingPort443);
            $port443 ? array_push($exist443, $ip) : array_push($diff443, $ip);
        }

        foreach ($existing as $rule) {
            if (strlen(trim($rule['ip_address'])) == 0) {
                continue;
            }
            if ($rule['port'] == 80 && ! in_array($rule['ip_address'], $cf)) {
                array_push($canRemove, ['id' => $rule['id'], 'ip' => $rule['ip_address']]);
            }

            if ($rule['port'] == 443 && ! in_array($rule['ip_address'], $cf)) {
                array_push($canRemove, ['id' => $rule['id'], 'ip' => $rule['ip_address']]);
            }
        }

        return ['port80' => $diff80, 'port443' => $diff443, 'canRemove' => $canRemove];
    }

    public static function handleUpdate()
    {
        $compare = self::compare();

        if (! $compare || ! isset($compare['port80'], $compare['port443'])) {
            return 0;
        }

        if (isset($compare['port80']) && count($compare['port80'])) {
            foreach ($compare['port80'] as $newRule) {
                self::addRule($newRule, 80);
            }
        }

        if (isset($compare['port443']) && count($compare['port443'])) {
            foreach ($compare['port443'] as $newRule) {
                self::addRule($newRule, 443);
            }
        }

        if (isset($compare['canRemove']) && count($compare['canRemove'])) {
            foreach ($compare['canRemove'] as $canRemove) {
                self::removeRule($canRemove['id']);
            }
        }

        return 1;
    }

    public static function forceDeleteRules($confirm = false)
    {
        if (! $confirm) {
            return 'Not valid';
        }

        $rules = self::rules();

        foreach ($rules as $rule) {
            self::removeRule($rule['id']);
        }

        return self::compare();
    }

    public static function addRule($ip, $port)
    {
        $payload = [
            'name' => 'CloudflareP'.$port,
            'ip_address' => $ip,
            'port' => $port,
            'type' => 'allow',
        ];

        $serverId = config('app.forge_server_id');
        $forgeKey = config('app.forge_api_key');
        if (! $serverId || ! $forgeKey) {
            return [];
        }

        $res = Http::withToken($forgeKey)
            ->post('https://forge.laravel.com/api/v1/servers/'.$serverId.'/firewall-rules/', $payload);

        if (! $res->ok()) {
            return [];
        }

        return $res->json();
    }

    public static function removeRule($id)
    {
        $serverId = config('app.forge_server_id');
        $forgeKey = config('app.forge_api_key');
        if (! $serverId || ! $forgeKey) {
            return [];
        }
        $res = Http::withToken($forgeKey)->delete('https://forge.laravel.com/api/v1/servers/'.$serverId.'/firewall-rules/'.$id);

        if (! $res->ok()) {
            return [];
        }

        return $res->json();
    }
}
