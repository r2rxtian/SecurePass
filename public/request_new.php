<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/RequestService.php';
require_once dirname(__DIR__) . '/app/View.php';

try {
    $user = securepass_require_user();
    if (!$user['can_create']) {
        http_response_code(403);
        exit('An active employee record is required to create a request.');
    }
} catch (Throwable $exception) {
    error_log('SecurePass Revamp request form failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('SecurePass is temporarily unavailable.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    securepass_verify_csrf($_POST['csrf_token'] ?? null);
    try {
        $reference = securepass_create_request($user, $_POST, $_FILES);
        $_SESSION['securepass_created_reference'] = $reference;
        header('Location: requests.php', true, 303);
        exit;
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('SecurePass Revamp request creation failed: ' . $exception->getMessage());
        $error = 'The request could not be submitted. Please try again.';
    }
}

$values = $_POST;
$postedItems = $values['items'] ?? [['name' => '', 'quantity' => '1', 'unit' => 'Piece', 'price' => '', 'return_date' => '']];
if (!is_array($postedItems) || $postedItems === []) {
    $postedItems = [['name' => '', 'quantity' => '1', 'unit' => 'Piece', 'price' => '', 'return_date' => '']];
}
$startStep = $error !== '' && !preg_match('/company|destination|category|release date|remarks/i', $error) ? 1 : 0;

function securepass_render_item(int $index, array $item): void
{
    ?>
    <fieldset class="wizard-item" data-item-index="<?= $index ?>">
        <legend class="sr-only">Item <span class="item-number"><?= $index + 1 ?></span></legend>
        <div class="item-context"><div><span class="item-eyebrow">ITEM DETAILS</span><h3>Item <span class="item-number"><?= $index + 1 ?></span></h3></div><button type="button" class="remove-item">Remove item</button></div>
        <div class="wizard-fields">
            <label class="field field-wide">Item name or description
                <input name="items[<?= $index ?>][name]" value="<?= securepass_h($item['name'] ?? '') ?>" maxlength="300" placeholder="e.g. Dell laptop" required>
            </label>
            <label class="field">Quantity
                <input name="items[<?= $index ?>][quantity]" type="number" min="0.001" step="0.001" value="<?= securepass_h($item['quantity'] ?? '1') ?>" required>
            </label>
            <label class="field">Unit
                <select name="items[<?= $index ?>][unit]" required>
                    <?php foreach (securepass_units() as $unit): ?><option value="<?= securepass_h($unit) ?>" <?= ($item['unit'] ?? 'Piece') === $unit ? 'selected' : '' ?>><?= securepass_h($unit) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="field">Unit price <span class="optional">Optional</span>
                <input name="items[<?= $index ?>][price]" type="number" min="0" step="0.0001" value="<?= securepass_h($item['price'] ?? '') ?>">
            </label>
            <label class="field">Expected return <span class="optional">If applicable</span>
                <input name="items[<?= $index ?>][return_date]" type="date" value="<?= securepass_h($item['return_date'] ?? '') ?>">
            </label>
            <label class="field field-wide upload-field">Attachments <span class="optional">1–5 files · JPG, PNG, WebP, PDF, DOCX, XLSX, PPTX · up to 10 MB each</span>
                <input name="attachments[<?= $index ?>][]" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.docx,.xlsx,.pptx" multiple required>
            </label>
        </div>
    </fieldset>
    <?php
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Create a Secure Pass request.">
    <title>Request Form · Secure Pass</title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/workspace.css">
    <link rel="stylesheet" href="assets/request-form.css">
    <script src="assets/request.js" defer></script>
    <script src="assets/gsap.min.js" defer></script>
    <script src="assets/workspace-motion.js" defer></script>
</head>
<body class="workspace-page request-form-page">
    <?php securepass_render_workspace_start($user, 'form', 'New request', 'Create a secure pass'); ?>
    <main class="form-workspace" id="main-content">
        <div class="form-workspace-heading"><div class="form-heading-title"><span class="form-heading-icon"><?= securepass_icon('clipboard') ?></span><div><span class="eyebrow">NEW SECURE PASS REQUEST</span><h1>Request Form</h1><p>Create a request in three short steps.</p></div></div><a href="requests.php" class="outline-button">← Request Summary</a></div>
        <form method="post" enctype="multipart/form-data" class="request-form request-wizard" id="request-form" data-start-step="<?= $startStep ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= securepass_h(securepass_csrf_token()) ?>">
            <aside class="wizard-sidebar" aria-label="Form progress"><span class="sidebar-kicker">SECURE PASS</span><h2>New request</h2><p>Complete each step before submitting your request.</p><ol class="wizard-steps"><li><button type="button" class="wizard-step is-active" data-step-target="0" aria-current="step"><span>01</span><strong>Request details</strong><small>Destination and schedule</small></button></li><li><button type="button" class="wizard-step" data-step-target="1"><span>02</span><strong>Items and evidence</strong><small>What is leaving</small></button></li><li><button type="button" class="wizard-step" data-step-target="2"><span>03</span><strong>Review and submit</strong><small>Check everything</small></button></li></ol><div class="sidebar-note"><?= securepass_icon('shield') ?><span>Your request begins with department head approval.</span></div></aside>
            <div class="wizard-main">
                <div class="wizard-panel is-active" data-step="0">
                    <div class="wizard-panel-heading"><div><span class="panel-kicker">STEP 01 / 03</span><h2>Request details</h2><p>Enter the destination and schedule for this pass.</p></div><span class="step-art"><?= securepass_icon('file') ?></span></div>
                    <div class="wizard-fields details-fields">
                        <label class="field">Employee<input value="<?= securepass_h($user['display_name']) ?>" disabled></label>
                        <label class="field">Department<input value="<?= securepass_h($user['department']) ?>" disabled></label>
                        <label class="field">Company or destination<input name="company" value="<?= securepass_h($values['company'] ?? '') ?>" maxlength="200" placeholder="Where will the items go?" required></label>
                        <label class="field">Category<select name="category" id="category" required><option value="">Select category</option><?php foreach (securepass_categories() as $key => $label): ?><option value="<?= securepass_h($key) ?>" <?= ($values['category'] ?? '') === $key ? 'selected' : '' ?>><?= securepass_h($label) ?></option><?php endforeach; ?></select></label>
                        <label class="field other-category-field" hidden>Other category<input name="other_category" value="<?= securepass_h($values['other_category'] ?? '') ?>" maxlength="200"></label>
                        <label class="field">Release date<input name="release_date" type="date" value="<?= securepass_h($values['release_date'] ?? '') ?>" required></label>
                        <label class="field remarks-field">Purpose or remarks <span class="optional">Optional</span><textarea name="remarks" rows="2" maxlength="5000" placeholder="Why is this pass needed?"><?= securepass_h($values['remarks'] ?? '') ?></textarea></label>
                    </div>
                </div>
                <div class="wizard-panel" data-step="1" hidden>
                    <div class="wizard-panel-heading"><div><span class="panel-kicker">STEP 02 / 03</span><h2>Items and evidence</h2><p>Add one item at a time, with its supporting file.</p></div><span class="step-art"><?= securepass_icon('clipboard') ?></span></div>
                    <div class="item-navigation"><div class="item-counter"><strong id="item-position">Item 1 of 1</strong><span>Up to 20 items per request</span></div><div class="item-navigation-actions"><button type="button" id="previous-item" class="item-nav-button" aria-label="Previous item">‹</button><button type="button" id="next-item" class="item-nav-button" aria-label="Next item">›</button><button type="button" class="add-item" id="add-item"><?= securepass_icon('plus') ?> Add item</button></div></div>
                    <div id="items"><?php foreach ($postedItems as $index => $item): ?><?php securepass_render_item((int) $index, is_array($item) ? $item : []); ?><?php endforeach; ?></div>
                </div>
                <div class="wizard-panel" data-step="2" hidden>
                    <div class="wizard-panel-heading"><div><span class="panel-kicker">STEP 03 / 03</span><h2>Review and submit</h2><p>Check the details before sending for approval.</p></div><span class="step-art"><?= securepass_icon('shield') ?></span></div>
                    <div class="review-grid"><section class="review-card"><h3>Request</h3><dl><div><dt>Employee</dt><dd><?= securepass_h($user['display_name']) ?></dd></div><div><dt>Destination</dt><dd id="review-company">—</dd></div><div><dt>Category</dt><dd id="review-category">—</dd></div><div><dt>Release date</dt><dd id="review-date">—</dd></div></dl></section><section class="review-card"><h3>Items <span id="review-count">0</span></h3><ol id="review-items"></ol><div class="review-pagination"><button type="button" id="review-prev" aria-label="Previous review page">‹</button><span id="review-page">1 of 1</span><button type="button" id="review-next" aria-label="Next review page">›</button></div></section></div>
                    <p class="review-note"><?= securepass_icon('shield') ?> After submission, this request goes to the department head approval queue.</p>
                </div>
                <?php if ($error !== ''): ?><p class="form-error wizard-error" role="alert"><?= securepass_h($error) ?><?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?> Reattach files before trying again.<?php endif; ?></p><?php endif; ?>
                <footer class="wizard-footer"><span id="step-indicator">Step 1 of 3</span><div><button type="button" id="back-step" class="wizard-back" hidden>Back</button><button type="button" id="next-step" class="wizard-next">Continue <?= securepass_icon('arrow') ?></button><button type="submit" id="submit-request" class="wizard-next" hidden>Submit request <?= securepass_icon('arrow') ?></button></div></footer>
            </div>
        </form>
    </main>
    <?php securepass_render_workspace_end(); ?>
    <template id="item-template"><?php securepass_render_item(999999, []); ?></template>
</body>
</html>
