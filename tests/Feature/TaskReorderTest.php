<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Services\TaskPriorityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskReorderTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<Task> created in order with priorities 1..n */
    private function seedGroup(?Project $project, int $count, string $prefix = 't'): array
    {
        $service = app(TaskPriorityService::class);
        $tasks = [];
        for ($i = 1; $i <= $count; $i++) {
            $tasks[] = $service->create(['name' => "$prefix$i", 'project_id' => $project?->id]);
        }

        return $tasks;
    }

    private function order(?Project $project): array
    {
        return Task::where('project_id', $project?->id)->orderBy('priority')->pluck('name')->all();
    }

    private function reorder(?Project $project, array $ids)
    {
        return $this->patchJson(route('tasks.reorder'), ['project_id' => $project?->id, 'ids' => $ids]);
    }

    public function test_reorder_no_project_group_persists_new_order(): void
    {
        [$a, $b, $c] = $this->seedGroup(null, 3);

        $this->reorder(null, [$c->id, $a->id, $b->id])
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => $c->id, 'priority' => 1],
                ['id' => $a->id, 'priority' => 2],
                ['id' => $b->id, 'priority' => 3],
            ]]);

        $this->assertSame(['t3', 't1', 't2'], $this->order(null));
    }

    public function test_reorder_project_group(): void
    {
        $p = Project::factory()->create();
        [$a, $b] = $this->seedGroup($p, 2);

        $this->reorder($p, [$b->id, $a->id])->assertOk();

        $this->assertSame(['t2', 't1'], $this->order($p));
    }

    public function test_reorder_does_not_affect_other_groups(): void
    {
        $p = Project::factory()->create();
        [$a, $b] = $this->seedGroup($p, 2, 'p');
        $this->seedGroup(null, 3, 'n');

        $this->reorder($p, [$b->id, $a->id])->assertOk();

        $this->assertSame(['n1', 'n2', 'n3'], $this->order(null));
        $this->assertSame(['p2', 'p1'], $this->order($p));
    }

    public function test_reorder_same_order_is_idempotent(): void
    {
        [$a, $b] = $this->seedGroup(null, 2);

        $this->reorder(null, [$a->id, $b->id])->assertOk();
        $this->reorder(null, [$a->id, $b->id])->assertOk();

        $this->assertSame(['t1', 't2'], $this->order(null));
    }

    public function test_reorder_single_task_group(): void
    {
        [$a] = $this->seedGroup(null, 1);

        $this->reorder(null, [$a->id])->assertOk();

        $this->assertSame(1, $a->fresh()->priority);
    }

    public function test_reorder_rewrites_drifted_priorities_to_one_through_n(): void
    {
        $a = Task::factory()->create(['name' => 'a', 'priority' => 5]);
        $b = Task::factory()->create(['name' => 'b', 'priority' => 9]);
        $c = Task::factory()->create(['name' => 'c', 'priority' => 9]);

        $this->reorder(null, [$b->id, $c->id, $a->id])->assertOk();

        $this->assertSame([1, 2, 3], Task::orderBy('priority')->pluck('priority')->all());
        $this->assertSame(['b', 'c', 'a'], $this->order(null));
    }

    public function test_reorder_rejects_task_from_another_group(): void
    {
        $p = Project::factory()->create();
        [$a, $b] = $this->seedGroup(null, 2, 'n');
        [$x] = $this->seedGroup($p, 1, 'p');

        $this->reorder(null, [$a->id, $b->id, $x->id])->assertStatus(422)->assertJsonValidationErrors('ids');
        $this->reorder(null, [$a->id, $x->id])->assertStatus(422)->assertJsonValidationErrors('ids');

        $this->assertSame(['n1', 'n2'], $this->order(null));
        $this->assertSame(['p1'], $this->order($p));
    }

    public function test_reorder_rejects_incomplete_set(): void
    {
        [$a, , $c] = $this->seedGroup(null, 3);

        $this->reorder(null, [$c->id, $a->id])->assertStatus(422)->assertJsonValidationErrors('ids');

        $this->assertSame(['t1', 't2', 't3'], $this->order(null));
    }

    public function test_reorder_rejects_missing_ids(): void
    {
        [$a] = $this->seedGroup(null, 1);

        $this->reorder(null, [$a->id, 99999])->assertStatus(422)->assertJsonValidationErrors('ids.1');
    }

    public function test_reorder_rejects_duplicate_ids(): void
    {
        [$a] = $this->seedGroup(null, 2);

        $this->reorder(null, [$a->id, $a->id])->assertStatus(422);

        $this->assertSame(['t1', 't2'], $this->order(null));
    }

    public function test_reorder_rejects_empty_and_malformed_payload(): void
    {
        $this->seedGroup(null, 2);

        $this->patchJson(route('tasks.reorder'), ['ids' => []])->assertStatus(422)->assertJsonValidationErrors('ids');
        $this->patchJson(route('tasks.reorder'), [])->assertStatus(422)->assertJsonValidationErrors('ids');
        $this->patchJson(route('tasks.reorder'), ['ids' => ['x']])->assertStatus(422)->assertJsonValidationErrors('ids.0');
        $this->patchJson(route('tasks.reorder'), ['ids' => 'nope'])->assertStatus(422);
    }

    public function test_reorder_rejects_unknown_project(): void
    {
        [$a] = $this->seedGroup(null, 1);

        $this->patchJson(route('tasks.reorder'), ['project_id' => 9999, 'ids' => [$a->id]])
            ->assertStatus(422)->assertJsonValidationErrors('project_id');
    }

    public function test_reorder_route_is_not_captured_by_task_resource(): void
    {
        $this->assertSame('/tasks/reorder', route('tasks.reorder', [], false));
        $this->patchJson('/tasks/reorder', [])->assertStatus(422);
    }
}
