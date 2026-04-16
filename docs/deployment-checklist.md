# cPanel Deployment Checklist

## Files and Structure
- Upload project files.
- Ensure `public/` is used as document root when possible.
- If document root cannot be changed, copy public assets to `public_html` and keep API paths aligned.

## Database
- Create MySQL database and user in cPanel.
- Import `db/schema.sql`.
- If upgrading an existing deployment, run `db/migration-song-centric-rehearsals.sql`.
- Verify DB user has read/write permissions.

## Configuration
- Copy `config.sample.php` to `config.php`.
- Fill production DB credentials.
- Keep `config.php` outside public web root when possible.

## Apache and Access Protection
- Ensure root `.htaccess` is present to deny `db/`, `docs/`, and config file access.
- Ensure `public/.htaccess` is present with security headers and directory index.
- Confirm directory listing is disabled.

## PHP Runtime
- Use PHP 8.1+ if available.
- Enable `pdo_mysql`.
- Enable `mbstring` (optional, app has fallback logic but recommended).

## App Smoke Tests
- Login with display name.
- Create song.
- Open a song directly in the editor by `songId`.
- Create session and open rehearsal.
- Open the same rehearsal from second browser via the Rehearsal list.
- Create and delete a rehearsal from the rehearsal page.
- Reorder and remove songs from a rehearsal setlist.
- Verify transport controls sync and polling works.
- Verify playback pauses at the end of the active song and shows replay/next options.
- Verify shared/private notes privacy.
- Verify section creation, reorder, delete, and rendering.

## Security and Operations
- Test 429 responses by rapidly hitting transport endpoints.
- Backup MySQL database regularly.
