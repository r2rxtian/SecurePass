<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/RequestService.php';
require_once dirname(__DIR__) . '/app/View.php';

function securepass_dashboard_date(mixed $value): string
{
    if (!is_string($value) || $value === '') {
        return '';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : '';
}

try {
    $user = securepass_require_user();
    $db = securepass_db();
    $rawSearch = $_GET['search'] ?? '';
    $search = is_string($rawSearch) ? mb_substr(trim($rawSearch), 0, 100) : '';
    $rawStatus = $_GET['status'] ?? 'all';
    $status = is_string($rawStatus) ? $rawStatus : 'all';
    if (!in_array($status, ['all', 'pending', 'approved', 'rejected', 'draft', 'cancelled'], true)) {
        $status = 'all';
    }
    $rawStage = $_GET['stage'] ?? 'all';
    $stage = is_string($rawStage) ? $rawStage : 'all';
    if (!in_array($stage, ['all', 'department_head', 'admin_approver', 'finance', 'executive', 'security'], true)) {
        $stage = 'all';
    }
    $startDate = securepass_dashboard_date($_GET['start_date'] ?? '');
    $endDate = securepass_dashboard_date($_GET['end_date'] ?? '');
    $perPage = (int) ($_GET['per_page'] ?? 10);
    if (!in_array($perPage, [10, 25, 50], true)) {
        $perPage = 10;
    }
    $page = max(1, min(1000, (int) ($_GET['page'] ?? 1)));

    $totalsStatement = $db->prepare(<<<'SQL'
        SELECT COUNT(*) AS total,
               COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved,
               COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected,
               COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
               COALESCE(SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END), 0) AS draft
        FROM dbo.acdsecurepass_requests WHERE creator_bio_id = ?
        SQL);
    $totalsStatement->execute([$user['empcode']]);
    $totals = $totalsStatement->fetch();

    $where = 'WHERE r.creator_bio_id = ?';
    $parameters = [$user['empcode']];
    if ($status !== 'all') {
        $where .= ' AND r.status = ?';
        $parameters[] = $status;
    }
    if ($stage !== 'all') {
        $where .= ' AND r.current_stage = ?';
        $parameters[] = $stage;
    }
    if ($startDate !== '') {
        $where .= ' AND r.created_at >= ?';
        $parameters[] = $startDate;
    }
    if ($endDate !== '') {
        $where .= ' AND r.created_at < DATEADD(day, 1, CAST(? AS date))';
        $parameters[] = $endDate;
    }
    if ($search !== '') {
        $where .= ' AND (r.reference_number LIKE ? OR r.company LIKE ? OR r.category LIKE ? OR EXISTS (SELECT 1 FROM dbo.acdsecurepass_items AS si WHERE si.request_id = r.request_id AND si.item_name LIKE ?))';
        $like = '%' . $search . '%';
        array_push($parameters, $like, $like, $like, $like);
    }

    $countStatement = $db->prepare('SELECT COUNT(*) FROM dbo.acdsecurepass_requests AS r ' . $where);
    $countStatement->execute($parameters);
    $filteredCount = (int) $countStatement->fetchColumn();
    $totalPages = max(1, (int) ceil($filteredCount / $perPage));
    $page = min($page, $totalPages);

    $query = <<<'SQL'
        SELECT r.request_id, r.reference_number, r.company, r.category,
               r.status, r.current_stage, r.release_date, r.created_at,
               (SELECT COUNT(*) FROM dbo.acdsecurepass_items AS i WHERE i.request_id = r.request_id) AS item_count,
               (SELECT TOP (1) i.item_name FROM dbo.acdsecurepass_items AS i WHERE i.request_id = r.request_id ORDER BY i.sort_order) AS first_item,
               (SELECT TOP (1) i.quantity FROM dbo.acdsecurepass_items AS i WHERE i.request_id = r.request_id ORDER BY i.sort_order) AS first_quantity,
               (SELECT MAX(i.expected_return_date) FROM dbo.acdsecurepass_items AS i WHERE i.request_id = r.request_id) AS return_date
        FROM dbo.acdsecurepass_requests AS r
        SQL;
    $query .= ' ' . $where . ' ORDER BY r.created_at DESC, r.request_id DESC';
    if (($_GET['export'] ?? '') === 'csv') {
        $exportQuery = str_replace('SELECT r.request_id', 'SELECT TOP (1000) r.request_id', $query);
        $exportStatement = $db->prepare($exportQuery);
        $exportStatement->execute($parameters);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="securepass-requests.csv"');
        $stream = fopen('php://output', 'wb');
        fputcsv($stream, ['Reference', 'Item', 'Item count', 'Destination', 'Category', 'Release date', 'Expected return', 'Status', 'Current step', 'Created']);
        while ($row = $exportStatement->fetch()) {
            $cells = [
                $row['reference_number'], $row['first_item'], $row['item_count'], $row['company'],
                securepass_categories()[$row['category']] ?? $row['category'],
                securepass_display_date($row['release_date']), securepass_display_date($row['return_date']),
                securepass_status_label((string) $row['status']), securepass_stage_label($row['current_stage']),
                securepass_display_date($row['created_at']),
            ];
            fputcsv($stream, array_map(static function ($value): string {
                $text = (string) ($value ?? '');
                return preg_match('/^[\s]*[=+\-@]/u', $text) ? "'" . $text : $text;
            }, $cells));
        }
        fclose($stream);
        exit;
    }
    $offset = ($page - 1) * $perPage;
    $listStatement = $db->prepare($query . " OFFSET {$offset} ROWS FETCH NEXT {$perPage} ROWS ONLY");
    $listStatement->execute($parameters);
    $requests = $listStatement->fetchAll();
} catch (Throwable $exception) {
    error_log('SecurePass Revamp dashboard failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('SecurePass is temporarily unavailable.');
}

$created = $_SESSION['securepass_created_reference'] ?? null;
unset($_SESSION['securepass_created_reference']);
$filterCount = (int) ($search !== '') + (int) ($status !== 'all') + (int) ($stage !== 'all') + (int) ($startDate !== '') + (int) ($endDate !== '');
$filterParams = ['search' => $search, 'start_date' => $startDate, 'end_date' => $endDate, 'status' => $status, 'stage' => $stage, 'per_page' => $perPage];
$pageUrl = static fn (int $targetPage): string => 'requests.php?' . http_build_query($filterParams + ['page' => $targetPage]) . '#request-summary';
$metricCards = [
    ['approved', 'Approved', 'check', 'Requests approved'],
    ['rejected', 'Rejected', 'close', 'Requests not approved'],
    ['pending', 'Pending', 'clock', 'Awaiting approval'],
    ['draft', 'Draft', 'edit', 'Not yet submitted'],
];
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Track and manage your Secure Pass requests.">
    <title>Dashboard · Secure Pass</title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/workspace.css">
    <link rel="stylesheet" href="assets/dashboard.css">
</head>
<body class="workspace-page dashboard-page">
    <?php securepass_render_workspace_start($user, 'dashboard', 'Dashboard', 'Request overview'); ?>
    <main class="dashboard-main" id="main-content">
        <section class="dashboard-hero" aria-labelledby="hero-title">
            <div class="dashboard-hero-copy"><h2 id="hero-title">Every request, clearly tracked.</h2><p>Create, submit, track, and manage your Secure Pass requests in one place.</p></div>
            <img src="assets/pass-hero.svg" alt="" aria-hidden="true" class="dashboard-hero-art">
            <?php if ($user['can_create']): ?><a href="request_new.php" class="dashboard-primary-button"><?= securepass_icon('plus') ?> New request</a><?php endif; ?>
        </section>

        <?php if ($created !== null): ?><p class="dashboard-success" role="status"><?= securepass_icon('check') ?> Request <?= securepass_h($created) ?> was submitted.</p><?php endif; ?>

        <section class="dashboard-metrics" aria-label="Request status totals">
            <?php foreach ($metricCards as [$key, $label, $icon, $hint]): ?>
                <a class="dashboard-metric dashboard-metric-<?= $key ?>" href="requests.php?<?= securepass_h(http_build_query(['status' => $key])) ?>#request-summary"><span class="dashboard-metric-icon"><?= securepass_icon($icon) ?></span><span class="dashboard-metric-copy"><strong><?= (int) $totals[$key] ?></strong><span><?= securepass_h($label) ?></span><small><?= securepass_h($hint) ?></small></span><span class="dashboard-metric-watermark" aria-hidden="true"><?= securepass_icon($icon) ?></span></a>
            <?php endforeach; ?>
        </section>

        <section class="dashboard-summary" id="request-summary" aria-labelledby="summary-title">
            <div class="dashboard-summary-heading"><h2 id="summary-title">Request summary</h2><a class="dashboard-export" href="requests.php?<?= securepass_h(http_build_query($filterParams + ['export' => 'csv'])) ?>"><?= securepass_icon('download') ?> Export CSV</a></div>
            <form class="dashboard-filters" method="get" action="requests.php#request-summary">
                <label class="dashboard-search"><span class="sr-only">Search requests</span><?= securepass_icon('search') ?><input type="search" name="search" value="<?= securepass_h($search) ?>" placeholder="Search requests..."></label>
                <label><span>Start date</span><input type="date" name="start_date" value="<?= securepass_h($startDate) ?>"></label>
                <label><span>End date</span><input type="date" name="end_date" value="<?= securepass_h($endDate) ?>"></label>
                <label><span>Status</span><select name="status"><option value="all">All</option><?php foreach (['approved', 'rejected', 'pending', 'draft', 'cancelled'] as $option): ?><option value="<?= $option ?>" <?= $status === $option ? 'selected' : '' ?>><?= securepass_status_label($option) ?></option><?php endforeach; ?></select></label>
                <label><span>Current approver</span><select name="stage"><option value="all">All</option><?php foreach (['department_head', 'admin_approver', 'finance', 'executive', 'security'] as $option): ?><option value="<?= $option ?>" <?= $stage === $option ? 'selected' : '' ?>><?= securepass_stage_label($option) ?></option><?php endforeach; ?></select></label>
                <input type="hidden" name="per_page" value="<?= $perPage ?>">
                <button class="dashboard-filter-button" type="submit"><?= securepass_icon('filter') ?> Filter</button>
                <a class="dashboard-reset-button" href="requests.php#request-summary"><?= securepass_icon('reset') ?> Reset</a>
            </form>
            <div class="dashboard-table-scroll"><table class="dashboard-table"><thead><tr><th scope="col">Reference no.</th><th scope="col">Category</th><th scope="col">Item</th><th scope="col">Qty</th><th scope="col">Created</th><th scope="col">Release date</th><th scope="col">Return date</th><th scope="col">Status</th><th scope="col">Approver</th><th scope="col">Actions</th></tr></thead><tbody>
                <?php if ($requests === []): ?><tr><td class="dashboard-empty-cell" colspan="10"><div class="dashboard-empty"><img src="assets/empty-inbox.svg" alt="" aria-hidden="true"><h3><?= $filterCount ? 'No matching requests' : 'No requests yet' ?></h3><p><?= $filterCount ? 'Try different filters or reset the search.' : 'Your requests will appear here.' ?></p></div></td></tr><?php else: ?>
                    <?php foreach ($requests as $request): ?><tr><td><a class="dashboard-reference" href="request.php?id=<?= (int) $request['request_id'] ?>"><?= securepass_h($request['reference_number']) ?></a></td><td><?= securepass_h(securepass_categories()[$request['category']] ?? $request['category']) ?></td><td><strong><?= securepass_h($request['first_item'] ?: $request['company']) ?></strong><?php if ((int) $request['item_count'] > 1): ?><small>+ <?= (int) $request['item_count'] - 1 ?> more</small><?php endif; ?></td><td><?= securepass_h($request['first_quantity'] ?? '—') ?></td><td><?= securepass_h(securepass_display_date($request['created_at'])) ?></td><td><?= securepass_h(securepass_display_date($request['release_date'])) ?></td><td><?= securepass_h(securepass_display_date($request['return_date'])) ?></td><td><span class="dashboard-status dashboard-status-<?= securepass_h($request['status']) ?>"><?= securepass_icon(match ($request['status']) { 'approved' => 'check', 'rejected' => 'close', 'pending' => 'clock', default => 'edit' }) ?> <?= securepass_h(securepass_status_label((string) $request['status'])) ?></span></td><td><?= securepass_h(securepass_stage_label($request['current_stage'])) ?></td><td><a class="dashboard-detail-link" href="request.php?id=<?= (int) $request['request_id'] ?>">View details <?= securepass_icon('chevron') ?></a></td></tr><?php endforeach; ?>
                <?php endif; ?>
            </tbody></table></div>
            <div class="dashboard-table-footer"><span><?= $filteredCount ?> <?= $filteredCount === 1 ? 'request' : 'requests' ?><?php if ($filteredCount > 0): ?> · Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $filteredCount) ?><?php endif; ?></span><div class="dashboard-footer-controls"><form method="get" action="requests.php#request-summary"><input type="hidden" name="search" value="<?= securepass_h($search) ?>"><input type="hidden" name="start_date" value="<?= securepass_h($startDate) ?>"><input type="hidden" name="end_date" value="<?= securepass_h($endDate) ?>"><input type="hidden" name="status" value="<?= securepass_h($status) ?>"><input type="hidden" name="stage" value="<?= securepass_h($stage) ?>"><label>Show <select name="per_page" onchange="this.form.submit()"><?php foreach ([10, 25, 50] as $option): ?><option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?></select> entries</label><button type="submit" class="sr-only">Apply page size</button></form><nav aria-label="Request pages"><a class="<?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $page > 1 ? securepass_h($pageUrl($page - 1)) : '#' ?>" <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Previous</a><span aria-current="page"><?= $page ?></span><a class="<?= $page >= $totalPages ? 'is-disabled' : '' ?>" href="<?= $page < $totalPages ? securepass_h($pageUrl($page + 1)) : '#' ?>" <?= $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Next</a></nav></div></div>
        </section>
    </main>
    <?php securepass_render_workspace_end(); ?>
</body>
</html>
