<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

rate_limit('session-members', 240, 60);
$userId = require_user();
$sessionId = (int) ($_GET['sessionId'] ?? 0);
if ($sessionId <= 0) {
    json_error('sessionId is required');
}

$pdo = db();
ensure_session_membership($pdo, $sessionId, $userId);

const ONLINE_TIMEOUT_SECONDS = 45;

$stmt = $pdo->prepare(
    'SELECT m.user_id AS userId, m.display_name AS displayName, m.joined_at AS joinedAt
     FROM session_memberships m
     INNER JOIN session_presence p
             ON p.session_id = m.session_id
            AND p.user_id = m.user_id
     WHERE m.session_id = :session_id
       AND TIMESTAMPDIFF(SECOND, p.last_seen_at, CURRENT_TIMESTAMP) <= :timeout_seconds
     ORDER BY p.last_seen_at DESC, m.joined_at ASC, m.id ASC'
);
$stmt->execute([
    'session_id' => $sessionId,
    'timeout_seconds' => ONLINE_TIMEOUT_SECONDS,
]);

json_ok([
    'sessionId' => $sessionId,
    'members' => array_map(static function (array $row): array {
        return [
            'userId' => (int) $row['userId'],
            'displayName' => $row['displayName'],
            'joinedAt' => $row['joinedAt'],
        ];
    }, $stmt->fetchAll()),
]);
