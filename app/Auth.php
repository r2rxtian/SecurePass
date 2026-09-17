<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** @return array<string, mixed>|null */
function securepass_current_user(): ?array
{
    $testUserId = $_SESSION['securepass_test_user_id'] ?? null;
    if (is_int($testUserId) && $testUserId > 0) {
        $statement = securepass_db()->prepare(<<<'SQL'
            SELECT test_user_id AS user_id, username, bio_id AS empcode,
                   bio_id AS active_employee_bio_id, display_name, department
            FROM dbo.acdsecurepass_test_users
            WHERE test_user_id = :user_id AND is_active = 1
            SQL);
        $statement->execute([':user_id' => $testUserId]);
        $user = $statement->fetch();
        if ($user === false) {
            unset($_SESSION['securepass_test_user_id']);
        }
    } else {
        $userId = $_SESSION['securepass_user_id'] ?? null;
        if (!is_int($userId) || $userId < 1) {
            return null;
        }
        $statement = securepass_db()->prepare(<<<'SQL'
            SELECT u.user_id, u.username, u.empcode,
                   m.BiometricsID AS active_employee_bio_id,
                   COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(m.FirstName, ' ', m.LastName))), ''),
                            NULLIF(u.full_name, ''), u.username) AS display_name,
                   COALESCE(NULLIF(m.Department, ''), u.department) AS department
            FROM dbo.lrnph_users AS u
            OUTER APPLY (
                SELECT TOP (1) BiometricsID, FirstName, LastName, Department
                FROM dbo.lrn_master_list
                WHERE BiometricsID = u.empcode AND IsActive = 1
                ORDER BY id DESC
            ) AS m
            WHERE u.user_id = :user_id AND u.status = 'active'
            SQL);
        $statement->execute([':user_id' => $userId]);
        $user = $statement->fetch();
        if ($user === false) {
            unset($_SESSION['securepass_user_id']);
        }
    }

    if ($user === false) {
        return null;
    }

    $rolesStatement = securepass_db()->prepare(<<<'SQL'
        SELECT role_name FROM dbo.acdsecurepass_user_roles
        WHERE bio_id = :bio_id AND is_active = 1
        ORDER BY role_name
        SQL);
    $rolesStatement->execute([':bio_id' => $user['empcode']]);
    $user['roles'] = $rolesStatement->fetchAll(PDO::FETCH_COLUMN);
    $user['is_test_user'] = is_int($testUserId) && $testUserId > 0;
    $user['can_create'] = $user['active_employee_bio_id'] !== null
        && trim((string) $user['department']) !== '';

    return $user;
}

function securepass_login(string $username, string $password): bool
{
    $username = trim($username);
    if (str_starts_with($username, 'seed.')) {
        $statement = securepass_db()->prepare(<<<'SQL'
            SELECT TOP (1) test_user_id, password_hash
            FROM dbo.acdsecurepass_test_users
            WHERE username = :username AND is_active = 1
            SQL);
        $statement->execute([':username' => $username]);
        $user = $statement->fetch();
        if ($user === false || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        unset($_SESSION['securepass_user_id']);
        $_SESSION['securepass_test_user_id'] = (int) $user['test_user_id'];
        return true;
    }

    $statement = securepass_db()->prepare(<<<'SQL'
        SELECT TOP (1) user_id, password
        FROM dbo.lrnph_users
        WHERE username = :username AND status = 'active'
        SQL);
    $statement->execute([':username' => $username]);
    $user = $statement->fetch();
    if ($user === false || !is_string($user['password']) || !password_verify($password, $user['password'])) {
        return false;
    }

    session_regenerate_id(true);
    unset($_SESSION['securepass_test_user_id']);
    $_SESSION['securepass_user_id'] = (int) $user['user_id'];
    return true;
}

/** @return array<string, mixed> */
function securepass_require_user(): array
{
    $user = securepass_current_user();
    if ($user === null) {
        header('Location: login.php', true, 302);
        exit;
    }
    return $user;
}

function securepass_csrf_token(): string
{
    if (!isset($_SESSION['securepass_csrf'])) {
        $_SESSION['securepass_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['securepass_csrf'];
}

function securepass_verify_csrf(?string $submitted): void
{
    if (!is_string($submitted) || !hash_equals(securepass_csrf_token(), $submitted)) {
        http_response_code(403);
        exit('Invalid request token.');
    }
}

function securepass_h(mixed $value): string
{
    return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
