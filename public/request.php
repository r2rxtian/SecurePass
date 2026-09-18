<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/RequestService.php';
require_once dirname(__DIR__) . '/app/View.php';

$requestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$requestId || $requestId < 1) {
    http_response_code(400);
    exit('Invalid request ID.');
}

try {
    $user = securepass_require_user();
    $statement = securepass_db()->prepare(<<<'SQL'
        SELECT * FROM dbo.acdsecurepass_requests
        WHERE request_id = :request_id AND creator_bio_id = :bio_id
        SQL);
    $statement->execute([':request_id' => $requestId, ':bio_id' => $user['empcode']]);
    $request = $statement->fetch();
    if ($request === false) {
        http_response_code(404);
        exit('Request not found.');
    }

    $statement = securepass_db()->prepare(<<<'SQL'
        SELECT i.item_id, i.item_name, i.quantity, i.unit_of_measure, i.unit_price,
               i.expected_return_date, a.attachment_id, a.original_filename, a.size_bytes
        FROM dbo.acdsecurepass_items AS i
        LEFT JOIN dbo.acdsecurepass_attachments AS a ON a.item_id = i.item_id
        WHERE i.request_id = :request_id
        ORDER BY i.sort_order, a.attachment_id
        SQL);
    $statement->execute([':request_id' => $requestId]);
    $items = [];
    foreach ($statement->fetchAll() as $row) {
        $itemId = (int) $row['item_id'];
        if (!isset($items[$itemId])) {
            $items[$itemId] = $row;
            $items[$itemId]['attachments'] = [];
        }
        if ($row['attachment_id'] !== null) {
            $items[$itemId]['attachments'][] = $row;
        }
    }

    $statement = securepass_db()->prepare(<<<'SQL'
        SELECT revision, stage, decision, actor_bio_id, remarks, acted_at
        FROM dbo.acdsecurepass_approval_steps
        WHERE request_id = :request_id ORDER BY revision, step_order
        SQL);
    $statement->execute([':request_id' => $requestId]);
    $steps = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('SecurePass Revamp request detail failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('SecurePass is temporarily unavailable.');
}

$reference = trim((string) ($request['reference_number'] ?? '')) ?: 'Request #' . $requestId;
$category = securepass_categories()[$request['category']] ?? (string) $request['category'];
if ($request['category'] === 'other' && !empty($request['other_category'])) {
    $category .= ': ' . $request['other_category'];
}
$expectedReturn = null;
$attachmentCount = 0;
foreach ($items as $item) {
    $attachmentCount += count($item['attachments']);
    $value = $item['expected_return_date'];
    if ($value === null || $value === '') {
        continue;
    }
    $date = $value instanceof DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10);
    if ($expectedReturn === null || $date > $expectedReturn) {
        $expectedReturn = $date;
    }
}
$remarks = trim((string) ($request['remarks'] ?? ''));
$status = (string) $request['status'];

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="View a Secure Pass request and its approval progress.">
    <title><?= securepass_h($reference) ?> · Secure Pass</title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/workspace.css">
    <link rel="stylesheet" href="assets/detail.css">
    <script src="assets/gsap.min.js" defer></script>
    <script src="assets/workspace-motion.js" defer></script>
</head>
<body class="workspace-page detail-page">
    <?php securepass_render_workspace_start($user, 'dashboard', 'Request details', $reference); ?>
    <main class="detail-content" id="main-content">
        <section class="detail-overview" aria-labelledby="detail-title">
            <div class="detail-overview-main">
                <div>
                    <a class="detail-back" href="requests.php#request-summary"><span aria-hidden="true">←</span> Request summary</a>
                    <span class="detail-kicker">SECURE PASS REQUEST</span>
                    <h1 id="detail-title"><?= securepass_h($reference) ?></h1>
                    <p><?= securepass_h($request['company']) ?> <span aria-hidden="true">·</span> <?= securepass_h($category) ?></p>
                </div>
                <div class="detail-overview-state">
                    <span class="status-pill status-<?= securepass_h($status) ?>"><?= securepass_icon(match ($status) { 'approved' => 'check', 'rejected' => 'close', 'pending' => 'clock', default => 'file' }) ?><?= securepass_h(securepass_status_label($status)) ?></span>
                    <small>Created <?= securepass_h(securepass_display_date($request['created_at'])) ?></small>
                </div>
            </div>
            <div class="detail-overview-facts">
                <div><span>RELEASE DATE</span><strong><?= securepass_h(securepass_display_date($request['release_date'])) ?></strong></div>
                <div><span>LATEST EXPECTED RETURN</span><strong><?= $expectedReturn !== null ? securepass_h(securepass_display_date($expectedReturn)) : 'Not specified' ?></strong></div>
                <div><span>ITEMS &amp; FILES</span><strong><?= count($items) ?> <?= count($items) === 1 ? 'item' : 'items' ?> <small>· <?= $attachmentCount ?> <?= $attachmentCount === 1 ? 'file' : 'files' ?></small></strong></div>
            </div>
        </section>

        <div class="detail-columns">
            <section class="detail-panel detail-information" aria-labelledby="information-title">
                <div class="detail-panel-heading"><span class="detail-panel-icon"><?= securepass_icon('file') ?></span><div><span class="detail-kicker">OVERVIEW</span><h2 id="information-title">Request information</h2></div></div>
                <dl class="detail-facts">
                    <div><dt>Company / destination</dt><dd><?= securepass_h($request['company']) ?></dd></div>
                    <div><dt>Category</dt><dd><?= securepass_h($category) ?></dd></div>
                    <div><dt>Requested by</dt><dd><?= securepass_h($request['creator_name']) ?></dd></div>
                    <div><dt>Department</dt><dd><?= securepass_h($request['department']) ?></dd></div>
                    <div><dt>Current approver</dt><dd><?= securepass_h(securepass_stage_label($request['current_stage'])) ?></dd></div>
                    <div class="detail-fact-remarks"><dt>Remarks</dt><dd><?= $remarks !== '' ? securepass_h($remarks) : 'No remarks added' ?></dd></div>
                </dl>
            </section>

            <section class="detail-panel detail-approval" aria-labelledby="approval-title">
                <div class="detail-panel-heading"><span class="detail-panel-icon"><?= securepass_icon('clock') ?></span><div><span class="detail-kicker">WORKFLOW</span><h2 id="approval-title">Approval progress</h2></div></div>
                <?php if ($steps === []): ?>
                    <p class="detail-empty-note">No approval steps have been recorded.</p>
                <?php else: ?>
                    <ol class="detail-timeline">
                        <?php foreach ($steps as $index => $step): ?>
                            <li class="detail-timeline-step detail-timeline-<?= securepass_h($step['decision']) ?>">
                                <span class="detail-timeline-marker"><?= securepass_icon($step['decision'] === 'approved' ? 'check' : ($step['decision'] === 'rejected' ? 'close' : 'clock')) ?></span>
                                <div class="detail-timeline-content">
                                    <div class="detail-timeline-head"><strong><?= securepass_h(securepass_stage_label($step['stage'])) ?></strong><span class="detail-decision detail-decision-<?= securepass_h($step['decision']) ?>"><?= securepass_h(ucfirst($step['decision'])) ?></span></div>
                                    <?php if ((int) $step['revision'] > 1): ?><small>Revision <?= (int) $step['revision'] ?></small><?php endif; ?>
                                    <?php if ($step['acted_at']): ?><small>Updated <?= securepass_h(securepass_display_date($step['acted_at'])) ?></small><?php endif; ?>
                                    <?php if ($step['remarks']): ?><p><?= securepass_h($step['remarks']) ?></p><?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        </div>

        <section class="detail-panel detail-items" aria-labelledby="items-title">
            <div class="detail-panel-heading"><span class="detail-panel-icon"><?= securepass_icon('clipboard') ?></span><div><span class="detail-kicker">CONTENTS</span><h2 id="items-title">Items &amp; attachments</h2></div><span class="detail-item-count"><?= count($items) ?> <?= count($items) === 1 ? 'item' : 'items' ?></span></div>
            <?php if ($items === []): ?>
                <p class="detail-empty-note">No items have been added to this request.</p>
            <?php else: ?>
                <div class="detail-item-list">
                    <?php foreach (array_values($items) as $index => $item): ?>
                        <article class="detail-item">
                            <span class="detail-item-index"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <div class="detail-item-body">
                                <h3><?= securepass_h($item['item_name']) ?></h3>
                                <div class="detail-item-meta"><span><b>Quantity</b> <?= securepass_h($item['quantity']) ?> <?= securepass_h($item['unit_of_measure']) ?></span><span><b>Expected return</b> <?= $item['expected_return_date'] ? securepass_h(securepass_display_date($item['expected_return_date'])) : 'Not specified' ?></span><?php if ($item['unit_price'] !== null): ?><span><b>Unit price</b> <?= securepass_h($item['unit_price']) ?></span><?php endif; ?></div>
                                <div class="detail-attachments"><span>ATTACHMENTS</span>
                                    <?php if ($item['attachments'] === []): ?><small>No files attached</small><?php else: ?>
                                        <ul><?php foreach ($item['attachments'] as $attachment): ?><li><a href="download.php?id=<?= (int) $attachment['attachment_id'] ?>"><?= securepass_icon('download') ?><span><?= securepass_h($attachment['original_filename']) ?></span></a></li><?php endforeach; ?></ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <?php securepass_render_workspace_end(); ?>
</body>
</html>
