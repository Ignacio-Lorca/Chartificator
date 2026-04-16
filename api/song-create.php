<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('song-create', 30, 60);
$userId = require_user();
$data = json_input();

$name = sanitize_short_text((string) ($data['name'] ?? ''), 120);
$bpm = (float) ($data['bpm'] ?? 0);
$num = (int) ($data['timeSignatureNum'] ?? 0);
$den = (int) ($data['timeSignatureDen'] ?? 0);

if ($name === '') {
    json_error('Song name is required');
}
if (strlen($name) > 120) {
    json_error('Song name too long');
}
validate_bpm($bpm);
validate_time_signature($num, $den);

$pdo = db();
$stmt = $pdo->prepare(
    'INSERT INTO songs (name, bpm, time_signature_num, time_signature_den, created_by_user_id)
     VALUES (:name, :bpm, :num, :den, :user_id)'
);
$stmt->execute([
    'name' => $name,
    'bpm' => $bpm,
    'num' => $num,
    'den' => $den,
    'user_id' => $userId,
]);

json_ok(['songId' => (int) $pdo->lastInsertId()]);
