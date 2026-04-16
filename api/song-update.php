<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('song-update', 60, 60);
$userId = require_user();
$data = json_input();

$songId = (int) ($data['songId'] ?? 0);
$name = sanitize_short_text((string) ($data['name'] ?? ''), 120);
$bpm = (float) ($data['bpm'] ?? 0);
$num = (int) ($data['timeSignatureNum'] ?? 0);
$den = (int) ($data['timeSignatureDen'] ?? 0);

if ($songId <= 0) {
    json_error('songId is required');
}
if ($name === '') {
    json_error('Song name is required');
}
validate_bpm($bpm);
validate_time_signature($num, $den);

$pdo = db();
$check = $pdo->prepare('SELECT id FROM songs WHERE id = :id');
$check->execute(['id' => $songId]);
if (!$check->fetch()) {
    json_error('Song not found', 404);
}

$stmt = $pdo->prepare(
    'UPDATE songs
     SET name = :name, bpm = :bpm, time_signature_num = :num, time_signature_den = :den
     WHERE id = :id'
);
$stmt->execute([
    'id' => $songId,
    'name' => $name,
    'bpm' => $bpm,
    'num' => $num,
    'den' => $den,
]);

json_ok(['songId' => $songId]);
