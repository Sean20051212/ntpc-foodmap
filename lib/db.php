<?php
declare(strict_types=1);

require_once __DIR__ . '/response.php';

function load_app_config(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $configPath = dirname(__DIR__) . '/config.php';
    if (!is_file($configPath)) {
        error_response('Missing config.php. Copy config.php.example to config.php and update database settings.', 500);
    }

    require_once $configPath;
    $loaded = true;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    load_app_config();

    $requiredConstants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET'];
    foreach ($requiredConstants as $constant) {
        if (!defined($constant)) {
            error_response('Database config is incomplete: missing ' . $constant, 500);
        }
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        error_response('Database connection failed', 500);
    }

    return $pdo;
}

