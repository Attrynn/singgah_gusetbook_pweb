# Demo Walkthrough

A 15-minute tour that touches every major capability. Start from a freshly seeded database:

```bash
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

All demo passwords are `password`.

| Role | Email |
| --- | --- |
| Superadmin | `admin@vowspace.test` |
| Owner (groom) | `raka@vowspace.test` |
| Owner (bride) | `alya@vowspace.test` |
| Owner (second event) | `dimas@vowspace.test` |

The seeded dataset:

| Item | Detail |
| --- | --- |
| Event 1 | `Raka & Alya`, **live**, 38 days out, `The Aruna Hall`, Jakarta |
| Event 2 | `Dimas & Nadia`, **completed**, 45 days ago, `Padma Garden`, Bali |
| Guests | 38 across both events, each with an invitation token and a QR token |
| Content | RSVPs, check-ins, guestbook messages (pending/approved/private), media rows, seating, polls, a quiz, song requests |
| System | app settings, audit log entries, activity feed entries |

---

## Act 1 — Superadmin console (desktop)

1. Sign in at `/login` as `admin@vowspace.test`.
2. **Dashboard** `/admin` — counts are read from the database, not hardcoded. Note the RSVP split and
   the check-in counter.
3. **Events** `/admin/events` — two rows. Open `Raka & Alya`.
   - Feature flags are real: turn off the guestbook, then reload the guest invitation later in Act 3
     and the section is gone.
   - Status workflow buttons move `live → completed → archived` and back.
4. **Guests** `/admin/guests` — filter, search, paginate. Open a guest:
   - Invitation status, open count, RSVP, check-in.
   - **Regenerate tokens** revokes the previous pair and issues a new one.
   - Export CSV from the toolbar.
5. **Import** `/admin/guests/import` — paste CSV or upload a file; the preview tells you how many
   rows will be created. Each created guest gets tokens automatically.
6. **Invitations** `/admin/invitations` — copy a personal URL, scan the QR SVG, or open the WhatsApp
   deep link. Click **Preview** to see the guest view without a token.
7. **Check-in** `/admin/checkins/scanner` — grant camera access, or paste a QR payload manually.
   - First scan → `success` with a green card and the guest's RSVP status.
   - Scan the same code again → `duplicate`, and the check-in counter does not move.
   - Scanning a QR belonging to the other event → `wrong_event`, nothing is written.
8. **Guestbook** `/admin/guestbook` — approve a pending message, then open `/wall/raka-alya` in a
   second window: the approved message appears, private and pending ones never do.
9. **Media** `/admin/media` — approve a pending guest upload, then feature it.
10. **Seating** `/admin/seating` — tables have capacity and zone; assigning a guest twice is rejected
    by a unique constraint.
11. **Interactions** `/admin/interactions/polls` and `/games` — open a poll, close it, watch results.
12. **Reports** `/admin/reports` — summary, RSVP breakdown, check-in timeline, interaction results.
    Export CSV; open the PDF.
13. **Settings** `/admin/settings` — change the app name and timezone; the change is persisted in
    `app_settings`.
14. **Audit log** `/admin/audit-logs` — every action above is recorded with actor, IP and metadata.

---

## Act 2 — Owner space (phone-sized)

Open DevTools device emulation (390 × 844) or a real phone.

1. Sign in as `raka@vowspace.test` → `/owner`.
2. The shell is capped at 480px and uses the cold-minimalist palette; navigation is a bottom bar.
3. **Dashboard** — next schedule, latest approved wishes, latest photos, recent activity.
4. **Wishes** — approved messages only; tap the heart to favourite, then open the favourites filter.
5. **Attendance** — RSVP and check-in numbers, read-only.
6. **Memories** — approved gallery.
7. **Timeline** — schedule plus activity feed.
8. **Interactions** — poll and quiz results.
9. **Story** — the couple profile as guests read it.
10. **Profile** — change name/phone; password change is validated against the current password.

Now try `/admin` while signed in as the owner → **403**. Then sign in as `dimas@vowspace.test` and
confirm the owner only ever sees `Dimas & Nadia`; `/owner?event=<raka-alya id>` also returns 403.

---

## Act 3 — Guest experience (phone-sized, no login)

Get a live guest link:

```bash
php artisan tinker --execute="
  \$g = App\Models\Guest::where('event_id', App\Models\Event::where('slug','raka-alya')->value('id'))->first();
  echo app(App\Services\InvitationService::class)->rawToken(\$g);
"
```

Open `http://127.0.0.1:8000/invite/<token>`.

1. **Invitation** — couple, countdown, schedule, venue + maps link, dress code, story, live-wall link.
   The guest's name is rendered from the token, never from a query string.
2. **RSVP** — submit `confirmed` with 2 attendees. It persists; reload and the form shows your answer.
   Try 99 attendees → validation error capped by `max_attendees`.
3. **Wishes** — post a public message (enters `pending`) and a private one (visible only to the
   couple/admin).
4. **Photo** — upload a JPG. It lands in `/admin/media` as `pending`.
5. **Pass** — QR code plus a downloadable PDF.
6. **Gifts** — bank account, QRIS image, gift address.
7. **Song request** — submits and appears in `/admin/interactions/songs`.
8. **Poll** — vote once; voting again is rejected.
9. **Quiz** — answer and submit; the score page shows the review; a second submission is rejected.
10. **Live wall** — `/wall/raka-alya` appends approved public messages without a page reload.

### Security checks worth demoing

| Try this | Expected |
| --- | --- |
| `/invite/garbage` | 404 `guest.invalid` view |
| Regenerate the guest's tokens in admin, then reload the old link | 404 |
| Post to another event's poll id with your token | validation error, no row |
| Open `/invite/<token>/game/<other event's game id>` | 404 |
| Open `/admin` without logging in | redirect to `/login` |
| Sign in as owner, then open `/admin/guests` | 403 |

---

## Act 4 — Public surfaces

- `/` — landing page with the two demo events.
- `/e/raka-alya` — public event page for a live event.
- `/e/dimas-nadia` — completed event, still visible as an archive.
- `/wall/raka-alya` — live wall (approved public messages only).

A draft event is not publicly visible: change `Raka & Alya` to `draft` in admin and `/e/raka-alya`
returns 404.

---

## Reset

```bash
php artisan migrate:fresh --seed
```

This restores the demo to a clean state. All tokens are regenerated, so any link you saved earlier
stops working — which is also a convenient way to demo token revocation.
