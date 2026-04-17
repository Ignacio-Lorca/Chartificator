<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('section-reorder', 60, 60);
$userId = require_user();
$data = json_input();

$songId = (int) ($data['songId'] ?? 0);
$sectionId = (int) ($data['sectionId'] ?? 0);
$direction = (string) ($data['direction'] ?? '');
if ($songId <= 0 || $sectionId <= 0) {
    json_error('songId and sectionId are required');
}
if (!in_array($direction, ['up', 'down'], true)) {
    json_error('Invalid direction');
}

$pdo = db();
ensure_song_access($pdo, $songId);

$sectionsStmt = $pdo->prepare(
    'SELECT id, bar_start, bar_end, sort_order
     FROM song_sections
     WHERE song_id = :song_id
     ORDER BY sort_order ASC, bar_start ASC, id ASC'
);
$sectionsStmt->execute(['song_id' => $songId]);
$sections = $sectionsStmt->fetchAll();
if (count($sections) < 2) {
    json_ok(['songId' => $songId, 'sectionId' => $sectionId]);
}

$currentIndex = -1;
foreach ($sections as $index => $section) {
    if ((int) $section['id'] === $sectionId) {
        $currentIndex = $index;
        break;
    }
}
if ($currentIndex < 0) {
    json_error('Section not found', 404);
}

$targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
if ($targetIndex < 0 || $targetIndex >= count($sections)) {
    json_ok(['songId' => $songId, 'sectionId' => $sectionId]);
}

$reordered = $sections;
$moved = $reordered[$currentIndex];
array_splice($reordered, $currentIndex, 1);
array_splice($reordered, $targetIndex, 0, [$moved]);

$baseBarStart = null;
foreach ($sections as $section) {
    $start = (int) $section['bar_start'];
    if ($baseBarStart === null || $start < $baseBarStart) {
        $baseBarStart = $start;
    }
}
$baseBarStart = $baseBarStart ?? 1;

$mapping = [];
$nextBar = $baseBarStart;
foreach ($reordered as $index => $section) {
    $oldStart = (int) $section['bar_start'];
    $oldEnd = (int) $section['bar_end'];
    $length = $oldEnd - $oldStart + 1;
    $newStart = $nextBar;
    $newEnd = $newStart + $length - 1;
    $mapping[(int) $section['id']] = [
        'oldStart' => $oldStart,
        'oldEnd' => $oldEnd,
        'newStart' => $newStart,
        'newEnd' => $newEnd,
        'sortOrder' => $index,
        'tempStart' => 1000000 + ($index * 10000),
    ];
    $nextBar = $newEnd + 1;
}

$pdo->beginTransaction();
try {
    $sectionUpdateStmt = $pdo->prepare(
        'UPDATE song_sections
         SET bar_start = :bar_start, bar_end = :bar_end, sort_order = :sort_order
         WHERE id = :id AND song_id = :song_id'
    );

    foreach ($mapping as $sectionKey => $item) {
        $sectionUpdateStmt->execute([
            'bar_start' => $item['newStart'],
            'bar_end' => $item['newEnd'],
            'sort_order' => $item['sortOrder'],
            'id' => $sectionKey,
            'song_id' => $songId,
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

json_ok(['songId' => $songId, 'sectionId' => $sectionId]);
