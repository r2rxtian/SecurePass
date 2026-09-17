<?php
declare(strict_types=1);

require_once __DIR__ . '/Config.php';

function securepass_db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $config = securepass_config();
    $dsn = sprintf(
        'sqlsrv:Server=%s;Database=%s;Encrypt=1;TrustServerCertificate=1',
        $config['db_host'],
        $config['db_name']
    );

    $connection = new PDO($dsn, $config['db_user'], $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $connection;
}
