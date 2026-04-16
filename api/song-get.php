<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

rate_limit('song-get', 120, 60);
$userId = require_user();
$songId = (int) ($_GET['songId'] ?? 0);
if ($songId <= 0) {
    json_error('songId is required');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM songs WHERE id = :song_id LIMIT 1');
$stmt->execute(['song_id' => $songId]);
$song = $stmt->fetch();
if (!$song) {
    json_error('Song not found', 404);
}

json_ok([
    'id' => (int) $song['id'],
    'name' => $song['name'],
    'bpm' => (float) $song['bpm'],
    'timeSignatureNum' => (int) $song['time_signature_num'],
    'timeSignatureDen' => (int) $song['time_signature_den'],
]);
