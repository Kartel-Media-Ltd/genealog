<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Fabryka połączenia Redis — opcjonalna, sterowna przez .env.local.
 * Wspiera TCP (REDIS_HOST + REDIS_PORT) i Unix socket (REDIS_SOCKET).
 * Zwraca null gdy Redis niezdefiniowany lub niedostępny (graceful degradation).
 *
 * Konfiguracja w .env.local:
 *   REDIS_HOST=127.0.0.1   + REDIS_PORT=6379   (TCP)
 *   REDIS_SOCKET=/var/run/redis/redis.sock       (Unix socket)
 *
 * Jeśli żadna zmienna nie jest zdefiniowana → connect() zwraca null,
 * a Discovery działa w trybie synchronicznym (fallback).
 */
final class RedisService
{
    private static ?\Redis $instance = null;

    /**
     * Zwraca połączone \Redis lub null gdy:
     *   - ext-redis nie jest zainstalowane
     *   - żadna zmienna REDIS_HOST / REDIS_SOCKET nie jest zdefiniowana
     *   - połączenie się nie powiodło (np. Redis niedostępny)
     */
    public static function connect(): ?\Redis
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (!extension_loaded('redis')) {
            return null;
        }

        $socket = (defined('REDIS_SOCKET') && REDIS_SOCKET !== null) ? REDIS_SOCKET : null;
        $host   = (defined('REDIS_HOST')   && REDIS_HOST   !== null) ? REDIS_HOST   : null;

        if ($socket === null && $host === null) {
            // Nie skonfigurowano — fallback do zachowania synchronicznego
            return null;
        }

        try {
            $r = new \Redis();
            if ($socket !== null) {
                $r->connect($socket);
            } else {
                $port = defined('REDIS_PORT') ? (int) REDIS_PORT : 6379;
                $r->connect($host, $port, 2.0); // 2s timeout połączenia
            }
            self::$instance = $r;
            return $r;
        } catch (\RedisException $e) {
            error_log('[RedisService] Nie można połączyć: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Reset singletona — używane w testach PHPUnit żeby wymusić nowe połączenie.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
