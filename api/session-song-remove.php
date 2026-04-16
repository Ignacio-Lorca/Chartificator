<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('session-song-remove', 60, 60);
$userId = require_user();
$data = json_input();

$sessionId = (int) ($data['sessionId'] ?? 0);
$songId = (int) ($data['songId'] ?? 0);
if ($sessionId <= 0 || $songId <= 0) {
    json_error('sessionId and songId are required');
}

$pdo = db();
$session = ensure_session_membership($pdo, $sessionId, $userId);

$songsStmt = $pdo->prepare(
    'SELECT id, song_id AS songId, sort_order AS sortOrder
     FROM session_songs
     WHERE session_id = :session_id
     ORDER BY sort_order ASC, id ASC'
);
$songsStmt->execute(['session_id' => $sessionId]);
$songs = $songsStmt->fetchAll();

$removedIndex = -1;
foreach ($songs as $index => $item) {
    if ((int) $item['songId'] === $songId) {
        $removedIndex = $index;
        break;
    }
}
if ($removedIndex < 0) {
    json_error('Song is not in this session setlist', 400);
}

$pdo->beginTransaction();
try {
    $deleteStmt = $pdo->prepare('DELETE FROM session_songs WHERE session_id = :session_id AND song_id = :song_id');
    $deleteStmt->execute([
        'session_id' => $sessionId,
        'song_id' => $songId,
    ]);

    normalize_session_song_sort_order($pdo, $sessionId);

    $remainingStmt = $pdo->prepare(
        'SELECT ss.song_id AS songId, s.bpm, s.time_signature_num AS timeSignatureNum
         FROM session_songs ss
         INNER JOIN songs s ON s.id = ss.song_id
         WHERE ss.session_id = :session_id
         ORDER BY ss.sort_order ASC, ss.id ASC'
    );
    $remainingStmt->execute(['session_id' => $sessionId]);
    $remaining = $remainingStmt->fetchAll();

    $wasActive = (int) ($session['active_song_id'] ?? 0) === $songId;
    if (!$remaining) {
        $updateSession = $pdo->prepare(
            'UPDATE sessions
             SET active_song_id = NULL,
                 song_id = NULL,
                 play_state = "paused",
                 bar_offset = 1,
                 started_at_ms = 0,
                 revision = revision + 1
             WHERE id = :id'
        );
        $updateSession->execute(['id' => $sessionId]);
    } elseif ($wasActive) {
        $nextIndex = min($removedIndex, count($remaining) - 1);
        $nextSong = $remaining[$nextIndex];
        $updateSession = $pdo->prepare(
            'UPDATE sessions
             SET active_song_id = :song_id,
                 song_id = :song_id,
                 current_bpm = :current_bpm,
                 beats_per_bar = :beats_per_bar,
                 play_state = "paused",
                 bar_offset = 1,
                 started_at_ms = 0,
                 revision = revision + 1
             WHERE id = :id'
        );
        $updateSession->execute([
            'song_id' => (int) $nextSong['songId'],
            'current_bpm' => (float) $nextSong['bpm'],
            'beats_per_bar' => (int) $nextSong['timeSignatureNum'],
            'id' => $sessionId,
        ]);
    } else {
        $sessionSongId = $session['song_id'] !== null ? (int) $session['song_id'] : null;
        $stillExists = false;
        foreach ($remaining as $item) {
            if ((int) $item['songId'] === $sessionSongId) {
                $stillExists = true;
                break;
            }
        }
        if (!$stillExists) {
            $fallbackSong = $remaining[0];
            $updateSession = $pdo->prepare(
                'UPDATE sessions
                 SET song_id = :song_id
                 WHERE id = :id'
            );
            $updateSession->execute([
                'song_id' => (int) $fallbackSong['songId'],
                'id' => $sessionId,
            ]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

json_ok(['sessionId' => $sessionId, 'songId' => $songId]);
