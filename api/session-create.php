<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('session-create', 20, 60);
$userId = require_user();
$data = json_input();

$songId = (int) ($data['songId'] ?? 0);
$name = sanitize_short_text((string) ($data['name'] ?? ''), 120);
if ($songId <= 0) {
    json_error('songId is required');
}
if ($name === '') {
    $name = 'Rehearsal Session';
}

$pdo = db();
$songStmt = $pdo->prepare('SELECT * FROM songs WHERE id = :id LIMIT 1');
$songStmt->execute(['id' => $songId]);
$song = $songStmt->fetch();
if (!$song) {
    json_error('Song not found', 404);
}

$inviteCode = random_code(8);
$beatsPerBar = (int) $song['time_signature_num'];

$stmt = $pdo->prepare(
    'INSERT INTO sessions (song_id, active_song_id, name, invite_code, current_bpm, beats_per_bar, play_state, bar_offset, started_at_ms)
     VALUES (:song_id, :active_song_id, :name, :invite_code, :current_bpm, :beats_per_bar, :play_state, :bar_offset, :started_at_ms)'
);
$stmt->execute([
    'song_id' => $songId,
    'active_song_id' => $songId,
    'name' => $name,
    'invite_code' => $inviteCode,
    'current_bpm' => (float) $song['bpm'],
    'beats_per_bar' => $beatsPerBar,
    'play_state' => 'paused',
    'bar_offset' => 1,
    'started_at_ms' => 0,
]);
$sessionId = (int) $pdo->lastInsertId();

$setlistStmt = $pdo->prepare(
    'INSERT INTO session_songs (session_id, song_id, sort_order)
     VALUES (:session_id, :song_id, 0)'
);
$setlistStmt->execute([
    'session_id' => $sessionId,
    'song_id' => $songId,
]);

$memberStmt = $pdo->prepare(
    'INSERT INTO session_memberships (session_id, user_id, display_name)
     VALUES (:session_id, :user_id, :display_name)'
);
$memberStmt->execute([
    'session_id' => $sessionId,
    'user_id' => $userId,
    'display_name' => current_user_name(),
]);

json_ok([
    'sessionId' => $sessionId,
]);
