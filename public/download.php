<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Auth.php';

$attachmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$attachmentId || $attachmentId < 1) {
    http_response_code(400);
    exit('Invalid attachment ID.');
}

try {
    $user = securepass_require_user();
    $statement = securepass_db()->prepare(<<<'SQL'
        SELECT a.storage_key, a.original_filename, a.size_bytes
        FROM dbo.acdsecurepass_attachments AS a
        JOIN dbo.acdsecurepass_items AS i ON i.item_id = a.item_id
        JOIN dbo.acdsecurepass_requests AS r ON r.request_id = i.request_id
        WHERE a.attachment_id = :attachment_id AND r.creator_bio_id = :bio_id
        SQL);
    $statement->execute([':attachment_id' => $attachmentId, ':bio_id' => $user['empcode']]);
    $attachment = $statement->fetch();
    if ($attachment === false) {
        http_response_code(404);
        exit('Attachment not found.');
    }
} catch (Throwable $exception) {
    error_log('SecurePass Revamp download failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('SecurePass is temporarily unavailable.');
}

$key = strtolower((string) $attachment['storage_key']);
if (!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/', $key)) {
    http_response_code(500);
    exit('Attachment storage error.');
}
$path = dirname(__DIR__) . '/storage/uploads/' . $key;
if (!is_file($path)) {
    http_response_code(404);
    exit('Attachment not found.');
}

header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($path));
header("Content-Disposition: attachment; filename=\"attachment\"; filename*=UTF-8''" . rawurlencode((string) $attachment['original_filename']));
readfile($path);
