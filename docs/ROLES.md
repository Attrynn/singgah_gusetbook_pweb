# Roles and Permissions

VOWSPACE has three audiences. Two are authenticated (`superadmin`, `owner`); guests are
unauthenticated and identified only by an opaque personal token.

## Role summary

| Role | Entry point | Layout | Primary intent |
| --- | --- | --- | --- |
| Superadmin | `/admin` | Desktop-first admin shell | Operate the whole platform |
| Owner | `/owner` | Mobile-native, max 480px | Follow the wedding as it happens |
| Guest | `/invite/{token}` | Mobile-native, max 480px | Respond, share, interact |
| Public | `/`, `/e/{slug}`, `/wall/{slug}` | Responsive | Discover the event, watch the live wall |

## Route protection

| Scope | Middleware |
| --- | --- |
| `/admin/*` | `auth`, `active`, `role:superadmin` |
| `/owner/*` | `auth`, `active`, `role:owner` |
| `/invite/{token}/*` | `guest.token` (token resolution + event scoping) |
| `/e/{slug}`, `/wall/{slug}` | public (event must be `published`, `live` or `completed`) |

`EnsureActiveUser` logs out and invalidates the session when `users.is_active` is false.
`EnsureRole` returns `403` for a valid session with the wrong role.
`ResolveGuestToken` returns the `guest.invalid` view with HTTP `404` for unknown, expired or
revoked tokens.

## Permission matrix

Legend: ✅ allowed · 👁 read-only · ❌ denied · 🔑 token-scoped

| Capability | Superadmin | Owner | Guest |
| --- | --- | --- | --- |
| View platform dashboard | ✅ | ❌ | ❌ |
| Create / edit / delete events | ✅ | ❌ | ❌ |
| Change event status | ✅ | ❌ | ❌ |
| Manage event venues, schedules, gifts | ✅ | 👁 | 👁 (gift page) |
| Create / edit owner accounts | ✅ | ❌ | ❌ |
| Create / edit / delete guests | ✅ | ❌ | ❌ |
| Import / export guests | ✅ | ❌ | ❌ |
| Manage categories and groups | ✅ | ❌ | ❌ |
| Issue / regenerate invitation & QR tokens | ✅ | ❌ | ❌ |
| Preview an invitation | ✅ | ❌ | 🔑 |
| Moderate RSVP | ✅ | 👁 | 🔑 submit own |
| Check in guests (QR / manual) | ✅ | ❌ | ❌ |
| Moderate guestbook | ✅ | 👁 + favourite | 🔑 submit |
| Moderate media / albums | ✅ | 👁 + album create | 🔑 upload |
| Manage seating | ✅ | 👁 | ❌ |
| Manage song requests | ✅ | 👁 | 🔑 request |
| Manage polls / games | ✅ | 👁 results | 🔑 vote / play |
| Reports, CSV, PDF | ✅ | 👁 | ❌ |
| App settings, audit log | ✅ | ❌ | ❌ |
| Own profile & password | ✅ | ✅ | ❌ |

## Policy layer

Policies exist for defence in depth and are used by controllers/services that accept a `User`:

| Policy | Key rules |
| --- | --- |
| `EventPolicy` | `viewAny`/`create`/`update`/`delete`/`publish`/`manageGuests`/`moderate`/`manageSettings` require superadmin; `view`/`viewGuests` allow any user attached to the event |
| `GuestPolicy` | `view` requires event access; `update`/`delete`/`manage` require superadmin |
| `GuestbookMessagePolicy` | `view` requires event access; `moderate` requires superadmin; `favorite` requires owner with event access |
| `MediaAssetPolicy` | `view` requires event access; `moderate` requires superadmin |

## Token model

1. A guest has two active tokens: `invitation` (the personal link) and `qr` (the check-in pass).
2. Raw tokens are 64 random characters. Only `sha256(raw)` is stored for lookup; the raw value is
   also stored encrypted so the couple/admin can re-display it.
3. Tokens can expire (`invitation` defaults to 365 days, `qr` has no expiry) and can be revoked.
4. Regenerating a guest's tokens revokes every previous token for that guest.
5. A token resolves to exactly one guest and one event. Controllers read the guest/event from the
   request attributes, never from user input, so a guest cannot pivot to another wedding.

## Owner scoping

Owners see only events attached through `event_user`. `ResolvesOwnerEvent`:

- returns `403` when the owner has no event;
- accepts an optional `?event={id}` and returns `403` if that event is not attached;
- otherwise defaults to the owner's earliest event.
