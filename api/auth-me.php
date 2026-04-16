<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    json_ok(['authenticated' => false]);
}

json_ok([
    'authenticated' => true,
    'userId' => (int) $userId,
    'displayName' => (string) ($_SESSION['display_name'] ?? 'Guest'),
]);
