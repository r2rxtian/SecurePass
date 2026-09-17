<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Location: ' . (isset($_SESSION['securepass_user_id']) || isset($_SESSION['securepass_test_user_id']) ? 'dashboard.php' : 'login.php'), true, 302);
exit;
