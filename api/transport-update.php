<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('transport-update', 180, 60);
$userId = require_user();
$data = json_input();

$sessionId = (int) ($data['sessionId'] ?? 0);
$action = (string) ($data['action'] ?? '');
$value = (array) ($data['value'] ?? []);
if ($sessionId <= 0) {
    json_error('sessionId is required');
}

$allowed = ['play', 'pause', 'setBpm', 'seekBar'];
if (!in_array($action, $allowed, true)) {
    json_error('Invalid action');
}

$pdo = db();
$session = ensure_session_membership($pdo, $sessionId, $userId);

$playState = (string) $session['play_state'];
$currentBpm = (float) $session['current_bpm'];
$beatsPerBar = (int) $session['beats_per_bar'];
$barOffset = (float) $session['bar_offset'];
$startedAtMs = (int) $session['started_at_ms'];
$nowMs = now_ms();

$msPerBar = (60000 / $currentBpm) * $beatsPerBar;
$currentBarNow = $barOffset;
if ($playState === 'playing' && $startedAtMs > 0) {
    $currentBarNow = $barOffset + (($nowMs - $startedAtMs) / $msPerBar);
}

if ($action === 'play') {
    $playState = 'playing';
    $barOffset = $currentBarNow;
    $startedAtMs = $nowMs;
} elseif ($action === 'pause') {
    $playState = 'paused';
    $barOffset = $currentBarNow;
    $startedAtMs = 0;
} elseif ($action === 'setBpm') {
    $newBpm = (float) ($value['bpm'] ?? 0);
    validate_bpm($newBpm);
    $currentBpm = $newBpm;
    if ($playState === 'playing') {
        $barOffset = $currentBarNow;
        $startedAtMs = $nowMs;
    }
} elseif ($action === 'seekBar') {
    $target = (float) ($value['bar'] ?? 0);
    if ($target < 1) {
        json_error('Invalid bar');
    }
    $barOffset = $target;
    if ($playState === 'playing') {
        $startedAtMs = $nowMs;
    }
}

$stmt = $pdo->prepare(
    'UPDATE sessions
     SET play_state = :play_state,
         current_bpm = :current_bpm,
         bar_offset = :bar_offset,
         started_at_ms = :started_at_ms,
         revision = revision + 1
     WHERE id = :id'
);
$stmt->execute([
    'id' => $sessionId,
    'play_state' => $playState,
    'current_bpm' => $currentBpm,
    'bar_offset' => $barOffset,
    'started_at_ms' => $startedAtMs,
]);

$fetch = $pdo->prepare('SELECT * FROM sessions WHERE id = :id LIMIT 1');
$fetch->execute(['id' => $sessionId]);
$updated = $fetch->fetch();

json_ok(transport_payload($updated ?: $session));
