<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('session-delete', 30, 60);
$userId = require_user();
$data = json_input();

$sessionId = (int) ($data['sessionId'] ?? 0);
if ($sessionId <= 0) {
    json_error('sessionId is required');
}

$pdo = db();
$sessionStmt = $pdo->prepare('SELECT id FROM sessions WHERE id = :id LIMIT 1');
$sessionStmt->execute(['id' => $sessionId]);
if (!$sessionStmt->fetch()) {
    json_error('Rehearsal not found', 404);
}

$pdo->beginTransaction();
try {
    $deleteMembers = $pdo->prepare('DELETE FROM session_memberships WHERE session_id = :session_id');
    $deleteMembers->execute(['session_id' => $sessionId]);

    $deleteSongs = $pdo->prepare('DELETE FROM session_songs WHERE session_id = :session_id');
    $deleteSongs->execute(['session_id' => $sessionId]);

    $deleteSession = $pdo->prepare('DELETE FROM sessions WHERE id = :id');
    $deleteSession->execute(['id' => $sessionId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

json_ok(['sessionId' => $sessionId]);
