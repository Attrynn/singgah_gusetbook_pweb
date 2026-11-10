<?php

namespace Tests\Feature\M6;

use App\Enums\EventStaffRole;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\OperationsStatus;
use App\Enums\OrganizationRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\EventIncident;
use App\Models\EventOperation;
use App\Models\EventTask;
use App\Models\Organization;
use App\Models\User;
use App\Services\OperationsLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\M6\Concerns\BuildsOperationsFixtures;
use Tests\TestCase;

class OperationsLifecycleTest extends TestCase
{
    use BuildsOperationsFixtures, RefreshDatabase;

    private OperationsLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lifecycle = app(OperationsLifecycleService::class);
    }

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_ensure_creates_draft_operation_once(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);

        $first = $this->lifecycle->ensure($event);
        $second = $this->lifecycle->ensure($event);

        $this->assertSame(OperationsStatus::Draft, $first->status);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, EventOperation::query()->where('event_id', $event->id)->count());
    }

    public function test_invalid_transition_from_draft_to_active_is_rejected(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->expectException(ValidationException::class);

        $this->lifecycle->transition($event, OperationsStatus::Active, $owner);
    }

    public function test_draft_can_move_to_preparing(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $operation = $this->lifecycle->transition($event, OperationsStatus::Preparing, $owner);

        $this->assertSame(OperationsStatus::Preparing, $operation->status);
    }

    public function test_ready_transition_is_blocked_when_readiness_has_blockers(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->lifecycle->transition($event, OperationsStatus::Preparing, $owner);

        // No active staff => staff_coverage is NotReady, which blocks readiness.
        $this->expectException(ValidationException::class);

        $this->lifecycle->transition($event, OperationsStatus::Ready, $owner);
    }

    public function test_ready_transition_can_be_overridden_with_a_reason(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->lifecycle->transition($event, OperationsStatus::Preparing, $owner);

        $operation = $this->lifecycle->transition($event, OperationsStatus::Ready, $owner, [
            'override' => true,
            'reason' => 'Staf menyusul, venue sudah siap.',
        ]);

        $this->assertSame(OperationsStatus::Ready, $operation->status);
    }

    public function test_override_requires_a_reason(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->lifecycle->transition($event, OperationsStatus::Preparing, $owner);

        $this->expectException(ValidationException::class);

        $this->lifecycle->transition($event, OperationsStatus::Ready, $owner, ['override' => true]);
    }

    public function test_active_requires_full_readiness_without_override(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->lifecycle->transition($event, OperationsStatus::Preparing, $owner);
        $this->lifecycle->transition($event, OperationsStatus::Ready, $owner, [
            'override' => true,
            'reason' => 'Uji coba.',
        ]);

        $this->expectException(ValidationException::class);

        $this->lifecycle->transition($event, OperationsStatus::Active, $owner);
    }

    public function test_activation_with_override_sets_activated_at(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->lifecycle->transition($event, OperationsStatus::Preparing, $owner);
        $this->lifecycle->transition($event, OperationsStatus::Ready, $owner, [
            'override' => true,
            'reason' => 'Uji coba.',
        ]);
        $operation = $this->lifecycle->transition($event, OperationsStatus::Active, $owner, [
            'override' => true,
            'reason' => 'Mulai lebih awal.',
        ]);

        $this->assertSame(OperationsStatus::Active, $operation->status);
        $this->assertNotNull($operation->activated_at);
    }

    public function test_serious_open_incident_blocks_closure(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $staff = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->lifecycle->transition($event, OperationsStatus::Preparing, $owner);
        $this->lifecycle->transition($event, OperationsStatus::Ready, $owner, ['override' => true, 'reason' => 'x']);
        $this->lifecycle->transition($event, OperationsStatus::Active, $owner, ['override' => true, 'reason' => 'x']);

        EventIncident::create([
            'event_id' => $event->id,
            'title' => 'Listrik padam',
            'category' => 'technical',
            'severity' => IncidentSeverity::Critical->value,
            'status' => IncidentStatus::Open->value,
            'reported_by' => $staff->user_id,
            'reported_at' => now(),
        ]);

        $review = $this->lifecycle->closureReview($event);

        $this->assertFalse($review['can_close']);
        $this->assertSame(1, $review['summary']['serious_open_incidents']);

        $this->expectException(ValidationException::class);

        $this->lifecycle->transition($event, OperationsStatus::Completed, $owner);
    }

    public function test_closure_override_stores_snapshot_and_notes(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->lifecycle->transition($event, OperationsStatus::Preparing, $owner);
        $this->lifecycle->transition($event, OperationsStatus::Ready, $owner, ['override' => true, 'reason' => 'x']);
        $this->lifecycle->transition($event, OperationsStatus::Active, $owner, ['override' => true, 'reason' => 'x']);

        EventTask::create([
            'event_id' => $event->id,
            'title' => 'Tugas kritis tersisa',
            'priority' => TaskPriority::Critical->value,
            'status' => TaskStatus::Open->value,
        ]);

        $operation = $this->lifecycle->transition($event, OperationsStatus::Completed, $owner, [
            'override' => true,
            'reason' => 'Ditutup sesuai instruksi klien.',
            'notes' => 'Acara selesai lebih cepat.',
        ]);

        $this->assertSame(OperationsStatus::Completed, $operation->status);
        $this->assertNotNull($operation->completed_at);
        $this->assertSame($owner->id, $operation->closed_by);
        $this->assertSame('Acara selesai lebih cepat.', $operation->closure_notes);
        $this->assertIsArray($operation->closure_snapshot);
        $this->assertSame(1, $operation->closure_snapshot['summary']['critical_open_tasks']);
    }

    public function test_completed_operation_can_be_reopened_by_close_authority(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        EventOperation::create(['event_id' => $event->id, 'status' => OperationsStatus::Completed->value, 'completed_at' => now()]);

        $operation = $this->lifecycle->transition($event, OperationsStatus::Active, $owner, [
            'override' => true,
            'reason' => 'Koreksi data penutupan.',
        ]);

        $this->assertSame(OperationsStatus::Active, $operation->status);
    }

    public function test_staff_without_operations_manage_cannot_transition(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $staff = $this->staffOf($org, $event, EventStaffRole::Observer);

        $this->expectException(HttpException::class);

        $this->lifecycle->transition($event, OperationsStatus::Preparing, $staff->user);
    }

    public function test_cancelled_operation_is_terminal(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $operation = $this->lifecycle->transition($event, OperationsStatus::Cancelled, $owner);

        $this->assertTrue($operation->status->isTerminal());

        $this->expectException(ValidationException::class);

        $this->lifecycle->transition($event, OperationsStatus::Preparing, $owner);
    }
}
