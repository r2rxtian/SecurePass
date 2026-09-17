<?php
declare(strict_types=1);

session_save_path(sys_get_temp_dir());
require_once dirname(__DIR__) . '/app/RequestService.php';

if (securepass_date('2026-02-30') !== null || securepass_date('2026-02-28') !== '2026-02-28') {
    throw new RuntimeException('Date validation failed.');
}

$request = [
    'company' => 'Test destination',
    'category' => 'repair',
    'release_date' => '2026-09-18',
    'items' => [['name' => 'Test item', 'quantity' => '1', 'unit' => 'Piece']],
];

try {
    securepass_validate_request($request, []);
    throw new RuntimeException('Request without attachment was accepted.');
} catch (InvalidArgumentException $exception) {
    if (!str_contains($exception->getMessage(), 'Attach between 1 and 5')) {
        throw $exception;
    }
}

try {
    securepass_validate_request(array_replace($request, ['category' => 'invalid']), []);
    throw new RuntimeException('Invalid category was accepted.');
} catch (InvalidArgumentException $exception) {
    if (!str_contains($exception->getMessage(), 'valid category')) {
        throw $exception;
    }
}

echo "Request validation checks passed.\n";
