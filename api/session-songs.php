<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

rate_limit('session-songs', 240, 60);
$userId = require_user();
$sessionId = (int) ($_GET['sessionId'] ?? 0);
if ($sessionId <= 0) {
    json_error('sessionId is required');
}

$pdo = db();
$session = ensure_session_membership($pdo, $sessionId, $userId);

$stmt = $pdo->prepare(
    'SELECT ss.id, ss.song_id AS songId, ss.sort_order AS sortOrder, s.name, s.bpm,
            s.time_signature_num AS timeSignatureNum, s.time_signature_den AS timeSignatureDen
     FROM session_songs ss
     INNER JOIN songs s ON s.id = ss.song_id
     WHERE ss.session_id = :session_id
     ORDER BY ss.sort_order ASC, ss.id ASC'
);
$stmt->execute(['session_id' => $sessionId]);
$songs = $stmt->fetchAll();
if (!$songs && !empty($session['song_id'])) {
    $legacyStmt = $pdo->prepare(
        'SELECT 0 AS id, id AS songId, 0 AS sortOrder, name, bpm,
                time_signature_num AS timeSignatureNum, time_signature_den AS timeSignatureDen
         FROM songs WHERE id = :song_id LIMIT 1'
    );
    $legacyStmt->execute(['song_id' => (int) $session['song_id']]);
    $legacySong = $legacyStmt->fetch();
    if ($legacySong) {
        $songs = [$legacySong];
    }
}

json_ok([
    'sessionId' => $sessionId,
    'activeSongId' => isset($session['active_song_id']) ? (int) $session['active_song_id'] : null,
    'songs' => array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'songId' => (int) $row['songId'],
            'sortOrder' => (int) $row['sortOrder'],
            'name' => $row['name'],
            'bpm' => (float) $row['bpm'],
            'timeSignatureNum' => (int) $row['timeSignatureNum'],
            'timeSignatureDen' => (int) $row['timeSignatureDen'],
        ];
    }, $songs),
]);
