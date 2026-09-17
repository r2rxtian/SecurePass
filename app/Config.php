<?php
declare(strict_types=1);

/** @return array{db_host:string,db_name:string,db_user:string,db_password:string} */
function securepass_validate_config(array $config): array
{
    $host = trim((string) ($config['db_host'] ?? ''));
    if ($host !== '10.2.0.167') {
        throw new RuntimeException('SecurePass Revamp only permits the designated test database server.');
    }

    $database = trim((string) ($config['db_name'] ?? ''));
    if ($database !== 'LRNPH_OJT') {
        throw new RuntimeException('SecurePass Revamp only permits the designated test database.');
    }

    $user = trim((string) ($config['db_user'] ?? ''));
    $password = (string) ($config['db_password'] ?? '');
    if ($user === '' || $password === '') {
        throw new RuntimeException('Test database credentials are required.');
    }

    return [
        'db_host' => $host,
        'db_name' => $database,
        'db_user' => $user,
        'db_password' => $password,
    ];
}

/** @return array{db_host:string,db_name:string,db_user:string,db_password:string} */
function securepass_config(): array
{
    $path = dirname(__DIR__) . '/config/local.php';
    if (!is_file($path)) {
        throw new RuntimeException('Create config/local.php from config/local.example.php.');
    }

    $config = require $path;
    if (!is_array($config)) {
        throw new RuntimeException('SecurePass local configuration must return an array.');
    }

    return securepass_validate_config($config);
}
