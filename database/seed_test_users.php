<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/Database.php';

$accounts = [
    ['seed.creator', 'TEST_SP_CREATOR', 'Test Creator', 'SecurePass Test Department', ['creator']],
    ['seed.department_head', 'TEST_SP_HEAD', 'Test Department Head', 'SecurePass Test Department', ['department_head']],
    ['seed.admin_approver', 'TEST_SP_ADMIN', 'Test Admin Approver', 'Administration', ['admin']],
    ['seed.finance', 'TEST_SP_FINANCE', 'Test Finance Approver', 'Finance', ['finance']],
    ['seed.executive', 'TEST_SP_EXECUTIVE', 'Test Executive Approver', 'Executive Office', ['executive']],
    ['seed.security', 'TEST_SP_SECURITY', 'Test Security Approver', 'Security', ['security']],
    ['seed.superadmin', 'TEST_SP_SUPERADMIN', 'Test SecurePass Admin', 'Administration', ['securepass_admin']],
];

$connection = securepass_db();
if ((int) $connection->query('SELECT COUNT(*) FROM dbo.acdsecurepass_test_users')->fetchColumn() !== 0) {
    fwrite(STDERR, "Test users already exist; refusing to replace credentials.\n");
    exit(1);
}

$credentialsPath = dirname(__DIR__) . '/config/test-credentials.local.txt';
if (file_exists($credentialsPath)) {
    fwrite(STDERR, "Test credentials file already exists; refusing to overwrite it.\n");
    exit(1);
}

$credentials = "SecurePass Revamp test accounts - LRNPH_OJT only\n";
$rows = [];
foreach ($accounts as [$username, $bioId, $displayName, $department, $roles]) {
    $password = bin2hex(random_bytes(12));
    $credentials .= $username . "  " . $password . "\n";
    $rows[] = [$username, password_hash($password, PASSWORD_DEFAULT), $bioId, $displayName, $department, $roles];
}

if (file_put_contents($credentialsPath, $credentials, LOCK_EX) === false) {
    throw new RuntimeException('Could not save the generated test credentials.');
}
@chmod($credentialsPath, 0600);

try {
    $connection->beginTransaction();
    $userStatement = $connection->prepare(<<<'SQL'
        INSERT INTO dbo.acdsecurepass_test_users
            (username, password_hash, bio_id, display_name, department)
        VALUES (?, ?, ?, ?, ?)
        SQL);
    $roleStatement = $connection->prepare(<<<'SQL'
        INSERT INTO dbo.acdsecurepass_user_roles (bio_id, role_name)
        VALUES (?, ?)
        SQL);

    foreach ($rows as [$username, $hash, $bioId, $displayName, $department, $roles]) {
        $userStatement->execute([$username, $hash, $bioId, $displayName, $department]);
        foreach ($roles as $role) {
            $roleStatement->execute([$bioId, $role]);
        }
    }
    $connection->commit();
    echo "Created " . count($rows) . " isolated test accounts. Credentials are in config/test-credentials.local.txt.\n";
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    @unlink($credentialsPath);
    throw $exception;
}
