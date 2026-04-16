# MVP Test Checklist

## Test Setup
- Deploy MVP to HostGator cPanel environment.
- Prepare 5 test users with separate browser profiles/devices.
- Use one shared song and one active session for most scenarios.

## A. Song and Session Basics
- [ ] Create song with valid `name`, `bpm`, and `time signature`.
- [ ] Open editor directly with `?songId=` and confirm notes/sections load without rehearsal context.
- [ ] Reject invalid BPM (`<30` or `>300`).
- [ ] Reject invalid time signature denominator (not `2/4/8/16`).
- [ ] Create session from song.
- [ ] Open the same rehearsal from 5 clients using the shared rehearsal list.
- [ ] Create a rehearsal from the rehearsal page and delete it from the rehearsal list.

## B. Transport Synchronization
- [ ] Start playback from one client; all 5 clients begin scrolling.
- [ ] Pause from a different client; all 5 clients stop within one poll cycle.
- [ ] Change BPM during playback; all clients adapt scroll rate.
- [ ] Seek to a target bar; all clients converge to same bar.
- [ ] Run 10-minute playback and verify drift remains acceptable.
- [ ] Confirm transport polling interval performs acceptably at `250-500ms`.

## C. Bar Notes (Shared and Private)
- [ ] Add shared note at a bar; all users can see it.
- [ ] Update shared note; all users see latest text.
- [ ] Add private note at a bar on User A.
- [ ] Verify User B/C/D/E cannot see User A private note.
- [ ] Verify private note persists after refresh/relogin for owner.

## D. Section Grouping
- [ ] Create sections: intro, verse, chorus, bridge with bar ranges.
- [ ] Verify section labels align to expected bars on all clients.
- [ ] Update section range and confirm consistent rendering.
- [ ] Reorder sections and confirm the section bar block and its notes move together.
- [ ] Delete a section and confirm only the section label is removed.
- [ ] Validate overlapping/invalid ranges are handled as designed.

## E. Reconnection and Recovery
- [ ] Disconnect one client mid-playback and reconnect.
- [ ] Reconnected client loads current transport state correctly.
- [ ] Reconnected client loads shared notes and own private notes.
- [ ] Reconnected client does not receive others' private notes.

## F. Security and Access Control
- [ ] Non-member cannot read session snapshot.
- [ ] Non-member cannot update transport.
- [ ] User cannot request private notes for another user.
- [ ] Authenticated user can edit song notes/sections without rehearsal membership.

## G. Performance and Stability (MVP Target)
- [ ] 5 concurrent clients maintain usable responsiveness.
- [ ] No endpoint has repeated server errors in 15-minute rehearsal simulation.
- [ ] Polling endpoints stay within acceptable response latency for hosting tier.

## Exit Criteria
MVP is considered ready when:
- All critical tests in sections A-F pass.
- Sync behavior is stable for 5 concurrent users over 10 minutes.
- Privacy boundaries for private notes are confirmed.
- Core rehearsal flow (create song -> join -> play -> annotate -> reconnect) succeeds end to end.
- Core rehearsal flow (create song -> create/open rehearsal -> play -> annotate -> reorder -> reconnect) succeeds end to end.
