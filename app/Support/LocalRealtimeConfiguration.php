<?php

namespace App\Support;

final class LocalRealtimeConfiguration
{
    /** @return array{id: string, key: string, secret: string, host: string, port: int, scheme: string, origins: list<string>}|null */
    public static function read(string $path): ?array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }
        $contents = file_get_contents($path);
        $data = $contents === false ? null : json_decode($contents, true);
        if (! is_array($data)
            || ! is_string($data['id'] ?? null) || ! preg_match('/\A[a-f0-9]{32}\z/', $data['id'])
            || ! is_string($data['key'] ?? null) || ! preg_match('/\A[a-f0-9]{32}\z/', $data['key'])
            || ! is_string($data['secret'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $data['secret'])
            || ! is_int($data['port'] ?? null) || $data['port'] < 1024 || $data['port'] > 65535) {
            return null;
        }

        return ['id' => $data['id'], 'key' => $data['key'], 'secret' => $data['secret'],
            'host' => '127.0.0.1', 'port' => $data['port'], 'scheme' => 'http',
            'origins' => ['localhost', '127.0.0.1']];
    }
}
