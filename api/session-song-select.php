<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('session-song-select', 120, 60);
$userId = require_user();
$data = json_input();

$sessionId = (int) ($data['sessionId'] ?? 0);
$songId = (int) ($data['songId'] ?? 0);
if ($sessionId <= 0 || $songId <= 0) {
    json_error('sessionId and songId are required');
}

$pdo = db();
ensure_session_membership($pdo, $sessionId, $userId);

$linkStmt = $pdo->prepare('SELECT id FROM session_songs WHERE session_id = :session_id AND song_id = :song_id');
$linkStmt->execute(['session_id' => $sessionId, 'song_id' => $songId]);
if (!$linkStmt->fetch()) {
    json_error('Song is not in this session setlist', 400);
}

$songStmt = $pdo->prepare('SELECT bpm, time_signature_num FROM songs WHERE id = :id');
$songStmt->execute(['id' => $songId]);
$song = $songStmt->fetch();
if (!$song) {
    json_error('Song not found', 404);
}

$stmt = $pdo->prepare(
    'UPDATE sessions
     SET active_song_id = :song_id,
         song_id = :song_id,
         current_bpm = :current_bpm,
         beats_per_bar = :beats_per_bar,
         revision = revision + 1
     WHERE id = :id'
);
$stmt->execute([
    'id' => $sessionId,
    'song_id' => $songId,
    'current_bpm' => (float) $song['bpm'],
    'beats_per_bar' => (int) $song['time_signature_num'],
]);

json_ok(['sessionId' => $sessionId, 'songId' => $songId]);
