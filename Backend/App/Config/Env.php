<?php
declare(strict_types=1);

namespace App\Config;

class Env {
    private static array $variables = [];
    private static bool $loaded = false;

    public static function load(): void {
        if (self::$loaded) return;

        $envPath = __DIR__ . '/../../.env';
        
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_starts_with($line, '#')) continue;

                if (strpos($line, '=') !== false) {
                    [$key, $val] = explode('=', $line, 2);
                    $key = trim($key);
                    $val = trim($val, " '\"");
                    
                    if (!isset($_ENV[$key])) {
                        $_ENV[$key] = $val;
                        self::$variables[$key] = $val;
                        putenv("$key=$val"); 
                    }
                }
            }
        }
        self::$loaded = true;
    }

    public static function get(string $key, $default = null) {
        self::load();
        return $_ENV[$key] ?? self::$variables[$key] ?? getenv($key) ?: $default;
    }
}