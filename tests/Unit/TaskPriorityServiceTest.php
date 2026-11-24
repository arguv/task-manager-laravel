<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Task;
use App\Services\TaskPriorityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TaskPriorityServiceTest extends TestCase
{
    use RefreshDatabase;

    private TaskPriorityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TaskPriorityService::class);
    }

    public function test_scope_in_group_distinguishes_null_and_project(): void
    {
        $p = Project::factory()->create();
        $n = Task::factory()->create();
        $t = Task::factory()->forProject($p)->create();

        $this->assertSame([$n->id], Task::inGroup(null)->pluck('id')->all());
        $this->assertSame([$t->id], Task::inGroup($p->id)->pluck('id')->all());
    }

    public function test_create_starts_at_one_then_increments(): void
    {
        $this->assertSame(1, $this->service->create(['name' => 'a'])->priority);
        $this->assertSame(2, $this->service->create(['name' => 'b'])->priority);
    }

    public function test_create_uses_max_plus_one_even_with_existing_gap(): void
    {
        Task::factory()->create(['priority' => 7]);

        $this->assertSame(8, $this->service->create(['name' => 'x'])->priority);
    }

    public function test_delete_decrements_only_higher_priorities_in_group(): void
    {
        $p = Project::factory()->create();
        $a = $this->service->create(['name' => 'a']);
        $b = $this->service->create(['name' => 'b']);
        $c = $this->service->create(['name' => 'c']);
        $other = $this->service->create(['name' => 'o', 'project_id' => $p->id]);

        $this->service->delete($b);

        $this->assertSame(1, $a->fresh()->priority);
        $this->assertSame(2, $c->fresh()->priority);
        $this->assertSame(1, $other->fresh()->priority);
        $this->assertNull(Task::find($b->id));
    }

    public function test_reorder_returns_tasks_sorted_by_new_priority(): void
    {
        $a = $this->service->create(['name' => 'a']);
        $b = $this->service->create(['name' => 'b']);

        $result = $this->service->reorder(null, [$b->id, $a->id]);

        $this->assertSame([$b->id, $a->id], $result->pluck('id')->all());
        $this->assertSame([1, 2], $result->pluck('priority')->all());
    }

    public function test_reorder_failure_leaves_priorities_untouched(): void
    {
        $a = $this->service->create(['name' => 'a']);
        $b = $this->service->create(['name' => 'b']);

        try {
            $this->service->reorder(null, [$b->id]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ids', $e->errors());
        }

        $this->assertSame(1, $a->fresh()->priority);
        $this->assertSame(2, $b->fresh()->priority);
    }

    public function test_update_with_same_integer_project_does_not_move_task(): void
    {
        $p = Project::factory()->create();
        $a = $this->service->create(['name' => 'a', 'project_id' => $p->id]);
        $b = $this->service->create(['name' => 'b', 'project_id' => $p->id]);

        $this->service->update($a->fresh(), ['name' => 'a2', 'project_id' => $p->id]);

        $this->assertSame(1, $a->fresh()->priority);
        $this->assertSame(2, $b->fresh()->priority);
    }
}
