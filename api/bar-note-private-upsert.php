<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

require_method('POST');
rate_limit('bar-note-private-upsert', 120, 60);
require_user();
json_error('bar-note-private-upsert endpoint has been retired; use section-upsert.php', 410);
