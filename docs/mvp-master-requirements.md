# MVP Master Requirements: Synchronized Bar Timeline

## Product Goal
Create a lightweight web tool for small bands (about 5 concurrent users) to rehearse and align on song structure in real time. Users create songs with tempo settings, follow synchronized scrolling by bars, and collaborate through shared and private notes.

## Primary Users
- Band members in active rehearsal.
- Songwriters coordinating arrangement notes.

## Core User Stories
- As a user, I can create a song with a name, BPM, and time signature.
- As a user, I can open a shared session for that song and see bars scroll in sync with others.
- As a user, I can write shared notes attached to specific bars that everyone sees.
- As a user, I can write private notes attached to specific bars that only I see.
- As a user, I can group bar ranges into sections (verse, chorus, bridge, intro, outro).
- As a user, I can reconnect and continue from the current transport state.

## MVP Scope
### In Scope
- Basic PHP backend running on cPanel-compatible shared hosting.
- MySQL persistence for songs, sessions, notes, and sections.
- Polling-based synchronization (`250-500ms`) for transport updates.
- Song CRUD (at least create and update essential fields).
- Rehearsal create/open from a shared rehearsal list.
- Transport actions: play, pause, set BPM, seek bar.
- Bar-level note CRUD for shared and private layers.
- Song section CRUD with labeled bar ranges.

### Out of Scope
- Audio playback engine or sample-accurate timing.
- Offline mode and sync conflict replay.
- Advanced permissions and role management.
- Mobile-native apps.

## Functional Requirements
1. Song data includes:
   - `name`
   - `bpm`
   - `time_signature_num`
   - `time_signature_den`
2. Scrolling position is derived from canonical transport state and song timing.
3. Any session participant may control transport in MVP.
4. Shared notes are visible to all session members.
5. Private notes are visible only to the note owner.
6. Section labels support default musical labels plus custom values.
7. Session snapshot endpoint returns:
   - transport state
   - shared notes
   - caller private notes
   - section list

## Non-Functional Requirements
- Support 5 concurrent users per song session.
- Typical synchronization update visibility within one poll cycle.
- Data persistence across sessions/reloads.
- Minimal setup and deploy complexity on shared hosting.

## UX Requirements (MVP)
- Clear current bar indicator and timeline scrolling.
- Visible BPM and time signature controls.
- Bar note entry UI that maps one note to one bar at minimum.
- Section markers rendered over timeline ranges.
- Distinct styling for shared vs private notes.

## Acceptance Criteria
- 5 browsers in one session remain aligned for at least 10 minutes.
- Song with name/BPM/time signature can be created and reopened.
- BPM updates by one user propagate to others within `<= 500ms` typical conditions.
- Shared notes are visible to all users in the session.
- Private notes are never returned to other users.
- Section labels and bar ranges are consistent across clients.
- Reconnected users load the current transport position plus relevant notes/sections.
