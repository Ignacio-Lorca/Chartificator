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

$stmt = $pdo->prepare(
    'SELECT user_id AS userId, display_name AS displayName, joined_at AS joinedAt
     FROM session_memberships
     WHERE session_id = :session_id
     ORDER BY joined_at ASC, id ASC'
);
$stmt->execute(['session_id' => $sessionId]);

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
