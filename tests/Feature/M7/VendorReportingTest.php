<?php

namespace Tests\Feature\M7;

use App\Enums\EventStaffRole;
use App\Enums\OrganizationRole;
use App\Enums\VendorDeliverableStatus;
use App\Models\Organization;
use App\Models\User;
use App\Services\VendorReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\M7\Concerns\BuildsVendorFixtures;
use Tests\TestCase;

class VendorReportingTest extends TestCase
{
    use BuildsVendorFixtures, RefreshDatabase;

    private VendorReportService $reports;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reports = app(VendorReportService::class);
    }

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_hub_counts_are_scoped_to_the_event(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $vendorA = $this->vendorIn($org);
        $vendorB = $this->vendorIn($org);

        $this->confirmedEngagementFor($event, $vendorA);
        $this->engagementFor($event, $vendorB);

        $requirement = $this->requirementIn($event);
        $this->proposalFor($requirement, $vendorA);

        $engagement = $this->engagementFor($event, $vendorA);
        $this->deliverableFor($engagement, [
            'status' => VendorDeliverableStatus::InProgress->value,
            'due_at' => now()->subDay(),
        ]);

        $hub = $this->reports->hub($event, true);

        $this->assertSame(2, $hub['vendors']['total']);
        $this->assertSame(2, $hub['vendors']['active']);
        $this->assertSame(3, $hub['engagements']['total']);
        $this->assertSame(1, $hub['engagements']['by_status']['confirmed']);
        $this->assertSame(2, $hub['engagements']['by_status']['draft']);
        $this->assertSame(1, $hub['proposals']['pending']);
        $this->assertSame(1, $hub['deliverables']['overdue']);
    }

    public function test_cross_org_data_is_excluded_from_hub_and_reports(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $this->engagementFor($event, $vendor, ['title' => 'Engagement utama']);

        $otherOrg = $this->organization('other');
        $otherEvent = $this->eventIn($otherOrg);
        $otherVendor = $this->vendorIn($otherOrg, ['name' => 'Vendor Luar']);
        $this->engagementFor($otherEvent, $otherVendor, ['title' => 'Engagement luar']);

        $hub = $this->reports->hub($event, true);

        $this->assertSame(1, $hub['vendors']['total']);
        $this->assertSame(1, $hub['engagements']['total']);

        $report = $this->reports->report($event, 'engagements');

        $this->assertCount(1, $report['rows']);
        $this->assertSame('Engagement utama', $report['rows'][0][1]);
    }

    public function test_commercial_totals_are_omitted_when_not_requested(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $this->engagementFor($event, $vendor, ['agreed_amount' => 10000000, 'currency' => 'IDR']);

        $hubWithoutCommercial = $this->reports->hub($event, false);
        $this->assertArrayNotHasKey('commercial', $hubWithoutCommercial);

        $reportWithoutCommercial = $this->reports->report($event, 'engagements', false);
        $this->assertNotContains('Nilai Kesepakatan', $reportWithoutCommercial['columns']);

        $hubWithCommercial = $this->reports->hub($event, true);
        $this->assertArrayHasKey('commercial', $hubWithCommercial);
        $this->assertSame(10000000.0, $hubWithCommercial['commercial']['agreed_by_currency']['IDR']);

        $reportWithCommercial = $this->reports->report($event, 'engagements', true);
        $this->assertContains('Nilai Kesepakatan', $reportWithCommercial['columns']);
    }

    public function test_csv_export_returns_bom_and_header_row(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $vendor = $this->vendorIn($org);
        $this->engagementFor($event, $vendor);

        $response = $this->actingAs($owner)->get(
            route('workspace.events.vendors.reports.export', [$org, $event, 'type' => 'engagements']),
        );

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Vendor,Engagement,Status,Koordinator', $content);
    }

    public function test_export_requires_export_permission(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $general = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->actingAs($general->user)
            ->get(route('workspace.events.vendors.reports.export', [$org, $event, 'type' => 'engagements']))
            ->assertForbidden();
    }

    public function test_report_rows_are_event_scoped(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $otherEvent = $this->eventIn($org);
        $vendor = $this->vendorIn($org);

        $this->engagementFor($event, $vendor, ['title' => 'Hanya acara ini']);
        $this->engagementFor($otherEvent, $vendor, ['title' => 'Acara lain']);

        $report = $this->reports->report($event, 'engagements');

        $this->assertCount(1, $report['rows']);
        $this->assertSame('Hanya acara ini', $report['rows'][0][1]);
    }
}
