<?php
declare(strict_types=1);

require_once __DIR__ . '/Auth.php';

function securepass_icon(string $name, string $class = ''): string
{
    $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        'file' => '<path d="M6 2h8l5 5v15H6z"/><path d="M14 2v6h5M9 13h7M9 17h7"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="18" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/>',
        'check' => '<path d="m4 12 5 5L20 6"/>',
        'close' => '<path d="M5 5 19 19M19 5 5 19"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/>',
        'edit' => '<path d="M6 3h9l4 4v14H6zM15 3v5h4M9 13h7M9 17h5"/>',
        'plus' => '<path d="M12 4v16M4 12h16"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'filter' => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
        'download' => '<path d="M12 3v12m-4-4 4 4 4-4M4 17v4h16v-4"/>',
        'chevron' => '<path d="m7 10 5 5 5-5"/>',
        'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'return' => '<path d="m9 8-5 4 5 4M4 12h10a6 6 0 0 1 0 12" transform="translate(0 -3)"/>',
        'shield' => '<path d="M12 2 20 5v6c0 5-3.2 8.7-8 11-4.8-2.3-8-6-8-11V5z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'home' => '<path d="m3 10 9-7 9 7v10H3z"/><path d="M9 20v-7h6v7"/>',
        'settings' => '<path d="M10 2h4l.5 2.1 1.8.8 1.9-1.1 2.8 2.8-1.1 1.9.8 1.8L22 11v4l-2.1.5-.8 1.8 1.1 1.9-2.8 2.8-1.9-1.1-1.8.8L13 23H9l-.5-2.1-1.8-.8-1.9 1.1L2 18.4l1.1-1.9-.8-1.8L.2 14v-4l2.1-.5.8-1.8L2 5.8 4.8 3l1.9 1.1 1.8-.8L9 1z" transform="translate(1 -1) scale(.9)"/><circle cx="12" cy="12" r="3"/>',
        'logout' => '<path d="M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5M14 7l5 5-5 5M8 12h11"/>',
        'reset' => '<path d="M20 7v5h-5M19 12a7 7 0 1 0-2 5"/>',
    ];
    $path = $paths[$name] ?? $paths['file'];
    return '<svg class="icon ' . securepass_h($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

/** @param array<string, mixed> $user */
function securepass_render_workspace_start(array $user, string $active, string $title, string $subtitle): void
{
    $name = trim((string) $user['display_name']);
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', $name) ?: [], 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    ?>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <div class="workspace-shell">
        <aside class="workspace-sidebar" aria-label="Secure Pass navigation">
            <a class="workspace-brand" href="requests.php" aria-label="La Rose Noire Secure Pass dashboard"><span class="workspace-brand-mark">SP</span><span class="workspace-brand-copy"><strong>LA ROSE NOIRE</strong><small>SECURE PASS</small></span></a>
            <nav class="workspace-nav" aria-label="Main navigation">
                <a class="<?= $active === 'dashboard' ? 'is-active' : '' ?>" href="requests.php" <?= $active === 'dashboard' ? 'aria-current="page"' : '' ?>><?= securepass_icon('home') ?><span>Dashboard</span></a>
                <?php if ($user['can_create']): ?><a class="<?= $active === 'form' ? 'is-active' : '' ?>" href="request_new.php" <?= $active === 'form' ? 'aria-current="page"' : '' ?>><?= securepass_icon('plus') ?><span>New request</span></a><?php endif; ?>
                <a class="<?= $active === 'requests' ? 'is-active' : '' ?>" href="requests.php#request-summary" <?= $active === 'requests' ? 'aria-current="page"' : '' ?>><?= securepass_icon('file') ?><span>Requests</span></a>
                <span class="workspace-nav-disabled" aria-disabled="true" title="Approval workspace coming soon"><?= securepass_icon('check') ?><span>Pending approvals</span></span>
                <span class="workspace-nav-disabled" aria-disabled="true" title="Activity logs coming soon"><?= securepass_icon('clock') ?><span>Activity logs</span></span>
                <span class="workspace-nav-disabled" aria-disabled="true" title="Settings coming soon"><?= securepass_icon('settings') ?><span>Settings</span></span>
            </nav>
            <div class="workspace-sidebar-bottom"><img src="assets/sidebar-landscape.svg" alt="" aria-hidden="true"><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= securepass_h(securepass_csrf_token()) ?>"><button type="submit"><?= securepass_icon('logout') ?><span>Sign out</span></button></form></div>
        </aside>
        <div class="workspace-content">
            <header class="workspace-topbar"><div class="workspace-page-title"><span>SECURE PASS</span><h1><?= securepass_h($title) ?></h1><p><?= securepass_h($subtitle) ?></p></div><div class="workspace-topbar-right"><time datetime="<?= securepass_h($now->format('c')) ?>"><?= securepass_h($now->format('M j, Y')) ?></time><span class="workspace-time"><?= securepass_h($now->format('g:i A')) ?></span><details class="workspace-account"><summary><span class="workspace-avatar"><?= securepass_h($initials ?: 'SP') ?></span><span class="workspace-account-copy"><strong><?= securepass_h($name) ?></strong><small><?= securepass_h($user['department']) ?></small></span><?= securepass_icon('chevron') ?></summary><div class="workspace-account-panel"><small>Signed in as</small><strong><?= securepass_h($user['username']) ?></strong><?php if ($user['is_test_user']): ?><span>Test account</span><?php endif; ?><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= securepass_h(securepass_csrf_token()) ?>"><button type="submit">Sign out</button></form></div></details></div></header>
    <?php
}

function securepass_render_workspace_end(): void
{
    echo '</div></div>';
}

function securepass_status_label(string $status): string
{
    return match ($status) {
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'pending' => 'Pending',
        'draft' => 'Draft',
        'cancelled' => 'Cancelled',
        default => ucfirst($status),
    };
}

function securepass_stage_label(?string $stage): string
{
    return match ($stage) {
        'department_head' => 'Department head',
        'admin_approver' => 'Admin approver',
        'finance' => 'Finance',
        'executive' => 'Executive',
        'security' => 'Security',
        null, '' => 'No active step',
        default => ucwords(str_replace('_', ' ', $stage)),
    };
}

function securepass_display_date(mixed $value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('M j, Y');
    }
    if (!is_string($value) || $value === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($value))->format('M j, Y');
    } catch (Throwable) {
        return $value;
    }
}

/** @param array<string, mixed> $user */
function securepass_render_header(array $user, string $active): void
{
    $name = trim((string) $user['display_name']);
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', $name) ?: [], 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    ?>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="app-header">
        <a class="app-brand" href="requests.php" aria-label="La Rose Noire Secure Pass home">
            <span class="brand-mark" aria-hidden="true"><span>SP</span></span>
            <span class="brand-wordmark">LA ROSE NOIRE <span class="brand-divider">|</span> SECURE PASS</span>
        </a>
        <nav class="main-nav" aria-label="Main navigation">
            <a class="nav-link <?= $active === 'summary' ? 'is-active' : '' ?>" href="requests.php" <?= $active === 'summary' ? 'aria-current="page"' : '' ?>><?= securepass_icon('grid') ?> <span>Request Summary</span></a>
            <?php if ($user['can_create']): ?><a class="nav-link <?= $active === 'form' ? 'is-active' : '' ?>" href="request_new.php" <?= $active === 'form' ? 'aria-current="page"' : '' ?>><?= securepass_icon('file') ?> <span>Request Form</span></a><?php endif; ?>
        </nav>
        <details class="account-menu">
            <summary><span class="avatar"><?= securepass_h($initials ?: 'SP') ?></span><span class="account-copy"><strong><?= securepass_h($name) ?></strong><small><?= securepass_h($user['department']) ?><?= $user['is_test_user'] ? ' · Test account' : '' ?></small></span><?= securepass_icon('chevron') ?></summary>
            <div class="account-popover">
                <p class="popover-label">Signed in as</p>
                <strong><?= securepass_h($user['username']) ?></strong>
                <?php if ($user['roles'] !== []): ?><p class="popover-label">Roles</p><p class="popover-roles"><?= securepass_h(implode(', ', $user['roles'])) ?></p><?php endif; ?>
                <form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= securepass_h(securepass_csrf_token()) ?>"><button type="submit">Sign out</button></form>
            </div>
        </details>
    </header>
    <?php
}
