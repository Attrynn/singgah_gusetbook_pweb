<?php

namespace Tests\Feature\M7\Concerns;

use App\Enums\VendorContactPurpose;
use App\Enums\VendorDeliverableStatus;
use App\Enums\VendorDocumentReviewStatus;
use App\Enums\VendorDocumentType;
use App\Enums\VendorDocumentVisibility;
use App\Enums\VendorEngagementStatus;
use App\Enums\VendorPortalStatus;
use App\Enums\VendorProposalStatus;
use App\Enums\VendorRequirementStatus;
use App\Enums\VendorStatus;
use App\Enums\VendorType;
use App\Models\Event;
use App\Models\EventVendorDeliverable;
use App\Models\EventVendorDocument;
use App\Models\EventVendorEngagement;
use App\Models\EventVendorProposal;
use App\Models\EventVendorRequirement;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Models\VendorPortalEngagementAccess;
use App\Models\VendorPortalMembership;
use App\Models\VendorService;
use App\Models\VendorServicePackage;
use Tests\Feature\M6\Concerns\BuildsOperationsFixtures;

/**
 * Shared Milestone 7 fixtures. Reuses the M6 operations fixtures for
 * organizations, events and staff memberships so M7 tests exercise the same
 * authorization baseline as the internal operations suite.
 */
trait BuildsVendorFixtures
{
    use BuildsOperationsFixtures;

    protected function vendorIn(Organization $organization, array $attributes = []): Vendor
    {
        return Vendor::create(array_merge([
            'organization_id' => $organization->id,
            'name' => 'Vendor '.uniqid(),
            'type' => VendorType::Company->value,
            'status' => VendorStatus::Active->value,
        ], $attributes));
    }

    protected function contactFor(Vendor $vendor, array $attributes = []): VendorContact
    {
        return VendorContact::create(array_merge([
            'vendor_id' => $vendor->id,
            'name' => 'Kontak Vendor',
            'email' => 'contact-'.uniqid().'@example.test',
            'purpose' => VendorContactPurpose::Operational->value,
            'is_primary' => true,
            'is_active' => true,
        ], $attributes));
    }

    protected function serviceFor(Vendor $vendor, array $attributes = []): VendorService
    {
        return VendorService::create(array_merge([
            'vendor_id' => $vendor->id,
            'name' => 'Layanan '.uniqid(),
            'is_active' => true,
            'version' => 1,
        ], $attributes));
    }

    protected function packageFor(VendorService $service, array $attributes = []): VendorServicePackage
    {
        return VendorServicePackage::create(array_merge([
            'vendor_service_id' => $service->id,
            'name' => 'Paket '.uniqid(),
            'price_amount' => 15000000,
            'currency' => 'IDR',
            'inclusions' => ['Dekorasi'],
            'exclusions' => ['Transport'],
            'is_active' => true,
            'version' => 1,
        ], $attributes));
    }

    protected function requirementIn(Event $event, array $attributes = []): EventVendorRequirement
    {
        return EventVendorRequirement::create(array_merge([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'title' => 'Kebutuhan '.uniqid(),
            'status' => VendorRequirementStatus::Open->value,
        ], $attributes));
    }

    protected function proposalFor(
        EventVendorRequirement $requirement,
        Vendor $vendor,
        array $attributes = [],
    ): EventVendorProposal {
        return EventVendorProposal::create(array_merge([
            'organization_id' => $requirement->organization_id,
            'event_id' => $requirement->event_id,
            'event_vendor_requirement_id' => $requirement->id,
            'vendor_id' => $vendor->id,
            'title' => 'Proposal '.uniqid(),
            'quoted_amount' => 12000000,
            'currency' => 'IDR',
            'status' => VendorProposalStatus::Submitted->value,
            'submitted_at' => now(),
        ], $attributes));
    }

    protected function engagementFor(Event $event, Vendor $vendor, array $attributes = []): EventVendorEngagement
    {
        return EventVendorEngagement::create(array_merge([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'vendor_id' => $vendor->id,
            'title' => 'Engagement '.uniqid(),
            'status' => VendorEngagementStatus::Draft->value,
            'agreed_amount' => 10000000,
            'currency' => 'IDR',
        ], $attributes));
    }

    protected function confirmedEngagementFor(Event $event, Vendor $vendor, array $attributes = []): EventVendorEngagement
    {
        return $this->engagementFor($event, $vendor, array_merge([
            'status' => VendorEngagementStatus::Confirmed->value,
            'confirmed_at' => now(),
        ], $attributes));
    }

    protected function documentFor(EventVendorEngagement $engagement, array $attributes = []): EventVendorDocument
    {
        return EventVendorDocument::create(array_merge([
            'organization_id' => $engagement->organization_id,
            'event_id' => $engagement->event_id,
            'vendor_id' => $engagement->vendor_id,
            'event_vendor_engagement_id' => $engagement->id,
            'type' => VendorDocumentType::Agreement->value,
            'title' => 'Perjanjian',
            'disk' => 'local',
            'path' => 'vendor-documents/test-'.uniqid().'.pdf',
            'original_name' => 'agreement.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'review_status' => VendorDocumentReviewStatus::Draft->value,
            'visibility' => VendorDocumentVisibility::InternalOnly->value,
            'version' => 1,
        ], $attributes));
    }

    protected function deliverableFor(EventVendorEngagement $engagement, array $attributes = []): EventVendorDeliverable
    {
        return EventVendorDeliverable::create(array_merge([
            'organization_id' => $engagement->organization_id,
            'event_id' => $engagement->event_id,
            'event_vendor_engagement_id' => $engagement->id,
            'title' => 'Deliverable '.uniqid(),
            'status' => VendorDeliverableStatus::NotStarted->value,
        ], $attributes));
    }

    protected function portalUser(?string $email = null): User
    {
        return User::factory()->create([
            'email' => $email ?? 'portal-'.uniqid().'@example.test',
            'is_active' => true,
        ]);
    }

    protected function portalMemberFor(Vendor $vendor, User $user, array $attributes = []): VendorPortalMembership
    {
        return VendorPortalMembership::create(array_merge([
            'organization_id' => $vendor->organization_id,
            'vendor_id' => $vendor->id,
            'user_id' => $user->id,
            'invited_email' => $user->email,
            'status' => VendorPortalStatus::Active->value,
            'accepted_at' => now(),
        ], $attributes));
    }

    protected function grantPortalAccess(
        VendorPortalMembership $membership,
        EventVendorEngagement $engagement,
    ): VendorPortalEngagementAccess {
        return VendorPortalEngagementAccess::create([
            'vendor_portal_membership_id' => $membership->id,
            'event_vendor_engagement_id' => $engagement->id,
            'granted_at' => now(),
        ]);
    }
}
