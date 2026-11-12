<?php

namespace App\Services;

use App\Enums\VendorDocumentReviewStatus;
use App\Enums\VendorDocumentVisibility;
use App\Models\Event;
use App\Models\EventVendorDocument;
use App\Models\EventVendorEngagement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private storage and lifecycle for vendor documents.
 *
 * Documents are written to the private disk only and are streamed through
 * authorization-aware controllers. A new version never overwrites or hides the
 * previously agreed document; it is linked through `supersedes_id`.
 */
class VendorDocumentService
{
    /** Business documents only — no executables, scripts or archives. */
    public const ALLOWED_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt',
    ];

    public const MAX_BYTES = 20 * 1024 * 1024;

    public function store(
        Event $event,
        EventVendorEngagement $engagement,
        UploadedFile $file,
        array $data,
        User $actor,
    ): EventVendorDocument {
        if ((int) $engagement->event_id !== (int) $event->id) {
            throw ValidationException::withMessages([
                'engagement' => 'Engagement tidak termasuk dalam acara ini.',
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => 'Jenis dokumen tidak diizinkan.',
            ]);
        }

        if (($file->getSize() ?: 0) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'file' => 'Ukuran dokumen melebihi 20 MB.',
            ]);
        }

        $supersedes = null;

        if (! empty($data['supersedes_id'])) {
            $supersedes = EventVendorDocument::query()
                ->where('event_vendor_engagement_id', $engagement->id)
                ->find($data['supersedes_id']);

            if ($supersedes === null) {
                throw ValidationException::withMessages([
                    'supersedes_id' => 'Dokumen yang digantikan tidak ditemukan pada engagement ini.',
                ]);
            }
        }

        $disk = 'local';
        $path = $file->store("events/{$event->id}/vendor-documents", $disk);

        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'Dokumen gagal disimpan. Silakan coba lagi.']);
        }

        try {
            return DB::transaction(fn (): EventVendorDocument => EventVendorDocument::create([
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'vendor_id' => $engagement->vendor_id,
                'event_vendor_engagement_id' => $engagement->id,
                'event_vendor_proposal_id' => $data['event_vendor_proposal_id'] ?? null,
                'type' => $data['type'],
                'title' => $data['title'],
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize() ?: 0,
                'version' => $supersedes !== null ? $supersedes->version + 1 : 1,
                'supersedes_id' => $supersedes?->id,
                'review_status' => $data['review_status'] ?? VendorDocumentReviewStatus::Draft->value,
                'visibility' => $data['visibility'] ?? VendorDocumentVisibility::InternalOnly->value,
                'effective_date' => $data['effective_date'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'uploaded_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]));
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    public function review(
        EventVendorDocument $document,
        VendorDocumentReviewStatus $status,
        User $actor,
        ?string $notes = null,
    ): EventVendorDocument {
        if (! $document->review_status->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'review_status' => 'Perubahan status tinjauan dokumen tidak valid.',
            ]);
        }

        if (in_array($status, [VendorDocumentReviewStatus::Approved, VendorDocumentReviewStatus::Rejected], true)
            && $document->review_status === VendorDocumentReviewStatus::Draft) {
            throw ValidationException::withMessages([
                'review_status' => 'Dokumen harus ditinjau terlebih dahulu sebelum disetujui atau ditolak.',
            ]);
        }

        $document->forceFill([
            'review_status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'notes' => $notes ?? $document->notes,
        ])->save();

        return $document->refresh();
    }

    public function stream(EventVendorDocument $document): StreamedResponse
    {
        $disk = Storage::disk($document->disk);

        abort_unless($disk->exists($document->path), 404);

        return $disk->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
