<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

rate_limit('auth-users', 60, 60);

$users = configured_user_names();
if (!$users) {
    json_error('No preconfigured users available', 500);
}

json_ok([
    'users' => array_map(static function (string $displayName): array {
        return ['displayName' => $displayName];
    }, $users),
]);
