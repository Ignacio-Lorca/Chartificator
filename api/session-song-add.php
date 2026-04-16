<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('session-song-add', 60, 60);
$userId = require_user();
$data = json_input();

$sessionId = (int) ($data['sessionId'] ?? 0);
$songId = (int) ($data['songId'] ?? 0);
if ($sessionId <= 0 || $songId <= 0) {
    json_error('sessionId and songId are required');
}

$pdo = db();
$session = ensure_session_membership($pdo, $sessionId, $userId);

$songCheck = $pdo->prepare('SELECT id FROM songs WHERE id = :id');
$songCheck->execute(['id' => $songId]);
if (!$songCheck->fetch()) {
    json_error('Song not found', 404);
}

$sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 AS nextSort FROM session_songs WHERE session_id = :session_id');
$sortStmt->execute(['session_id' => $sessionId]);
$nextSort = (int) (($sortStmt->fetch()['nextSort'] ?? 0));

$stmt = $pdo->prepare(
    'INSERT INTO session_songs (session_id, song_id, sort_order)
     VALUES (:session_id, :song_id, :sort_order)
     ON DUPLICATE KEY UPDATE song_id = VALUES(song_id)'
);
$stmt->execute([
    'session_id' => $sessionId,
    'song_id' => $songId,
    'sort_order' => $nextSort,
]);

if (empty($session['active_song_id'])) {
    $activeStmt = $pdo->prepare(
        'UPDATE sessions
         SET active_song_id = :song_id,
             song_id = :song_id,
             current_bpm = :current_bpm,
             beats_per_bar = :beats_per_bar,
             revision = revision + 1
         WHERE id = :id'
    );
    $songMetaStmt = $pdo->prepare('SELECT bpm, time_signature_num FROM songs WHERE id = :id LIMIT 1');
    $songMetaStmt->execute(['id' => $songId]);
    $songMeta = $songMetaStmt->fetch();
    $activeStmt->execute([
        'song_id' => $songId,
        'current_bpm' => (float) ($songMeta['bpm'] ?? 120),
        'beats_per_bar' => (int) ($songMeta['time_signature_num'] ?? 4),
        'id' => $sessionId,
    ]);
}

json_ok(['sessionId' => $sessionId, 'songId' => $songId]);
