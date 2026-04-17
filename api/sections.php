<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

rate_limit('sections', 240, 60);
$userId = require_user();
$songId = (int) ($_GET['songId'] ?? 0);
if ($songId <= 0) {
    json_error('songId is required');
}

$pdo = db();
ensure_song_access($pdo, $songId);

$stmt = $pdo->prepare(
    'SELECT ss.id, ss.label, ss.color_hex AS colorHex, ss.shared_text AS sharedText,
            ss.bar_start AS barStart, ss.bar_end AS barEnd, ss.sort_order AS sortOrder, ss.updated_at AS updatedAt,
            COALESCE(spn.note_text, "") AS privateText
     FROM song_sections ss
     LEFT JOIN song_section_private_notes spn
            ON spn.section_id = ss.id AND spn.owner_user_id = :owner_user_id
     WHERE song_id = :song_id
     ORDER BY sort_order ASC, bar_start ASC'
);
$stmt->execute(['song_id' => $songId, 'owner_user_id' => $userId]);

json_ok(['sections' => $stmt->fetchAll()]);
