<?php
/**
 * Minimal .env loader (no Composer). Loads once from project root.
 */
function epoin_load_env(string $rootDir): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $path = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . '.env';
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if ($key === '') {
            continue;
        }
        $value = trim($value);
        $len = strlen($value);
        if (
            $len >= 2
            && (
                ($value[0] === '"' && $value[$len - 1] === '"')
                || ($value[0] === "'" && $value[$len - 1] === "'")
            )
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) !== false) {
            continue;
        }
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

function epoin_env(string $key, ?string $default = null): ?string
{
    $v = $_ENV[$key] ?? getenv($key);
    if ($v === false || $v === '') {
        return $default;
    }
    return (string) $v;
}
