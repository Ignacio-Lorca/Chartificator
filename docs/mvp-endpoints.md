# MVP Endpoint Contracts (Basic PHP + MySQL)

## Conventions
- Content type: `application/json`
- Auth: PHP session cookie (or equivalent token mapped to `user_id`)
- Error shape:
  - `{ "ok": false, "error": "MESSAGE" }`
- Success shape:
  - `{ "ok": true, "data": { ... } }`

## Song Endpoints

## `POST /api/song-create.php`
Create a new song.

Request:
```json
{
  "name": "My New Song",
  "bpm": 120,
  "timeSignatureNum": 4,
  "timeSignatureDen": 4
}
```

Response:
```json
{
  "ok": true,
  "data": {
    "songId": 101
  }
}
```

## `GET /api/song-get.php?songId=101`
Fetch base song metadata.

Response:
```json
{
  "ok": true,
  "data": {
    "id": 101,
    "name": "My New Song",
    "bpm": 120,
    "timeSignatureNum": 4,
    "timeSignatureDen": 4
  }
}
```

## `GET /api/song-list.php`
List all songs available to any authenticated user.

Response:
```json
{
  "ok": true,
  "data": {
    "songs": [
      {
        "id": 101,
        "name": "My New Song",
        "bpm": 120,
        "timeSignatureNum": 4,
        "timeSignatureDen": 4
      }
    ]
  }
}
```

## `POST /api/song-update.php`
Update song metadata.

Request:
```json
{
  "songId": 101,
  "name": "My New Song v2",
  "bpm": 124,
  "timeSignatureNum": 4,
  "timeSignatureDen": 4
}
```

## Session Endpoints

## `POST /api/session-create.php`
Create a live session for a song.

Request:
```json
{
  "songId": 101,
  "name": "Thursday Rehearsal"
}
```

Response:
```json
{
  "ok": true,
  "data": {
    "sessionId": 55
  }
}
```

## `POST /api/session-join.php`
Join/open a rehearsal by `sessionId` and ensure the current user is a member.

Request:
```json
{
  "sessionId": 55
}
```

## `GET /api/rehearsal-list.php`
Returns the shared rehearsal list with current member counts and active song summary.

## `GET /api/session-snapshot.php?sessionId=55`
Returns all initial UI data in one call:
- canonical transport
- active song
- session setlist
- section list
- shared notes
- caller private notes

Response:
```json
{
  "ok": true,
  "data": {
    "transport": {
      "playState": "paused",
      "currentBpm": 120,
      "beatsPerBar": 4,
      "barOffset": 1,
      "startedAtMs": 0,
      "serverNowMs": 1710000000000
    },
    "activeSong": {
      "id": 101,
      "name": "My New Song",
      "bpm": 120,
      "timeSignatureNum": 4,
      "timeSignatureDen": 4
    },
    "setlist": [],
    "sections": [],
    "sharedNotes": [],
    "privateNotes": []
  }
}
```

## `GET /api/session-songs.php?sessionId=55`
Returns the ordered song setlist for a rehearsal session plus the active song id.

## `POST /api/session-song-add.php`
Add a song to an existing rehearsal session setlist.

Request:
```json
{
  "sessionId": 55,
  "songId": 102
}
```

## `POST /api/session-song-select.php`
Select the active song for a rehearsal session.

Request:
```json
{
  "sessionId": 55,
  "songId": 102
}
```

## `POST /api/session-song-reorder.php`
Move a rehearsal setlist song up or down.

Request:
```json
{
  "sessionId": 55,
  "songId": 102,
  "direction": "up"
}
```

## `POST /api/session-song-remove.php`
Remove a song from a rehearsal setlist. If the removed song is active, the server selects the next valid song or leaves the rehearsal empty.

## `POST /api/session-delete.php`
Delete a rehearsal session and its memberships/setlist.

## Transport Endpoints

## `POST /api/transport-update.php`
Apply one transport action.

Request:
```json
{
  "sessionId": 55,
  "action": "setBpm",
  "value": {
    "bpm": 128
  }
}
```

Supported actions:
- `play`
- `pause`
- `setBpm`
- `seekBar`

Response:
```json
{
  "ok": true,
  "data": {
    "playState": "playing",
    "currentBpm": 128,
    "beatsPerBar": 4,
    "barOffset": 12.25,
    "startedAtMs": 1710000001234,
    "serverNowMs": 1710000001235
  }
}
```

## `GET /api/transport-state.php?sessionId=55`
Polling endpoint called every `250-500ms`.

Response:
```json
{
  "ok": true,
  "data": {
    "playState": "playing",
    "currentBpm": 128,
    "beatsPerBar": 4,
    "barOffset": 12.25,
    "startedAtMs": 1710000001234,
    "serverNowMs": 1710000001600,
    "revision": 88
  }
}
```

## Notes Endpoints

## `POST /api/bar-note-shared-upsert.php`
Upsert shared note for a bar.

Request:
```json
{
  "songId": 101,
  "barNumber": 17,
  "noteText": "Guitar hits accent on beat 3"
}
```

## `POST /api/bar-note-private-upsert.php`
Upsert private note for caller only.

Request:
```json
{
  "songId": 101,
  "barNumber": 17,
  "noteText": "Remember harmony on second pass"
}
```

## `GET /api/bar-notes.php?songId=101`
Returns:
- all shared notes
- caller private notes only

## Section Endpoints

## `POST /api/section-upsert.php`
Create or update section range.

Request:
```json
{
  "songId": 101,
  "label": "chorus",
  "colorHex": "#2B7CFF",
  "barStart": 17,
  "barEnd": 24
}
```

## `GET /api/sections.php?songId=101`
Get ordered section ranges for timeline rendering.

## `POST /api/section-reorder.php`
Move a song section up or down in section order. The section keeps its bar block and aligned notes move with it.

## `POST /api/section-delete.php`
Delete a song section label without deleting the underlying bars or notes.

Response item shape includes:
- `id`
- `label`
- `colorHex`
- `barStart`
- `barEnd`
- `sortOrder`

## Security and Validation Requirements
- Reject calls when user is not session member (where session scoped).
- Allow authenticated users to read and edit song-linked notes and sections without rehearsal membership.
- Reject private-note reads/writes for any `owner_user_id` other than caller.
- Validate BPM, time signature, and bar number ranges.
- Apply simple rate limits to transport updates and polling endpoints.
