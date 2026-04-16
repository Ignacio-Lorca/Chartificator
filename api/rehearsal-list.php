<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

rate_limit('rehearsal-list', 120, 60);
$userId = require_user();

$pdo = db();
$stmt = $pdo->query(
    'SELECT
        s.id,
        s.name,
        s.updated_at AS updatedAt,
        s.active_song_id,
        s.song_id,
        active_song.name AS activeSongName,
        active_song.bpm AS activeSongBpm,
        active_song.time_signature_num AS activeSongTimeSignatureNum,
        active_song.time_signature_den AS activeSongTimeSignatureDen,
        COUNT(DISTINCT sm.user_id) AS memberCount
     FROM sessions s
     LEFT JOIN songs active_song ON active_song.id = COALESCE(s.active_song_id, s.song_id)
     LEFT JOIN session_memberships sm ON sm.session_id = s.id
     GROUP BY
        s.id,
        s.name,
        s.updated_at,
        s.active_song_id,
        s.song_id,
        active_song.name,
        active_song.bpm,
        active_song.time_signature_num,
        active_song.time_signature_den
     ORDER BY s.updated_at DESC, s.id DESC'
);
$rows = $stmt->fetchAll();

json_ok([
    'rehearsals' => array_map(static function (array $row): array {
        return [
            'sessionId' => (int) $row['id'],
            'name' => $row['name'],
            'updatedAt' => $row['updatedAt'],
            'songId' => ($row['active_song_id'] ?: $row['song_id']) !== null
                ? (int) ($row['active_song_id'] ?: $row['song_id'])
                : null,
            'activeSongName' => $row['activeSongName'],
            'activeSongBpm' => $row['activeSongBpm'] !== null ? (float) $row['activeSongBpm'] : null,
            'activeSongTimeSignatureNum' => $row['activeSongTimeSignatureNum'] !== null ? (int) $row['activeSongTimeSignatureNum'] : null,
            'activeSongTimeSignatureDen' => $row['activeSongTimeSignatureDen'] !== null ? (int) $row['activeSongTimeSignatureDen'] : null,
            'memberCount' => (int) $row['memberCount'],
        ];
    }, $rows),
]);
