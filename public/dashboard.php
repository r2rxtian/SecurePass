<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Auth.php';
securepass_require_user();
header('Location: requests.php', true, 302);
exit;
