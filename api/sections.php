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
    'SELECT id, label, color_hex AS colorHex, bar_start AS barStart, bar_end AS barEnd, sort_order AS sortOrder, updated_at AS updatedAt
     FROM song_sections
     WHERE song_id = :song_id
     ORDER BY sort_order ASC, bar_start ASC'
);
$stmt->execute(['song_id' => $songId]);

json_ok(['sections' => $stmt->fetchAll()]);
