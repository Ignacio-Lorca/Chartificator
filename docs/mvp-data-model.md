# MVP Data Model and Sync Architecture

## Architecture Summary
This MVP uses a server-authoritative transport model with HTTP polling:
- Clients send transport commands to PHP endpoints.
- Server writes canonical state to MySQL.
- Clients poll transport state every `250-500ms`.
- Clients interpolate between polls using server-provided timestamps.

This approach is cPanel-safe because it avoids long-running websocket processes.

## Transport Timing Model
Canonical fields on active session:
- `current_bpm`
- `beats_per_bar`
- `play_state` (`playing` or `paused`)
- `bar_offset` (reference bar at `started_at_ms`)
- `started_at_ms` (server Unix time in milliseconds)

Derived timing:
- `ms_per_beat = 60000 / current_bpm`
- `ms_per_bar = ms_per_beat * beats_per_bar`
- When playing:
  - `bar_now = bar_offset + ((server_now_ms - started_at_ms) / ms_per_bar)`

Client drift handling:
- Client computes local bar continuously.
- On each poll response, compare local vs canonical computed bar.
- If drift exceeds threshold (example `0.2` bars), snap to canonical value.

## Relational Schema (MySQL)

## `users`
- `id` BIGINT PK
- `display_name` VARCHAR(64) NOT NULL
- `email` VARCHAR(255) NULL
- `password_hash` VARCHAR(255) NULL
- `created_at` DATETIME NOT NULL

## `songs`
- `id` BIGINT PK
- `name` VARCHAR(120) NOT NULL
- `bpm` DECIMAL(6,2) NOT NULL
- `time_signature_num` TINYINT NOT NULL
- `time_signature_den` TINYINT NOT NULL
- `created_by_user_id` BIGINT NOT NULL FK -> `users.id`
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

## `song_sections`
- `id` BIGINT PK
- `song_id` BIGINT NOT NULL FK -> `songs.id`
- `label` VARCHAR(40) NOT NULL
- `bar_start` INT NOT NULL
- `bar_end` INT NOT NULL
- `sort_order` INT NOT NULL DEFAULT 0
- `updated_at` DATETIME NOT NULL

Constraints:
- `bar_start >= 1`
- `bar_end >= bar_start`

## `sessions`
- `id` BIGINT PK
- `song_id` BIGINT NOT NULL FK -> `songs.id`
- `name` VARCHAR(120) NOT NULL
- `invite_code` VARCHAR(32) NOT NULL UNIQUE
- `current_bpm` DECIMAL(6,2) NOT NULL
- `beats_per_bar` TINYINT NOT NULL
- `play_state` ENUM('playing','paused') NOT NULL
- `bar_offset` DECIMAL(10,4) NOT NULL
- `started_at_ms` BIGINT NOT NULL
- `updated_at` DATETIME NOT NULL

## `session_memberships`
- `id` BIGINT PK
- `session_id` BIGINT NOT NULL FK -> `sessions.id`
- `user_id` BIGINT NOT NULL FK -> `users.id`
- `display_name` VARCHAR(64) NOT NULL
- `joined_at` DATETIME NOT NULL

Unique:
- (`session_id`, `user_id`)

## `bar_notes`
- `id` BIGINT PK
- `song_id` BIGINT NOT NULL FK -> `songs.id`
- `bar_number` INT NOT NULL
- `layer_type` ENUM('shared','private') NOT NULL
- `owner_user_id` BIGINT NULL FK -> `users.id`
- `note_text` TEXT NOT NULL
- `updated_at` DATETIME NOT NULL

Rules:
- Shared notes: `layer_type='shared'` and `owner_user_id IS NULL`
- Private notes: `layer_type='private'` and `owner_user_id IS NOT NULL`

Indexes:
- (`song_id`, `bar_number`)
- (`song_id`, `layer_type`)
- (`song_id`, `owner_user_id`, `bar_number`)

## Access and Privacy Rules
- Every write validates that caller is a member of the target session.
- Shared note read:
  - return `layer_type='shared'` for `song_id`.
- Private note read:
  - return `layer_type='private'` and `owner_user_id = caller_user_id`.
- Never include other users' private notes in any response payload.

## Conflict Handling (MVP)
- Last-write-wins for transport updates by `updated_at` and request order.
- Last-write-wins for note upserts by `(song_id, bar_number, layer_type, owner_user_id)`.
- If two users update BPM close together, latest accepted request becomes canonical.

## Recommended Validation Rules
- BPM range: `30` to `300`.
- Time signature numerator: `1` to `16`.
- Time signature denominator: one of `2, 4, 8, 16`.
- Bar number: `>= 1`.
- Note text max length: `2000` chars.
