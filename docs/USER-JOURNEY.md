# User Journeys

Four journeys define the product. Each one is backed by real routes, real database writes, and real
authorization — nothing below is a mock.

---

## 1. Superadmin — run a wedding from first guest to final report

**Entry:** `/login` → `/admin`

1. **Create the event.** `/admin/events/create` captures the couple, date, venue, feature flags and
   an optional schedule. On save, `EventService::create()` writes the event, its primary venue, the
   default guest categories (`Family`, `Friend`, `Colleague`) and any schedules, then records
   `event.created` in the audit log.
2. **Attach owners.** `/admin/events/{event}` → owner sync writes `event_user` rows so the couple can
   log in and see only their own event.
3. **Add guests.** `/admin/guests/create` or `/admin/guests/import` (CSV). Every guest creation runs
   `InvitationService::issueForGuest()`, which generates a personal invitation token **and** a QR
   token, then creates the `invitations` row. This is why a guest is immediately shareable.
4. **Share.** `/admin/invitations/{guest}` shows the personal URL, the QR SVG, a WhatsApp deep link
   and a copy button. Opening or sharing updates `invitations.status` and `open_count`.
5. **Watch RSVP.** `/admin/rsvps` lists responses with attendance counts and companions. Admins can
   override a response manually (`RsvpService::manualUpdate`), which is audited.
6. **Run the door.** `/admin/checkins/scanner` opens the camera scanner. A scan posts the QR payload
   to `POST /admin/checkins/scan` and returns JSON: `success`, `duplicate`, `wrong_event`,
   `expired`, `revoked` or `invalid`. Duplicate protection is a database unique key plus a row lock,
   so two officers scanning at once cannot both succeed.
7. **Moderate content.** `/admin/guestbook` approves, rejects, hides or pins wishes.
   `/admin/media` approves or rejects guest uploads and features photos.
8. **Seat guests.** `/admin/seating` creates tables with capacity and zone, then assigns guests.
   `unique(event_id, guest_id)` guarantees one seat per guest.
9. **Run interactions.** `/admin/interactions/polls` and `/games` create polls/quizzes, activate
   them, and close them. Results are visible live.
10. **Report.** `/admin/reports` renders per-event summary, RSVP breakdown, check-in timeline and
    interaction results. `reports/export` streams CSV; `reports/pdf` renders a Dompdf document.
11. **Audit.** `/admin/audit-logs` shows every sensitive action with actor, event, IP and metadata.

---

## 2. Owner — follow the wedding without operating it

**Entry:** `/login` → `/owner`

An owner account is linked to one or more events through `event_user`. `ResolvesOwnerEvent` returns
`403` with a clear message when no event is attached, and refuses an `?event=` id the owner does not
own.

1. **Dashboard** (`/owner`) — live counters, the three latest approved wishes, the six latest
   approved photos, the next upcoming schedule item and the last six `event_activities` entries.
2. **Wishes** (`/owner/wishes`) — the approved guestbook feed, with a favourites filter
   (`/owner/wishes/favorites`) and a download endpoint for the archive.
3. **Attendance** (`/owner/attendance`) — read-only RSVP and check-in summary.
4. **Memories** (`/owner/memories`) — the approved gallery; owners may create albums but cannot
   moderate.
5. **Timeline** (`/owner/timeline`) — schedule and activity history.
6. **Interactions** (`/owner/interactions`) — poll and game results.
7. **Story** (`/owner/story`) — the couple profile and event description as guests see it.
8. **Profile** (`/owner/profile`) — name, phone, avatar and password.

Owners never see guest contact details, token hashes, or moderation controls. Admin routes return
`403` for an owner even when they type the URL.

---

## 3. Guest — open one link, do everything from a phone

**Entry:** `/invite/{token}` — no login, no password.

1. **Token resolution.** `ResolveGuestToken` hashes the URL token with SHA-256, looks up
   `guest_tokens.token_hash`, and rejects unknown, expired or revoked tokens with the
   `guest.invalid` view and HTTP `404`. It injects `guest` and `event` into the request and shares
   `token`, `currentGuest`, `currentEvent` with the view.
2. **The invitation** shows the couple, countdown, schedule, venue with a maps link, dress code,
   story, and a link to the live wall. Opening it increments `invitations.open_count` and sets
   `status = link_opened`.
3. **RSVP** (`POST /invite/{token}/rsvp`) — status, attendance count (capped by `max_attendees`),
   companion names, dietary preference, notes. Blocked when the event is not `published`/`live`,
   when `rsvp_enabled` is false, or when `rsvp_deadline` has passed.
4. **Wishes** (`POST /invite/{token}/guestbook`) — public messages enter moderation as `pending`;
   private messages are visible only to the couple and admins.
5. **Photos** (`POST /invite/{token}/gallery`) — validated image, stored as `pending` for moderation.
6. **Digital pass** (`/invite/{token}/pass`) — QR code plus a downloadable PDF for the door.
7. **Gifts** (`/invite/{token}/gifts`) — bank accounts, QRIS image and gift address when enabled.
8. **Interactions** — song requests, poll voting (one vote per guest per poll) and a scored quiz
   (one attempt per guest per game).
9. **Live wall** (`/wall/{slug}`) — public, only approved public messages, refreshed by polling.

Every guest write is scoped to the token's event. A guest cannot pass another `event_id`, another
poll id, or another game id: the service re-checks ownership and returns a validation error or
`404`.

---

## 4. Public visitor — discover and watch

- `/e/{slug}` — public event landing page, only for `published`, `live` or `completed` events.
- `/wall/{slug}` — projected live wall of approved public wishes. `GET /wall/{slug}/messages?since_id=`
  returns JSON so the wall can append new messages without a reload.

---

## Failure paths (all implemented)

| Situation | Result |
| --- | --- |
| Unknown / expired / revoked token | `guest.invalid` view, HTTP 404 |
| Owner without an attached event | HTTP 403 with a message |
| Owner requesting another owner's event | HTTP 403 |
| Owner or guest hitting an admin route | HTTP 403 / redirect to login |
| RSVP after deadline | validation error, no row written |
| Guest action on a draft event | HTTP 403 |
| Duplicate QR scan | `duplicate` JSON, no second check-in |
| QR from another event | `wrong_event` JSON, no check-in |
| Poll voted twice | validation error, single response row |
| Quiz submitted twice | validation error, single attempt row |
| Guest upload of a non-image | validation error, nothing stored |
| Inactive user with a live session | logged out and session invalidated |
