<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('auth-login', 20, 60);
$data = json_input();

$displayName = sanitize_display_name((string) ($data['displayName'] ?? ''));
if ($displayName === '') {
    json_error('Display name is required');
}
if (strlen($displayName) > 64) {
    json_error('Display name too long');
}
$displayName = require_configured_user_name($displayName);

$pdo = db();
$findStmt = $pdo->prepare(
    'SELECT id, display_name
     FROM users
     WHERE display_name = :display_name
     ORDER BY id ASC
     LIMIT 1'
);
$findStmt->execute(['display_name' => $displayName]);
$existingUser = $findStmt->fetch();

if ($existingUser) {
    $userId = (int) $existingUser['id'];
} else {
    $stmt = $pdo->prepare('INSERT INTO users (display_name) VALUES (:display_name)');
    $stmt->execute(['display_name' => $displayName]);
    $userId = (int) $pdo->lastInsertId();
}

$_SESSION['user_id'] = $userId;
$_SESSION['display_name'] = $displayName;

json_ok([
    'userId' => $userId,
    'displayName' => $displayName,
]);
