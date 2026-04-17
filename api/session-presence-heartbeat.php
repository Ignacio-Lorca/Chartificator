<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('session-presence-heartbeat', 120, 60);
$userId = require_user();
$data = json_input();

$sessionId = (int) ($data['sessionId'] ?? 0);
if ($sessionId <= 0) {
    json_error('sessionId is required');
}

$pdo = db();
ensure_session_membership($pdo, $sessionId, $userId);

$stmt = $pdo->prepare(
    'INSERT INTO session_presence (session_id, user_id, display_name, last_seen_at)
     VALUES (:session_id, :user_id, :display_name, CURRENT_TIMESTAMP)
     ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), last_seen_at = CURRENT_TIMESTAMP'
);
$stmt->execute([
    'session_id' => $sessionId,
    'user_id' => $userId,
    'display_name' => current_user_name(),
]);

json_ok([
    'sessionId' => $sessionId,
    'userId' => $userId,
    'lastSeenAt' => date('Y-m-d H:i:s'),
]);
