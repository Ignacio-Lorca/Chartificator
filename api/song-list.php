<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

rate_limit('song-list', 120, 60);
$userId = require_user();

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT s.id, s.name, s.bpm, s.time_signature_num, s.time_signature_den, s.updated_at
     FROM songs s
     ORDER BY s.updated_at DESC'
);
$stmt->execute();
$rows = $stmt->fetchAll();

$songs = array_map(static function (array $r): array {
    return [
        'id' => (int) $r['id'],
        'name' => $r['name'],
        'bpm' => (float) $r['bpm'],
        'timeSignatureNum' => (int) $r['time_signature_num'],
        'timeSignatureDen' => (int) $r['time_signature_den'],
    ];
}, $rows);

json_ok(['songs' => $songs]);
