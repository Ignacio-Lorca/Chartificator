<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('bar-note-shared-upsert', 120, 60);
$userId = require_user();
$data = json_input();

$songId = (int) ($data['songId'] ?? 0);
$barNumber = (int) ($data['barNumber'] ?? 0);
$noteText = sanitize_note_text((string) ($data['noteText'] ?? ''));
if ($songId <= 0) {
    json_error('songId is required');
}
validate_bar_number($barNumber);
if (app_strlen($noteText) > 2000) {
    json_error('Note too long');
}

$pdo = db();
ensure_song_access($pdo, $songId);

$existingStmt = $pdo->prepare(
    'SELECT id
     FROM bar_notes
     WHERE song_id = :song_id
       AND layer_type = "shared"
       AND bar_number = :bar_number
     LIMIT 1'
);
$existingStmt->execute([
    'song_id' => $songId,
    'bar_number' => $barNumber,
]);

$pdo->beginTransaction();
try {
    if ($noteText === '') {
        $deleteStmt = $pdo->prepare(
            'DELETE FROM bar_notes
             WHERE song_id = :song_id
               AND layer_type = "shared"
               AND bar_number = :bar_number'
        );
        $deleteStmt->execute([
            'song_id' => $songId,
            'bar_number' => $barNumber,
        ]);
        $pdo->commit();
        json_ok(['songId' => $songId, 'barNumber' => $barNumber]);
    }

    if (!$existingStmt->fetch()) {
        $laterStmt = $pdo->prepare(
            'SELECT id
             FROM bar_notes
             WHERE song_id = :song_id
               AND layer_type = "shared"
               AND bar_number >= :bar_number
             LIMIT 1'
        );
        $laterStmt->execute([
            'song_id' => $songId,
            'bar_number' => $barNumber,
        ]);
        if ($laterStmt->fetch()) {
            shift_shared_notes_forward($pdo, $songId, $barNumber, 1);
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO bar_notes (song_id, bar_number, layer_type, owner_user_id, note_text)
         VALUES (:song_id, :bar_number, "shared", NULL, :note_text)
         ON DUPLICATE KEY UPDATE note_text = VALUES(note_text), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        'song_id' => $songId,
        'bar_number' => $barNumber,
        'note_text' => $noteText,
    ]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

json_ok(['songId' => $songId, 'barNumber' => $barNumber]);
