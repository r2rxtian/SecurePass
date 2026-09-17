<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('SECUREPASS_REVAMP_SESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/gatepassrevamp',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
