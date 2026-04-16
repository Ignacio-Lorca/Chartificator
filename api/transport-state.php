<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

rate_limit('transport-state', 600, 60);
$userId = require_user();
$sessionId = (int) ($_GET['sessionId'] ?? 0);
if ($sessionId <= 0) {
    json_error('sessionId is required');
}

$pdo = db();
$session = ensure_session_membership($pdo, $sessionId, $userId);
json_ok(transport_payload($session));
