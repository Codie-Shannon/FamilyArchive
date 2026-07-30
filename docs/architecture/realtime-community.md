# Real-Time Community Boundary

Screenshot Group 09 adds membership-aware community records and expiring
real-time signals without claiming that external call infrastructure exists.

## Space Access

`CommunitySpace` records have channels and memberships. The `/community` read
model begins with the signed-in user's active, non-suspended memberships and
selects channels, role counts, presence and voice messages only from the first
authorized space. A public or invite visibility label does not bypass
membership checks.

Space roles—Owner, moderator, member and guest—describe community authority.
They do not grant private archive, original-file or administration access.
Community suspension removes the space from the member read model.

## Presence and Typing

Presence and typing are temporary signals, not durable identity facts.
`RealtimeStatus` treats presence as expired after 90 seconds and typing as
expired after eight seconds. The view receives only the resolved state; raw
timestamps are not rendered.

## Voice Messages

Voice messages retain a private storage key, checksum, duration, MIME type and
moderation state. The community read model selects only `allowed` records and
renders only the fictional member name, channel, duration and MIME type.
Pending and blocked records, storage keys and checksums stay outside the
member-facing view.

This release provides no recording, upload or playback endpoint. A future
write path must enforce the configured ten-minute limit, technical validation,
private storage and moderation before publication.

## Voice Calls

Call records model signalling, active, ended and failed states, but a database
record is not infrastructure proof. Calls are considered deployable only when:

- calls are explicitly enabled;
- a signalling endpoint is configured;
- a TURN relay is configured; and
- browser interoperability tests have passed.

The generated development environment deliberately fails closed. The
Owner-only operations view reports each missing prerequisite and excludes call
identifiers, safe diagnostics, infrastructure endpoints and secrets.
