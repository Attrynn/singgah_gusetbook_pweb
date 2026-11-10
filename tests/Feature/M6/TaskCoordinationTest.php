<?php

namespace Tests\Feature\M6;

use App\Enums\EventStaffRole;
use App\Enums\OperationsStatus;
use App\Enums\OrganizationRole;
use App\Enums\TaskStatus;
use App\Models\EventOperation;
use App\Models\Organization;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\M6\Concerns\BuildsOperationsFixtures;
use Tests\TestCase;

class TaskCoordinationTest extends TestCase
{
    use BuildsOperationsFixtures, RefreshDatabase;

    private TaskService $tasks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tasks = app(TaskService::class);
    }

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_creating_a_task_requires_the_create_permission(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $observer = $this->staffOf($org, $event, EventStaffRole::Observer);

        $this->expectException(HttpException::class);

        $this->tasks->create($event, ['title' => 'Tugas'], $observer->user);
    }

    public function test_staff_without_assign_permission_cannot_assign_work_to_others(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $general = $this->staffOf($org, $event, EventStaffRole::GuestRelations);
        $other = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->expectException(ValidationException::class);

        $this->tasks->create($event, [
            'title' => 'Tugas',
            'assigned_membership_id' => $other->id,
        ], $general->user);
    }

    public function test_blocking_a_task_requires_a_reason(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $task = $this->tasks->create($event, ['title' => 'Tugas'], $owner);

        $this->expectException(ValidationException::class);

        $this->tasks->transition($event, $task, TaskStatus::Blocked, $owner);
    }

    public function test_completion_is_blocked_by_incomplete_checklist_items(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $task = $this->tasks->create($event, ['title' => 'Tugas'], $owner);
        $this->tasks->addChecklistItem($event, $task, 'Siapkan meja', $owner);
        $this->tasks->transition($event, $task, TaskStatus::InProgress, $owner);

        $this->expectException(ValidationException::class);

        $this->tasks->transition($event, $task->fresh(), TaskStatus::Completed, $owner);
    }

    public function test_completion_is_blocked_by_unfinished_dependencies(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $task = $this->tasks->create($event, ['title' => 'Tugas utama'], $owner);
        $dependency = $this->tasks->create($event, ['title' => 'Prasyarat'], $owner);

        $this->tasks->addDependency($event, $task, $dependency, $owner);
        $this->tasks->transition($event, $task, TaskStatus::InProgress, $owner);

        $this->expectException(ValidationException::class);

        $this->tasks->transition($event, $task->fresh(), TaskStatus::Completed, $owner);
    }

    public function test_manager_can_override_dependencies_with_a_reason(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $task = $this->tasks->create($event, ['title' => 'Tugas utama'], $owner);
        $dependency = $this->tasks->create($event, ['title' => 'Prasyarat'], $owner);

        $this->tasks->addDependency($event, $task, $dependency, $owner);
        $this->tasks->transition($event, $task, TaskStatus::InProgress, $owner);

        $completed = $this->tasks->transition(
            $event,
            $task->fresh(),
            TaskStatus::Completed,
            $owner,
            'Prasyarat dibatalkan klien',
            true,
        );

        $this->assertSame(TaskStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
    }

    public function test_override_without_a_reason_is_rejected(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $task = $this->tasks->create($event, ['title' => 'Tugas utama'], $owner);
        $dependency = $this->tasks->create($event, ['title' => 'Prasyarat'], $owner);

        $this->tasks->addDependency($event, $task, $dependency, $owner);
        $this->tasks->transition($event, $task, TaskStatus::InProgress, $owner);

        $this->expectException(ValidationException::class);

        $this->tasks->transition($event, $task->fresh(), TaskStatus::Completed, $owner, null, true);
    }

    public function test_dependency_cycles_are_rejected(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $a = $this->tasks->create($event, ['title' => 'A'], $owner);
        $b = $this->tasks->create($event, ['title' => 'B'], $owner);

        $this->tasks->addDependency($event, $a, $b, $owner);

        $this->expectException(ValidationException::class);

        $this->tasks->addDependency($event, $b, $a, $owner);
    }

    public function test_a_task_cannot_depend_on_itself(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $task = $this->tasks->create($event, ['title' => 'A'], $owner);

        $this->expectException(ValidationException::class);

        $this->tasks->addDependency($event, $task, $task, $owner);
    }

    public function test_task_visibility_hides_unrelated_work_from_general_staff(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $assigned = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);
        $unrelated = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $task = $this->tasks->create($event, [
            'title' => 'Tugas privat',
            'assigned_membership_id' => $assigned->id,
        ], $owner);

        $this->assertTrue($this->tasks->visibleTasks($event, $assigned->user)->whereKey($task->id)->exists());
        $this->assertFalse($this->tasks->visibleTasks($event, $unrelated->user)->whereKey($task->id)->exists());
    }

    public function test_overdue_flag_is_derived_from_due_date_and_status(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $task = $this->tasks->create($event, [
            'title' => 'Tugas',
            'due_at' => now()->subHour()->toDateTimeString(),
        ], $owner);

        $this->assertTrue($task->fresh()->isOverdue());

        $this->tasks->transition($event, $task->fresh(), TaskStatus::InProgress, $owner);
        $this->tasks->transition($event, $task->fresh(), TaskStatus::Completed, $owner);

        $this->assertFalse($task->fresh()->isOverdue());
    }

    public function test_new_tasks_are_rejected_after_operational_closure(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        EventOperation::create([
            'event_id' => $event->id,
            'status' => OperationsStatus::Completed->value,
            'completed_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        $this->tasks->create($event, ['title' => 'Tugas baru'], $owner);
    }

    public function test_task_from_another_event_is_not_found(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $other = $this->eventIn($org);
        $owner = $this->owner($org);
        $foreign = $this->tasks->create($other, ['title' => 'Lain'], $owner);

        $this->expectException(HttpException::class);

        $this->tasks->update($event, $foreign, ['title' => 'Hijack'], $owner);
    }

    public function test_commenting_on_an_invisible_task_is_not_found(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $outsider = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);
        $assignee = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $task = $this->tasks->create($event, [
            'title' => 'Tugas privat',
            'assigned_membership_id' => $assignee->id,
        ], $owner);

        $this->expectException(HttpException::class);

        $this->tasks->addComment($event, $task, 'Halo', $outsider->user);
    }

    public function test_checklist_cannot_be_modified_on_a_completed_task(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $task = $this->tasks->create($event, ['title' => 'Tugas'], $owner);
        $this->tasks->transition($event, $task, TaskStatus::InProgress, $owner);
        $this->tasks->transition($event, $task->fresh(), TaskStatus::Completed, $owner);

        $this->expectException(ValidationException::class);

        $this->tasks->addChecklistItem($event, $task->fresh(), 'Item baru', $owner);
    }
}
