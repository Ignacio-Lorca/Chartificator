<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('session-song-reorder', 60, 60);
$userId = require_user();
$data = json_input();

$sessionId = (int) ($data['sessionId'] ?? 0);
$songId = (int) ($data['songId'] ?? 0);
$direction = (string) ($data['direction'] ?? '');
if ($sessionId <= 0 || $songId <= 0) {
    json_error('sessionId and songId are required');
}
if (!in_array($direction, ['up', 'down'], true)) {
    json_error('Invalid direction');
}

$pdo = db();
ensure_session_membership($pdo, $sessionId, $userId);

$stmt = $pdo->prepare(
    'SELECT id, song_id, sort_order
     FROM session_songs
     WHERE session_id = :session_id
     ORDER BY sort_order ASC, id ASC'
);
$stmt->execute(['session_id' => $sessionId]);
$songs = $stmt->fetchAll();

$currentIndex = -1;
foreach ($songs as $index => $item) {
    if ((int) $item['song_id'] === $songId) {
        $currentIndex = $index;
        break;
    }
}
if ($currentIndex < 0) {
    json_error('Song is not in this session setlist', 400);
}

$targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
if ($targetIndex < 0 || $targetIndex >= count($songs)) {
    json_ok(['sessionId' => $sessionId, 'songId' => $songId]);
}

$reordered = $songs;
$moved = $reordered[$currentIndex];
array_splice($reordered, $currentIndex, 1);
array_splice($reordered, $targetIndex, 0, [$moved]);

$pdo->beginTransaction();
try {
    $update = $pdo->prepare('UPDATE session_songs SET sort_order = :sort_order WHERE id = :id');
    foreach ($reordered as $index => $item) {
        $update->execute([
            'sort_order' => $index,
            'id' => (int) $item['id'],
        ]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

json_ok(['sessionId' => $sessionId, 'songId' => $songId]);
