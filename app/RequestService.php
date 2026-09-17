<?php
declare(strict_types=1);

require_once __DIR__ . '/Auth.php';

/** @return array<string, string> */
function securepass_categories(): array
{
    return [
        'employee_sale' => 'Employee sale',
        'item_for_disposal' => 'Item for disposal',
        'ob_materials' => 'OB materials',
        'product_representation' => 'Product representation',
        'pull_out_3rd_party_items' => 'Pull-out: third-party items',
        'repair' => 'Repair',
        'return_to_supplier' => 'Return to supplier',
        'scrap_for_sale' => 'Scrap for sale',
        'shipment' => 'Shipment',
        'transfer_permanently' => 'Permanent transfer',
        'transfer_temporary' => 'Temporary transfer',
        'other' => 'Other',
    ];
}

/** @return list<string> */
function securepass_units(): array
{
    return ['Piece', 'Pieces', 'Bag', 'Box', 'Carton', 'Case', 'Each', 'Kilos', 'Liters',
        'Pack', 'Pair', 'Pallet', 'Roll', 'Set', 'Sheet', 'Tube', 'Unit'];
}

function securepass_date(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
}

/** @return array<string, mixed> */
function securepass_validate_request(array $input, array $files): array
{
    $company = trim((string) ($input['company'] ?? ''));
    $category = (string) ($input['category'] ?? '');
    $other = trim((string) ($input['other_category'] ?? ''));
    $releaseDate = securepass_date($input['release_date'] ?? null);
    $remarks = trim((string) ($input['remarks'] ?? ''));
    $items = $input['items'] ?? null;

    if ($company === '' || mb_strlen($company) > 200) {
        throw new InvalidArgumentException('Enter a company or destination of up to 200 characters.');
    }
    if (!array_key_exists($category, securepass_categories())) {
        throw new InvalidArgumentException('Select a valid category.');
    }
    if ($category === 'other' && ($other === '' || mb_strlen($other) > 200)) {
        throw new InvalidArgumentException('Describe the other category.');
    }
    if ($releaseDate === null) {
        throw new InvalidArgumentException('Enter a valid release date.');
    }
    if (mb_strlen($remarks) > 5000) {
        throw new InvalidArgumentException('Remarks must be 5,000 characters or fewer.');
    }
    if (!is_array($items) || count($items) < 1 || count($items) > 20) {
        throw new InvalidArgumentException('Add between 1 and 20 items.');
    }

    $normalizedItems = [];
    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Invalid item details.');
        }
        $name = trim((string) ($item['name'] ?? ''));
        $quantity = trim((string) ($item['quantity'] ?? ''));
        $unit = (string) ($item['unit'] ?? '');
        $price = trim((string) ($item['price'] ?? ''));
        $returnDate = securepass_date($item['return_date'] ?? null);

        if ($name === '' || mb_strlen($name) > 300) {
            throw new InvalidArgumentException('Each item needs a name of up to 300 characters.');
        }
        if (!preg_match('/^\d{1,15}(?:\.\d{1,3})?$/', $quantity) || (float) $quantity <= 0) {
            throw new InvalidArgumentException('Each item needs a positive quantity.');
        }
        if (!in_array($unit, securepass_units(), true)) {
            throw new InvalidArgumentException('Select a valid unit for each item.');
        }
        if ($price !== '' && (!preg_match('/^\d{1,15}(?:\.\d{1,4})?$/', $price))) {
            throw new InvalidArgumentException('Enter a valid item price.');
        }
        if (trim((string) ($item['return_date'] ?? '')) !== '' && $returnDate === null) {
            throw new InvalidArgumentException('Enter a valid return date.');
        }
        if ($returnDate !== null && $returnDate < $releaseDate) {
            throw new InvalidArgumentException('An item return date cannot precede its release date.');
        }

        $uploads = securepass_item_uploads($files, (string) $index);
        if ($uploads === [] || count($uploads) > 5) {
            throw new InvalidArgumentException('Attach between 1 and 5 files to each item.');
        }
        foreach ($uploads as $upload) {
            securepass_validate_upload($upload);
        }

        $normalizedItems[] = [
            'name' => $name,
            'quantity' => $quantity,
            'unit' => $unit,
            'price' => $price === '' ? null : $price,
            'return_date' => $returnDate,
            'uploads' => $uploads,
        ];
    }

    return [
        'company' => $company,
        'category' => $category,
        'other_category' => $category === 'other' ? $other : null,
        'release_date' => $releaseDate,
        'remarks' => $remarks === '' ? null : $remarks,
        'items' => $normalizedItems,
    ];
}

/** @return list<array{name:string,tmp_name:string,error:int,size:int}> */
function securepass_item_uploads(array $files, string $index): array
{
    $group = $files['attachments'] ?? [];
    $names = $group['name'][$index] ?? [];
    if (!is_array($names)) {
        return [];
    }
    $uploads = [];
    foreach ($names as $fileIndex => $name) {
        if ($name === '' && ($group['error'][$index][$fileIndex] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $uploads[] = [
            'name' => (string) $name,
            'tmp_name' => (string) ($group['tmp_name'][$index][$fileIndex] ?? ''),
            'error' => (int) ($group['error'][$index][$fileIndex] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($group['size'][$index][$fileIndex] ?? 0),
        ];
    }
    return $uploads;
}

/** @param array{name:string,tmp_name:string,error:int,size:int} $upload */
function securepass_validate_upload(array $upload): void
{
    if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] < 1 || $upload['size'] > 10 * 1024 * 1024
        || !is_uploaded_file($upload['tmp_name'])) {
        throw new InvalidArgumentException('Each attachment must be a valid file of at most 10 MB.');
    }

    $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
    $allowed = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'webp' => ['image/webp'], 'pdf' => ['application/pdf'],
        'docx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'pptx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    ];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    if (!isset($allowed[$extension]) || !in_array($mime, $allowed[$extension], true)) {
        throw new InvalidArgumentException('Attach a JPG, PNG, WebP, PDF, DOCX, XLSX, or PPTX file.');
    }
}

function securepass_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
        . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

/** @param array<string, mixed> $user */
function securepass_create_request(array $user, array $input, array $files): string
{
    if (empty($user['can_create'])) {
        throw new RuntimeException('An active employee record is required.');
    }
    $data = securepass_validate_request($input, $files);
    $connection = securepass_db();
    $storedFiles = [];

    try {
        $connection->beginTransaction();
        $requestStatement = $connection->prepare(<<<'SQL'
            INSERT INTO dbo.acdsecurepass_requests
                (creator_bio_id, creator_name, department, company, category, other_category,
                 release_date, remarks, status, current_stage)
            OUTPUT INSERTED.request_id
            VALUES (:bio_id, :creator_name, :department, :company, :category, :other_category,
                    :release_date, :remarks, 'pending', 'department_head')
            SQL);
        $requestStatement->execute([
            ':bio_id' => $user['empcode'],
            ':creator_name' => $user['display_name'],
            ':department' => $user['department'],
            ':company' => $data['company'],
            ':category' => $data['category'],
            ':other_category' => $data['other_category'],
            ':release_date' => $data['release_date'],
            ':remarks' => $data['remarks'],
        ]);
        $requestId = (int) $requestStatement->fetchColumn();
        $requestStatement->closeCursor();
        if ($requestId < 1) {
            throw new RuntimeException('Request ID was not returned by SQL Server.');
        }
        $reference = 'SP' . (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y')
            . str_pad((string) $requestId, 6, '0', STR_PAD_LEFT);
        $connection->prepare('UPDATE dbo.acdsecurepass_requests SET reference_number = ? WHERE request_id = ?')
            ->execute([$reference, $requestId]);

        $itemStatement = $connection->prepare(<<<'SQL'
            INSERT INTO dbo.acdsecurepass_items
                (request_id, sort_order, item_name, quantity, unit_of_measure, unit_price, expected_return_date)
            OUTPUT INSERTED.item_id
            VALUES (:request_id, :sort_order, :item_name, :quantity, :unit, :price, :return_date)
            SQL);
        $attachmentStatement = $connection->prepare(<<<'SQL'
            INSERT INTO dbo.acdsecurepass_attachments
                (item_id, storage_key, original_filename, mime_type, size_bytes, uploaded_by_bio_id)
            VALUES (:item_id, :storage_key, :original_name, :mime_type, :size_bytes, :bio_id)
            SQL);
        $storage = dirname(__DIR__) . '/storage/uploads';
        if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) {
            throw new RuntimeException('Unable to prepare attachment storage.');
        }

        foreach ($data['items'] as $position => $item) {
            $itemStatement->execute([
                ':request_id' => $requestId,
                ':sort_order' => $position + 1,
                ':item_name' => $item['name'],
                ':quantity' => $item['quantity'],
                ':unit' => $item['unit'],
                ':price' => $item['price'],
                ':return_date' => $item['return_date'],
            ]);
            $itemId = (int) $itemStatement->fetchColumn();
            $itemStatement->closeCursor();
            if ($itemId < 1) {
                throw new RuntimeException('Item ID was not returned by SQL Server.');
            }
            foreach ($item['uploads'] as $upload) {
                $key = securepass_uuid();
                $path = $storage . '/' . $key;
                if (!move_uploaded_file($upload['tmp_name'], $path)) {
                    throw new RuntimeException('Unable to store an attachment.');
                }
                $storedFiles[] = $path;
                $attachmentStatement->execute([
                    ':item_id' => $itemId,
                    ':storage_key' => $key,
                    ':original_name' => mb_substr(basename(str_replace('\\', '/', $upload['name'])), 0, 255),
                    ':mime_type' => (new finfo(FILEINFO_MIME_TYPE))->file($path),
                    ':size_bytes' => $upload['size'],
                    ':bio_id' => $user['empcode'],
                ]);
            }
        }

        $connection->prepare(<<<'SQL'
            INSERT INTO dbo.acdsecurepass_approval_steps
                (request_id, revision, step_order, stage, assigned_role)
            VALUES (?, 1, 1, 'department_head', 'department_head')
            SQL)->execute([$requestId]);
        $connection->prepare(<<<'SQL'
            INSERT INTO dbo.acdsecurepass_audit_events
                (request_id, actor_bio_id, action_name, new_status, new_stage, details_json)
            VALUES (?, ?, 'request_created', 'pending', 'department_head', ?)
            SQL)->execute([$requestId, $user['empcode'], json_encode(['item_count' => count($data['items'])], JSON_THROW_ON_ERROR)]);

        $connection->commit();
        return $reference;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        foreach ($storedFiles as $path) {
            @unlink($path);
        }
        throw $exception;
    }
}
