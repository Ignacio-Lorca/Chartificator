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
$sharedText = sanitize_note_text((string) ($data['sharedText'] ?? ''));
$privateText = sanitize_note_text((string) ($data['privateText'] ?? ''));

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
if (app_strlen($sharedText) > 2000) {
    json_error('Shared note too long');
}
if (app_strlen($privateText) > 2000) {
    json_error('Private note too long');
}

$pdo = db();
ensure_song_access($pdo, $songId);

if ($sectionId > 0) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'UPDATE song_sections
             SET label = :label, color_hex = :color_hex, shared_text = :shared_text,
                 bar_start = :bar_start, bar_end = :bar_end, sort_order = :sort_order
             WHERE id = :id AND song_id = :song_id'
        );
        $stmt->execute([
            'id' => $sectionId,
            'song_id' => $songId,
            'label' => $label,
            'color_hex' => $colorHex,
            'shared_text' => $sharedText,
            'bar_start' => $barStart,
            'bar_end' => $barEnd,
            'sort_order' => $sortOrder,
        ]);

        save_private_section_note($pdo, $sectionId, $userId, $privateText);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
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
             FROM song_sections
             WHERE song_id = :song_id
               AND bar_end >= :bar_start
             LIMIT 1'
        );
        $laterContentStmt->execute([
            'song_id' => $songId,
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
            'INSERT INTO song_sections (song_id, label, color_hex, shared_text, bar_start, bar_end, sort_order)
             VALUES (:song_id, :label, :color_hex, :shared_text, :bar_start, :bar_end, :sort_order)'
        );
        $stmt->execute([
            'song_id' => $songId,
            'label' => $label,
            'color_hex' => $colorHex,
            'shared_text' => $sharedText,
            'bar_start' => $barStart,
            'bar_end' => $barEnd,
            'sort_order' => $sortOrder,
        ]);
        $sectionId = (int) $pdo->lastInsertId();
        save_private_section_note($pdo, $sectionId, $userId, $privateText);
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

function save_private_section_note(PDO $pdo, int $sectionId, int $userId, string $privateText): void
{
    if ($privateText === '') {
        $deleteStmt = $pdo->prepare(
            'DELETE FROM song_section_private_notes
             WHERE section_id = :section_id AND owner_user_id = :owner_user_id'
        );
        $deleteStmt->execute([
            'section_id' => $sectionId,
            'owner_user_id' => $userId,
        ]);
        return;
    }

    $upsertStmt = $pdo->prepare(
        'INSERT INTO song_section_private_notes (section_id, owner_user_id, note_text)
         VALUES (:section_id, :owner_user_id, :note_text)
         ON DUPLICATE KEY UPDATE note_text = VALUES(note_text), updated_at = CURRENT_TIMESTAMP'
    );
    $upsertStmt->execute([
        'section_id' => $sectionId,
        'owner_user_id' => $userId,
        'note_text' => $privateText,
    ]);
}
