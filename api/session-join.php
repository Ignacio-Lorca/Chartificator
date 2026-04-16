<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('session-join', 40, 60);
$userId = require_user();
$data = json_input();

$sessionId = (int) ($data['sessionId'] ?? 0);
$inviteCode = strtoupper(sanitize_short_text((string) ($data['inviteCode'] ?? ''), 32));

$pdo = db();
if ($sessionId > 0) {
    $stmt = $pdo->prepare('SELECT id FROM sessions WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $sessionId]);
} elseif ($inviteCode !== '') {
    $stmt = $pdo->prepare('SELECT id FROM sessions WHERE invite_code = :invite_code LIMIT 1');
    $stmt->execute(['invite_code' => $inviteCode]);
} else {
    json_error('sessionId is required');
}

$session = $stmt->fetch();
if (!$session) {
    json_error('Session not found', 404);
}
$sessionId = (int) $session['id'];

$memberStmt = $pdo->prepare(
    'INSERT INTO session_memberships (session_id, user_id, display_name)
     VALUES (:session_id, :user_id, :display_name)
     ON DUPLICATE KEY UPDATE display_name = VALUES(display_name)'
);
$memberStmt->execute([
    'session_id' => $sessionId,
    'user_id' => $userId,
    'display_name' => current_user_name(),
]);

json_ok(['sessionId' => $sessionId]);
