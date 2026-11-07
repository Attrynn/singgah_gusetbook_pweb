# VOWSPACE — Storage & Media Lifecycle Report

## 1. Decision

Guest media is **private by default**. Originals, thumbnails, medium renditions and future
responsive derivatives all live on `MEDIA_PRIVATE_DISK` (`local` by default). The `public` disk is
reserved for staff artefacts: event covers, avatars and QRIS images.

Approval is an access-policy decision, not a publication action. No approved object receives a
permanent public URL.

## 2. State model

| State | Meaning | Delivery | Physical object |
| --- | --- | --- | --- |
| Pending | Uploaded, awaiting moderation | Denied | Private |
| Approved | Accepted for the event audience | Signed URL only | Private |
| Rejected | Refused by moderation | Denied | Deleted (best effort, retryable) |
| Demoted (Approved → Pending) | Withdrawn | Denied immediately | Original retained, derivatives removed |
| Deleted | Removed by staff | Denied | Deleted before the row |
| Purged | Retention expiry | Denied | Removed with verification |

`MediaAsset::isPublished()` now means "approved and deliverable"; it no longer implies a public
object. The previous "Approved ⇒ copy to public disk" flow is removed.

## 3. Delivery

`GET /media/{asset}?variant=thumb|medium|original` (`media.show`):

1. Requires a valid Laravel signature (`signed` middleware).
2. Re-checks: asset not trashed, status approved, event exists and is not trashed, event status is
   `published`/`live`/`completed`, gallery enabled, variant file exists.
3. Streams from the private disk with `Cache-Control: private, max-age=300` and `nosniff`.

URLs are minted by `MediaAsset::url()/thumbnailUrl()/mediumUrl()` with a 60-minute TTL
(`MEDIA_DELIVERY_TTL_MINUTES`). Revocation does not depend on URL expiry: the controller denies
access on the next request.

Staff preview of non-approved media remains restricted to superadmins
(`admin.media.preview`). Owner downloads read from the asset's recorded disk.

## 4. Cleanup and failure handling

- **Approval**: verifies the original exists; writes derivatives only when `Storage::put()` returns
  true; a failed write is reported and never recorded as a path.
- **Rejection/demotion**: access state commits first, then derivatives are deleted; failures are
  reported and left for the purge command. No success is reported for a storage write that failed.
- **Asset deletion**: files are deleted first (checked); a storage failure throws and the database
  row is preserved. On success the album cover reference is cleared and the row is soft-deleted.
- **Album deletion**: detaches media, clears the cover, audits critically, then deletes the album.
- **Event purge**: inventories every `media_assets` row (including trashed), deletes original,
  thumbnail, medium and legacy public/staging copies, verifies absence, and only then removes the
  event. A remaining object aborts that event and returns a non-zero exit code.
- **Purge idempotency**: missing files count as deleted, so a re-run is safe.

## 5. Legacy migration

`php artisan vowspace:migrate-legacy-media [--dry-run] [--keep-source]`

- Inventories assets whose `disk` is not the private disk (including trashed rows).
- Reports missing source objects and destination hash conflicts; conflicts are skipped, never
  overwritten.
- Copies each object, verifies destination existence and SHA-256 equality, then updates the
  database reference in a transaction, then deletes the old public object.
- Dry-run changes nothing. Failures are logged and the row is left untouched for retry.

## 6. Accepted risks / gaps

- Derivatives are generated synchronously in the request. The pre-decode guard bounds format and
  pixel count *before* GD allocation, but moving generation to a queue is not implemented
  (accepted risk; no queue worker is assumed to be running).
- CDN invalidation is not implemented because no CDN is configured. If one is added, withdrawal
  must purge the CDN cache in addition to denying access.
- `checkins.ip_address` is retained with the check-in record; it is removed by purge.
