# Chartificator MVP (PHP + MySQL)

Basic cPanel-friendly app for synchronized bar timeline collaboration.

## Features
- Song creation (`name`, `bpm`, `time signature`).
- Shared rehearsal list that any logged-in user can open.
- Rehearsal session setlists with active song switching, reordering, and removal.
- Rehearsals can be created and deleted from the rehearsal page.
- Polling transport sync (`play`, `pause`, `setBpm`, `seekBar`).
- Automatic pause at the end of the active song content with replay/next-song prompt.
- Shared and private bar notes tied directly to songs.
- Song sections (`verse`, `chorus`, `bridge`, etc.) with reorder/delete controls.

## Project Structure
- `public/` multi-page frontend:
  - `index.php` login
  - `songs.php` songs + create session
  - `rehearsal.php` rehearsal list + transport + synced timeline
  - `editor.php` song notes and sections
  - `assets/js/` shared and page scripts
  - `styles.css`, `partials/nav.php`
- `api/` PHP JSON endpoints
- `db/schema.sql` MySQL schema
- `config.sample.php` configuration template
- `.htaccess` and `public/.htaccess` basic shared-host hardening
- `docs/` MVP requirements and contracts

## Setup
1. Create MySQL database.
2. Run `db/schema.sql`.
3. If upgrading an existing deployment, run `db/migration-song-centric-rehearsals.sql`.
4. Copy `config.sample.php` to `config.php` and fill DB credentials.
5. Point web root to `public/` (or keep root and adjust paths as needed).
6. Open `/index.php` in the browser, log in, use **Songs** to create songs and start a rehearsal, then use **Rehearsal** to open any shared session.

## Page flow
1. **Login** (`index.php`) – display name; redirects to Songs if already logged in.
2. **Songs** (`songs.php`) – list/create/edit songs; any logged-in user can update songs; **Create session** opens **Rehearsal** with the first active song in the session setlist.
3. **Rehearsal** (`rehearsal.php`) – browse/create/delete rehearsals, auto-join a session by `sessionId`, manage setlist order/removals, play/pause/seek/BPM, and view the bar-synced timeline.
4. **Editor** (`editor.php`) – edit shared/private notes and sections for a song (`?songId=` required; `sessionId` optional only for navigation back to rehearsal).

## Refactor validation (manual)
After deploy, verify: login redirects to Songs when already authenticated; Songs list loads via `song-list.php`; songs can be opened directly in the editor; any logged-in user can edit songs; create session opens Rehearsal with an initial active song; Rehearsal lists existing sessions; opening a listed rehearsal adds the current user to members; Rehearsal can create/delete sessions, add songs to the setlist, reorder/remove them, and switch active song; section reorder moves section bar blocks and aligned notes; playback pauses automatically at song end; private notes stay per-user.

## Notes
- This MVP intentionally uses polling (no websockets) to stay shared-hosting compatible.
- Lightweight file-based rate limiting is enabled per endpoint bucket.
- PHP CLI is not installed in this environment, so syntax checks were not executable locally here.
- See `docs/deployment-checklist.md` for cPanel rollout checks.
