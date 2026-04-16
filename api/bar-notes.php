<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

rate_limit('bar-notes', 240, 60);
$userId = require_user();
$songId = (int) ($_GET['songId'] ?? 0);
if ($songId <= 0) {
    json_error('songId is required');
}

$pdo = db();
ensure_song_access($pdo, $songId);

$sharedStmt = $pdo->prepare(
    'SELECT bar_number AS barNumber, note_text AS noteText, updated_at AS updatedAt
     FROM bar_notes
     WHERE song_id = :song_id AND layer_type = "shared"
     ORDER BY bar_number ASC'
);
$sharedStmt->execute(['song_id' => $songId]);

$privateStmt = $pdo->prepare(
    'SELECT bar_number AS barNumber, note_text AS noteText, updated_at AS updatedAt
     FROM bar_notes
     WHERE song_id = :song_id AND layer_type = "private" AND owner_user_id = :user_id
     ORDER BY bar_number ASC'
);
$privateStmt->execute(['song_id' => $songId, 'user_id' => $userId]);

json_ok([
    'sharedNotes' => $sharedStmt->fetchAll(),
    'privateNotes' => $privateStmt->fetchAll(),
]);
