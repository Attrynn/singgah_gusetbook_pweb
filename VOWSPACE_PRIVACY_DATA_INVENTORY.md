# VOWSPACE — Privacy & Personal Data Inventory

Audited: branch `master`, HEAD `e837ae9` plus the remediation working tree.
This inventory reflects source behaviour, not a legal compliance claim. Items marked
**POLICY DECISION REQUIRED** need an authorised product/legal decision; no regulation is
asserted without evidence.

## 1. Inventory

| Category | Where stored | Purpose | Access scope | Retention / deletion |
| --- | --- | --- | --- | --- |
| Account name, email, phone | `users` | Authentication, audit attribution, contact | The user; superadmin via owner management | Until account deletion; soft-deleted rows purge after `TRASHED_RETENTION_DAYS` (30) |
| Password hash | `users.password` | Authentication | None (never rendered) | Replaced on change; invalidates other sessions |
| Pending email + token hash | `users.pending_email`, `email_change_token_hash` | Verify control of a new address | Superadmin (DB) | 60 minutes (`EMAIL_CHANGE_TTL_MINUTES`), cleared on success/expiry |
| Avatar / event cover / QRIS image | `public` disk | Staff-facing presentation | Public URL by design (staff artefact) | Replaced on upload; event purge removes |
| Guest identity (name, email, phone, notes) | `guests` | Invitation, RSVP, seating, check-in | Event owner, superadmin; guest sees own record | Deleted with event or after soft-delete retention |
| Guest companion count | `guests.max_attendees` | RSVP capacity | Same as guest | Same as guest |
| RSVP | `rsvps` | Attendance planning | Event owner, superadmin, guest (own) | Cascade with guest/event |
| Wedding wishes / guestbook | `guestbook_messages` | Guestbook display | Approver + public for approved public messages | Soft-delete retention, then purge |
| Guest photos + derivatives | `media_assets`, private disk | Memory gallery | Event audience via signed delivery; owner; superadmin | Rejected: file removed; deleted asset: file removed then row; event purge removes all |
| Invitation / QR tokens | `guest_tokens` (SHA-256 hash, encrypted ciphertext) | Authorised access, check-in | Never disclosed except to the guest pass page | Invitation 365 days; QR 30 days or event end + 3 days |
| Check-in record | `checkins` | Attendance truth | Event owner, superadmin | Cascade with guest/event |
| IP address / User-Agent | `audit_logs`, `checkins.ip_address` | Security accountability | Superadmin audit screen | Audit identifiers minimised after 90 days (`AUDIT_LOG_IP_RETENTION_DAYS`); check-in IP goes with the record |
| Session rows | `sessions` | Authentication | None | `SESSION_LIFETIME` (120 min) |
| Queue payloads / failed jobs | `jobs`, `failed_jobs` | Background work | Operator only | Laravel/operator policy; not user-facing |
| Backups | External (operator) | Recovery | Operator only | **POLICY DECISION REQUIRED** — backup expiry is not controlled by this application |

## 2. Access rules

- Guests reach only their own event through a token; inactive/blocked guests are refused by
  `ResolveGuestToken`.
- Owners reach only events attached to their account (`ResolvesOwnerEvent`); a selected event id
  is never proof of ownership.
- Superadmins reach all events through the `role:superadmin` group.
- Guest media is never served from a public URL; delivery requires a signature and re-checks
  asset status, event status and gallery availability on every request.

## 3. Retention implemented in code

| Mechanism | Setting | Behaviour |
| --- | --- | --- |
| Soft-deleted records | `TRASHED_RETENTION_DAYS` (30) | `vowspace:purge-trashed` force-deletes after the window |
| Audit identifiers | `AUDIT_LOG_IP_RETENTION_DAYS` (90) | `ip_address` and `user_agent` nulled; audit rows preserved |
| QR passes | `QR_TOKEN_TTL_DAYS` (30) + `QR_TOKEN_GRACE_DAYS` (3) | Expiry bounded by event end + grace |
| Invitation links | `INVITATION_TTL_DAYS` (365) | Expired links resolve to 404 |
| Email verification | `EMAIL_CHANGE_TTL_MINUTES` (60) | Single-use, hashed, cleared on expiry |
| Sessions | `SESSION_LIFETIME` (120) | Standard session expiry; password change revokes others |

## 4. Deletion and export

- Event deletion is soft; access is revoked immediately through status/trashed checks.
- Permanent purge inventories originals, thumbnails, medium renditions and legacy public copies;
  the database row is only removed when storage deletion is verified, keeping failed runs
  retryable.
- Guest data export is available as CSV from the admin guest/report screens (staff only).
- Backups are outside application control. **POLICY DECISION REQUIRED:** backup retention and
  restore-deletion behaviour must be documented by the operator before production.

## 5. Gaps and accepted risks

- No automated retention for `checkins.ip_address` independent of the check-in record; it is
  removed when the guest/event is purged.
- Companion dietary preferences and similar categories do not exist in the schema; no collection,
  no retention obligation.
- No DSAR self-service portal; requests are handled operationally by a superadmin.
