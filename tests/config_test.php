<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Config.php';

$base = [
    'db_host' => '10.2.0.167',
    'db_name' => 'LRNPH_OJT',
    'db_user' => 'test_user',
    'db_password' => 'test_password',
];

if (securepass_validate_config($base)['db_host'] !== '10.2.0.167') {
    throw new RuntimeException('Expected test database host.');
}

foreach (['10.2.0.9', 'localhost', ''] as $host) {
    try {
        securepass_validate_config(array_replace($base, ['db_host' => $host]));
        throw new RuntimeException('Unexpectedly accepted database host.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Unexpectedly accepted database host.') {
            throw $exception;
        }
    }
}

try {
    securepass_validate_config(array_replace($base, ['db_name' => 'LRNPH']));
    throw new RuntimeException('Unexpectedly accepted live database name.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Unexpectedly accepted live database name.') {
        throw $exception;
    }
}

echo "Test database host and name guards passed.\n";
