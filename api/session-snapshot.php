<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

rate_limit('session-snapshot', 240, 60);
$userId = require_user();
$sessionId = (int) ($_GET['sessionId'] ?? 0);
if ($sessionId <= 0) {
    json_error('sessionId is required');
}

$pdo = db();
$session = ensure_session_membership($pdo, $sessionId, $userId);
$activeSongId = (int) ($session['active_song_id'] ?: $session['song_id']);
$songId = $activeSongId > 0 ? $activeSongId : null;

$setlistStmt = $pdo->prepare(
    'SELECT ss.id, ss.song_id AS songId, ss.sort_order AS sortOrder, s.name, s.bpm,
            s.time_signature_num AS timeSignatureNum, s.time_signature_den AS timeSignatureDen
     FROM session_songs ss
     INNER JOIN songs s ON s.id = ss.song_id
     WHERE ss.session_id = :session_id
     ORDER BY ss.sort_order ASC, ss.id ASC'
);
$setlistStmt->execute(['session_id' => $sessionId]);
$setlist = $setlistStmt->fetchAll();
if (!$setlist && $songId !== null) {
    $legacySongStmt = $pdo->prepare(
        'SELECT 0 AS id, id AS songId, 0 AS sortOrder, name, bpm,
                time_signature_num AS timeSignatureNum, time_signature_den AS timeSignatureDen
         FROM songs WHERE id = :song_id LIMIT 1'
    );
    $legacySongStmt->execute(['song_id' => $songId]);
    $legacySong = $legacySongStmt->fetch();
    if ($legacySong) {
        $setlist = [$legacySong];
    }
}

$activeSong = null;
if ($songId !== null) {
    $songStmt = $pdo->prepare(
        'SELECT id, name, bpm, time_signature_num AS timeSignatureNum, time_signature_den AS timeSignatureDen
         FROM songs WHERE id = :song_id LIMIT 1'
    );
    $songStmt->execute(['song_id' => $songId]);
    $activeSong = $songStmt->fetch();
}

$sections = [];
if ($songId !== null) {
    $sectionStmt = $pdo->prepare(
        'SELECT ss.id, ss.label, ss.color_hex AS colorHex, ss.shared_text AS sharedText,
                ss.bar_start AS barStart, ss.bar_end AS barEnd, ss.sort_order AS sortOrder,
                COALESCE(spn.note_text, "") AS privateText
         FROM song_sections ss
         LEFT JOIN song_section_private_notes spn
                ON spn.section_id = ss.id AND spn.owner_user_id = :owner_user_id
         WHERE song_id = :song_id
         ORDER BY sort_order ASC, bar_start ASC'
    );
    $sectionStmt->execute(['song_id' => $songId, 'owner_user_id' => $userId]);
    $sections = $sectionStmt->fetchAll();
}

json_ok([
    'transport' => transport_payload($session),
    'sections' => $sections,
    'songId' => $songId,
    'activeSong' => $activeSong ?: null,
    'setlist' => array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'songId' => (int) $row['songId'],
            'sortOrder' => (int) $row['sortOrder'],
            'name' => $row['name'],
            'bpm' => (float) $row['bpm'],
            'timeSignatureNum' => (int) $row['timeSignatureNum'],
            'timeSignatureDen' => (int) $row['timeSignatureDen'],
        ];
    }, $setlist),
    'sessionName' => $session['name'],
]);
