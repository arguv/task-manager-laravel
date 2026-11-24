<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPriorityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,int> name => priority for a group, ordered by priority */
    private function group(?Project $project): array
    {
        return Task::where('project_id', $project?->id)->orderBy('priority')->pluck('priority', 'name')->all();
    }

    private function add(string $name, ?Project $project = null): void
    {
        $this->post(route('tasks.store'), ['name' => $name, 'project_id' => $project?->id])->assertSessionHasNoErrors();
    }

    private function task(string $name): Task
    {
        return Task::where('name', $name)->firstOrFail();
    }

    public function test_priorities_increment_within_a_group(): void
    {
        $this->add('a');
        $this->add('b');
        $this->add('c');

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $this->group(null));
    }

    public function test_groups_are_numbered_independently(): void
    {
        $p1 = Project::factory()->create();
        $p2 = Project::factory()->create();

        $this->add('n1');
        $this->add('p1a', $p1);
        $this->add('n2');
        $this->add('p1b', $p1);
        $this->add('p2a', $p2);

        $this->assertSame(['n1' => 1, 'n2' => 2], $this->group(null));
        $this->assertSame(['p1a' => 1, 'p1b' => 2], $this->group($p1));
        $this->assertSame(['p2a' => 1], $this->group($p2));
    }

    public function test_delete_first_middle_last_and_only_keep_contiguous(): void
    {
        foreach (['a', 'b', 'c', 'd'] as $n) {
            $this->add($n);
        }

        $this->delete(route('tasks.destroy', $this->task('a')));
        $this->assertSame(['b' => 1, 'c' => 2, 'd' => 3], $this->group(null));

        $this->delete(route('tasks.destroy', $this->task('c')));
        $this->assertSame(['b' => 1, 'd' => 2], $this->group(null));

        $this->delete(route('tasks.destroy', $this->task('d')));
        $this->assertSame(['b' => 1], $this->group(null));

        $this->delete(route('tasks.destroy', $this->task('b')));
        $this->assertSame([], $this->group(null));

        $this->add('fresh');
        $this->assertSame(['fresh' => 1], $this->group(null));
    }

    public function test_delete_does_not_touch_other_groups(): void
    {
        $p = Project::factory()->create();
        $this->add('n1');
        $this->add('n2');
        $this->add('p1', $p);
        $this->add('p2', $p);

        $this->delete(route('tasks.destroy', $this->task('n1')));

        $this->assertSame(['n2' => 1], $this->group(null));
        $this->assertSame(['p1' => 1, 'p2' => 2], $this->group($p));
    }

    /** Regression: deleting must close the gap (R3), and later creates continue the sequence. */
    public function test_regression_gap_is_closed_after_delete_then_create_continues_sequence(): void
    {
        foreach (['a', 'b', 'c'] as $n) {
            $this->add($n);
        }
        $this->delete(route('tasks.destroy', $this->task('b')));
        $this->add('d');

        $this->assertSame(['a' => 1, 'c' => 2, 'd' => 3], $this->group(null));
    }

    public function test_moving_task_to_another_project_closes_gap_and_appends(): void
    {
        $p = Project::factory()->create();
        foreach (['a', 'b', 'c'] as $n) {
            $this->add($n);
        }
        $this->add('x', $p);

        $this->put(route('tasks.update', $this->task('b')), ['name' => 'b', 'project_id' => $p->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(['a' => 1, 'c' => 2], $this->group(null));
        $this->assertSame(['x' => 1, 'b' => 2], $this->group($p));
    }

    public function test_moving_task_to_empty_project_gets_priority_one(): void
    {
        $p = Project::factory()->create();
        $this->add('a');

        $this->put(route('tasks.update', $this->task('a')), ['name' => 'a', 'project_id' => $p->id]);

        $this->assertSame(['a' => 1], $this->group($p));
        $this->assertSame([], $this->group(null));
    }

    public function test_moving_task_from_project_to_no_project(): void
    {
        $p = Project::factory()->create();
        $this->add('n');
        $this->add('a', $p);
        $this->add('b', $p);

        $this->put(route('tasks.update', $this->task('a')), ['name' => 'a', 'project_id' => '']);

        $this->assertSame(['n' => 1, 'a' => 2], $this->group(null));
        $this->assertSame(['b' => 1], $this->group($p));
    }

    public function test_moving_between_two_projects(): void
    {
        $p1 = Project::factory()->create();
        $p2 = Project::factory()->create();
        $this->add('a', $p1);
        $this->add('b', $p1);
        $this->add('c', $p2);

        $this->put(route('tasks.update', $this->task('a')), ['name' => 'a', 'project_id' => $p2->id]);

        $this->assertSame(['b' => 1], $this->group($p1));
        $this->assertSame(['c' => 1, 'a' => 2], $this->group($p2));
    }

    /**
     * Browser edit forms submit project_id as a string; editing a task without changing its
     * project must not alter its priority.
     */
    public function test_editing_task_with_unchanged_project_keeps_priority_form_post(): void
    {
        $p = Project::factory()->create();
        $this->add('a', $p);
        $this->add('b', $p);
        $this->add('c', $p);

        $this->put(route('tasks.update', $this->task('a')), ['name' => 'a renamed', 'project_id' => (string) $p->id]);

        $this->assertSame(['a renamed' => 1, 'b' => 2, 'c' => 3], $this->group($p));
    }

    public function test_editing_task_with_unchanged_project_keeps_priority_json(): void
    {
        $p = Project::factory()->create();
        $this->add('a', $p);
        $this->add('b', $p);

        $this->putJson(route('tasks.update', $this->task('a')), ['name' => 'a2', 'project_id' => $p->id]);

        $this->assertSame(['a2' => 1, 'b' => 2], $this->group($p));
    }

    public function test_editing_no_project_task_keeps_priority(): void
    {
        $this->add('a');
        $this->add('b');

        $this->put(route('tasks.update', $this->task('a')), ['name' => 'a2', 'project_id' => '']);

        $this->assertSame(['a2' => 1, 'b' => 2], $this->group(null));
    }
}
