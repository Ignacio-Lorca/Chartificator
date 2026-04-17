<?php

declare(strict_types=1);

session_start();

set_security_headers();

function app_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $path = __DIR__ . '/../config.php';
    if (!file_exists($path)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Missing config.php. Copy config.sample.php to config.php']);
        exit;
    }

    $cfg = require $path;
    return $cfg;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = app_config()['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        (int) $db['port'],
        $db['name'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    if (strlen($raw) > 1024 * 64) {
        json_error('Payload too large', 413);
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function json_ok(array $data = []): void
{
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $status = 400): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function now_ms(): int
{
    return (int) round(microtime(true) * 1000);
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        json_error('Method not allowed', 405);
    }
}

function set_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: no-referrer');
}

function require_user(): int
{
    if (!isset($_SESSION['user_id'])) {
        json_error('Not authenticated', 401);
    }

    return (int) $_SESSION['user_id'];
}

function current_user_name(): string
{
    return sanitize_display_name((string) ($_SESSION['display_name'] ?? 'Guest'));
}

function configured_user_names(): array
{
    $configured = app_config()['preconfigured_users'] ?? [];
    if (!is_array($configured)) {
        return [];
    }

    $names = [];
    foreach ($configured as $item) {
        $name = sanitize_display_name((string) $item);
        if ($name === '') {
            continue;
        }
        $names[$name] = true;
    }

    return array_keys($names);
}

function require_configured_user_name(string $displayName): string
{
    $allowed = configured_user_names();
    if (!$allowed) {
        json_error('No preconfigured users available', 500);
    }
    if (!in_array($displayName, $allowed, true)) {
        json_error('Selected user is not allowed', 403);
    }

    return $displayName;
}

function sanitize_display_name(string $name): string
{
    $name = trim($name);
    $name = preg_replace('/\s+/', ' ', $name) ?? '';
    return app_substr($name, 64);
}

function sanitize_short_text(string $value, int $maxLen): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return app_substr($value, $maxLen);
}

function sanitize_note_text(string $value, int $maxLen = 2000): string
{
    $value = trim(str_replace("\r\n", "\n", $value));
    $value = str_replace("\r", "\n", $value);
    return app_substr($value, $maxLen);
}

function app_strlen(string $value): int
{
    return function_exists('mb_strlen') ? (int) mb_strlen($value, 'UTF-8') : strlen($value);
}

function app_substr(string $value, int $length): string
{
    return function_exists('mb_substr') ? (string) mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
}

function validate_bpm(float $bpm): void
{
    if ($bpm < 30 || $bpm > 300) {
        json_error('Invalid bpm');
    }
}

function validate_time_signature(int $num, int $den): void
{
    $validDen = [2, 4, 8, 16];
    if ($num < 1 || $num > 16 || !in_array($den, $validDen, true)) {
        json_error('Invalid time signature');
    }
}

function validate_bar_number(int $bar): void
{
    if ($bar < 1) {
        json_error('Invalid bar number');
    }
}

function random_code(int $length = 8): string
{
    $pool = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($pool) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $pool[random_int(0, $max)];
    }

    return $out;
}

function rate_limit(string $bucket, int $max, int $windowSec): void
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $user = (string) ($_SESSION['user_id'] ?? 'guest');
    $key = hash('sha256', $bucket . '|' . $ip . '|' . $user);
    $file = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'shared_charts_rl_' . $key . '.json';
    $now = time();

    $data = ['start' => $now, 'count' => 0];
    if (is_file($file)) {
        $raw = file_get_contents($file);
        $parsed = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($parsed) && isset($parsed['start'], $parsed['count'])) {
            $data = ['start' => (int) $parsed['start'], 'count' => (int) $parsed['count']];
        }
    }

    if ($now - $data['start'] >= $windowSec) {
        $data = ['start' => $now, 'count' => 0];
    }

    $data['count']++;
    if ($data['count'] > $max) {
        json_error('Too many requests', 429);
    }

    @file_put_contents($file, json_encode($data));
}

function ensure_session_membership(PDO $pdo, int $sessionId, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.*, m.user_id AS member_user_id
         FROM sessions s
         INNER JOIN session_memberships m ON m.session_id = s.id
         WHERE s.id = :session_id AND m.user_id = :user_id'
    );
    $stmt->execute(['session_id' => $sessionId, 'user_id' => $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        json_error('Forbidden', 403);
    }

    return $row;
}

function ensure_song_access(PDO $pdo, int $songId): array
{
    $stmt = $pdo->prepare('SELECT * FROM songs WHERE id = :song_id LIMIT 1');
    $stmt->execute(['song_id' => $songId]);
    $song = $stmt->fetch();
    if (!$song) {
        json_error('Song not found', 404);
    }

    return $song;
}

function normalize_section_sort_order(PDO $pdo, int $songId): void
{
    $stmt = $pdo->prepare('SELECT id FROM song_sections WHERE song_id = :song_id ORDER BY sort_order ASC, bar_start ASC, id ASC');
    $stmt->execute(['song_id' => $songId]);
    $rows = $stmt->fetchAll();
    $update = $pdo->prepare('UPDATE song_sections SET sort_order = :sort_order WHERE id = :id');
    foreach ($rows as $index => $row) {
        $update->execute([
            'sort_order' => $index,
            'id' => (int) $row['id'],
        ]);
    }
}

function normalize_session_song_sort_order(PDO $pdo, int $sessionId): void
{
    $stmt = $pdo->prepare('SELECT id FROM session_songs WHERE session_id = :session_id ORDER BY sort_order ASC, id ASC');
    $stmt->execute(['session_id' => $sessionId]);
    $rows = $stmt->fetchAll();
    $update = $pdo->prepare('UPDATE session_songs SET sort_order = :sort_order WHERE id = :id');
    foreach ($rows as $index => $row) {
        $update->execute([
            'sort_order' => $index,
            'id' => (int) $row['id'],
        ]);
    }
}

function shift_sections_forward(PDO $pdo, int $songId, int $fromBar, int $delta): void
{
    if ($delta <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE song_sections
         SET bar_start = CASE WHEN bar_start >= :from_bar THEN bar_start + :delta ELSE bar_start END,
             bar_end = CASE WHEN bar_end >= :from_bar THEN bar_end + :delta ELSE bar_end END
         WHERE song_id = :song_id
           AND bar_end >= :from_bar'
    );
    $stmt->execute([
        'song_id' => $songId,
        'from_bar' => $fromBar,
        'delta' => $delta,
    ]);
}

function shift_all_song_content_forward(PDO $pdo, int $songId, int $fromBar, int $delta): void
{
    shift_sections_forward($pdo, $songId, $fromBar, $delta);
}

function transport_payload(array $session): array
{
    return [
        'playState' => $session['play_state'],
        'currentBpm' => (float) $session['current_bpm'],
        'beatsPerBar' => (int) $session['beats_per_bar'],
        'barOffset' => (float) $session['bar_offset'],
        'startedAtMs' => (int) $session['started_at_ms'],
        'serverNowMs' => now_ms(),
        'revision' => (int) $session['revision'],
    ];
}
