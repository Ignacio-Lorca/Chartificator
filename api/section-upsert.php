<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('section-upsert', 90, 60);
$userId = require_user();
$data = json_input();

$songId = (int) ($data['songId'] ?? 0);
$label = sanitize_short_text((string) ($data['label'] ?? ''), 40);
$colorHex = strtoupper(trim((string) ($data['colorHex'] ?? '#2B7CFF')));
$barStart = (int) ($data['barStart'] ?? 0);
$barEnd = (int) ($data['barEnd'] ?? 0);
$sortOrder = (int) ($data['sortOrder'] ?? 0);
$sectionId = isset($data['sectionId']) ? (int) $data['sectionId'] : 0;

if ($songId <= 0) {
    json_error('songId is required');
}
if ($label === '' || strlen($label) > 40) {
    json_error('Invalid section label');
}
if (!preg_match('/^#[0-9A-F]{6}$/', $colorHex)) {
    json_error('Invalid section color');
}
validate_bar_number($barStart);
if ($barEnd < $barStart) {
    json_error('barEnd must be >= barStart');
}

$pdo = db();
ensure_song_access($pdo, $songId);

if ($sectionId > 0) {
    $stmt = $pdo->prepare(
        'UPDATE song_sections
         SET label = :label, color_hex = :color_hex, bar_start = :bar_start, bar_end = :bar_end, sort_order = :sort_order
         WHERE id = :id AND song_id = :song_id'
    );
    $stmt->execute([
        'id' => $sectionId,
        'song_id' => $songId,
        'label' => $label,
        'color_hex' => $colorHex,
        'bar_start' => $barStart,
        'bar_end' => $barEnd,
        'sort_order' => $sortOrder,
    ]);
} else {
    $length = $barEnd - $barStart + 1;

    $pdo->beginTransaction();
    try {
        if (!isset($data['sortOrder'])) {
            $nextSortStmt = $pdo->prepare(
                'SELECT COALESCE(MIN(sort_order), 0) AS nextSort
                 FROM song_sections
                 WHERE song_id = :song_id
                   AND bar_start >= :bar_start'
            );
            $nextSortStmt->execute([
                'song_id' => $songId,
                'bar_start' => $barStart,
            ]);
            $nextSort = $nextSortStmt->fetch();
            if ($nextSort && $nextSort['nextSort'] !== null) {
                $sortOrder = (int) $nextSort['nextSort'];
            } else {
                $lastSortStmt = $pdo->prepare(
                    'SELECT COALESCE(MAX(sort_order), -1) + 1 AS nextSort
                     FROM song_sections
                     WHERE song_id = :song_id'
                );
                $lastSortStmt->execute(['song_id' => $songId]);
                $sortOrder = (int) (($lastSortStmt->fetch()['nextSort'] ?? 0));
            }
        }

        $laterContentStmt = $pdo->prepare(
            'SELECT 1
             FROM (
                 SELECT bar_end AS affected_bar
                 FROM song_sections
                 WHERE song_id = :song_id_sections
                 UNION ALL
                 SELECT bar_number AS affected_bar
                 FROM bar_notes
                 WHERE song_id = :song_id_notes
             ) AS song_content
             WHERE affected_bar >= :bar_start
             LIMIT 1'
        );
        $laterContentStmt->execute([
            'song_id_sections' => $songId,
            'song_id_notes' => $songId,
            'bar_start' => $barStart,
        ]);
        if ($laterContentStmt->fetch()) {
            shift_all_song_content_forward($pdo, $songId, $barStart, $length);
        }

        $sortShiftStmt = $pdo->prepare(
            'UPDATE song_sections
             SET sort_order = sort_order + 1
             WHERE song_id = :song_id
               AND sort_order >= :sort_order'
        );
        $sortShiftStmt->execute([
            'song_id' => $songId,
            'sort_order' => $sortOrder,
        ]);

        $stmt = $pdo->prepare(
            'INSERT INTO song_sections (song_id, label, color_hex, bar_start, bar_end, sort_order)
             VALUES (:song_id, :label, :color_hex, :bar_start, :bar_end, :sort_order)'
        );
        $stmt->execute([
            'song_id' => $songId,
            'label' => $label,
            'color_hex' => $colorHex,
            'bar_start' => $barStart,
            'bar_end' => $barEnd,
            'sort_order' => $sortOrder,
        ]);
        $sectionId = (int) $pdo->lastInsertId();
        normalize_section_sort_order($pdo, $songId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

json_ok(['sectionId' => $sectionId]);
