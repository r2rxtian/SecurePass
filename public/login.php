<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/View.php';

if (isset($_SESSION['securepass_user_id']) || isset($_SESSION['securepass_test_user_id'])) {
    header('Location: requests.php', true, 302);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    securepass_verify_csrf($_POST['csrf_token'] ?? null);
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    try {
        if ($username !== '' && $password !== '' && securepass_login($username, $password)) {
            header('Location: requests.php', true, 302);
            exit;
        }
        $error = 'Invalid username or password.';
    } catch (Throwable $exception) {
        error_log('SecurePass Revamp login failed: ' . $exception->getMessage());
        $error = 'Sign in is temporarily unavailable.';
    }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sign in to La Rose Noire Secure Pass.">
    <title>Sign in · Secure Pass</title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/login.css">
    <script src="assets/gsap.min.js" defer></script>
    <script src="assets/login.js" defer></script>
    <script src="assets/workspace-motion.js" defer></script>
</head>
<body class="login-page">
    <main class="login-layout">
        <section class="login-intro" aria-label="About Secure Pass">
            <span class="login-glow" aria-hidden="true"></span>
            <div class="login-brand"><span class="brand-mark"><span>SP</span></span><span>LA ROSE NOIRE <span class="brand-divider">|</span> SECURE PASS</span></div>
            <div class="login-intro-content"><span class="login-kicker">REQUEST MANAGEMENT</span><span class="login-art" aria-hidden="true"><?= securepass_icon('clipboard') ?><span><?= securepass_icon('shield') ?></span></span><h1>Every request.<br>Clear from start<br>to finish.</h1><p>Create and track secure pass requests in one place.</p></div>
            <p class="login-environment">Secure Pass Revamp <span>·</span> Test environment</p>
        </section>
        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-panel-inner"><span class="panel-kicker">WELCOME BACK</span><h2 id="login-title">Sign in to Secure Pass</h2><p class="supporting">Use your employee account to continue.</p>
                <?php if ($error !== ''): ?><p class="form-error" role="alert"><?= securepass_h($error) ?></p><?php endif; ?>
                <form method="post" action="login.php">
                    <input type="hidden" name="csrf_token" value="<?= securepass_h(securepass_csrf_token()) ?>">
                    <label for="username">Username</label><input id="username" name="username" autocomplete="username" value="<?= securepass_h($_POST['username'] ?? '') ?>" required autofocus>
                    <label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required>
                    <button type="submit">Sign in <?= securepass_icon('arrow') ?></button>
                </form>
                <p class="login-help">Access is available to active employees. Contact your administrator if you cannot sign in.</p>
            </div>
        </section>
    </main>
</body>
</html>
