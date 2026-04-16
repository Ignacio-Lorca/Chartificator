<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('section-delete', 60, 60);
$userId = require_user();
$data = json_input();

$songId = (int) ($data['songId'] ?? 0);
$sectionId = (int) ($data['sectionId'] ?? 0);
if ($songId <= 0 || $sectionId <= 0) {
    json_error('songId and sectionId are required');
}

$pdo = db();
ensure_song_access($pdo, $songId);

$pdo->beginTransaction();
try {
    $deleteStmt = $pdo->prepare('DELETE FROM song_sections WHERE id = :id AND song_id = :song_id');
    $deleteStmt->execute([
        'id' => $sectionId,
        'song_id' => $songId,
    ]);
    if ($deleteStmt->rowCount() < 1) {
        $pdo->rollBack();
        json_error('Section not found', 404);
    }

    normalize_section_sort_order($pdo, $songId);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

json_ok(['songId' => $songId, 'sectionId' => $sectionId]);
