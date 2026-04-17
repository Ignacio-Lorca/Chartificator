<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

rate_limit('bar-notes', 240, 60);
require_user();
json_error('bar-notes endpoint has been retired; use sections.php', 410);
