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
        SELECT stage, decision, actor_bio_id, remarks, acted_at
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

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= securepass_h($request['reference_number']) ?> · Secure Pass</title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/workspace.css">
</head>
<body class="workspace-page detail-page">
    <?php securepass_render_workspace_start($user, 'dashboard', 'Request details', (string) $request['reference_number']); ?>
    <main class="content-layout" id="main-content">
        <a class="back-link" href="requests.php">← Request Summary</a>
        <p class="eyebrow">Gate pass request</p>
        <div class="page-title-row"><div><h1><?= securepass_h($request['reference_number']) ?></h1><p class="supporting"><?= securepass_h($request['company']) ?> · <?= securepass_h(securepass_categories()[$request['category']] ?? $request['category']) ?></p></div><span class="status-pill status-<?= securepass_h($request['status']) ?>"><?= securepass_h(securepass_status_label($request['status'])) ?></span></div>
        <div class="detail-grid">
            <section class="detail-panel"><h2>Request details</h2><dl class="facts"><div><dt>Creator</dt><dd><?= securepass_h($request['creator_name']) ?></dd></div><div><dt>Department</dt><dd><?= securepass_h($request['department']) ?></dd></div><div><dt>Release date</dt><dd><?= securepass_h($request['release_date']) ?></dd></div><div><dt>Current step</dt><dd><?= securepass_h(str_replace('_', ' ', $request['current_stage'])) ?></dd></div><div><dt>Remarks</dt><dd><?= securepass_h($request['remarks'] ?: '—') ?></dd></div></dl></section>
            <section class="detail-panel"><h2>Approval progress</h2><ol class="steps"><?php foreach ($steps as $step): ?><li><strong><?= securepass_h(ucwords(str_replace('_', ' ', $step['stage']))) ?></strong><span><?= securepass_h(ucfirst($step['decision'])) ?></span><?php if ($step['remarks']): ?><p><?= securepass_h($step['remarks']) ?></p><?php endif; ?></li><?php endforeach; ?></ol></section>
        </div>
        <section class="detail-panel item-detail-panel"><h2>Items</h2>
            <?php foreach ($items as $item): ?>
                <div class="detail-item"><div><h3><?= securepass_h($item['item_name']) ?></h3><p><?= securepass_h($item['quantity']) ?> <?= securepass_h($item['unit_of_measure']) ?><?php if ($item['expected_return_date']): ?> · Return by <?= securepass_h($item['expected_return_date']) ?><?php endif; ?></p></div>
                    <ul class="attachment-list"><?php foreach ($item['attachments'] as $attachment): ?><li><a href="download.php?id=<?= (int) $attachment['attachment_id'] ?>"><?= securepass_h($attachment['original_filename']) ?></a></li><?php endforeach; ?></ul>
                </div>
            <?php endforeach; ?>
        </section>
    </main>
    <?php securepass_render_workspace_end(); ?>
</body>
</html>
