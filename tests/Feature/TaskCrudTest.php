<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_task_list(): void
    {
        $this->get('/')->assertRedirect('/tasks');
    }

    public function test_index_renders_empty_state(): void
    {
        $this->get(route('tasks.index'))->assertOk()->assertSee('No tasks yet.');
    }

    public function test_create_persists_name_priority_and_timestamps(): void
    {
        $this->post(route('tasks.store'), ['name' => 'Write tests'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $task = Task::firstOrFail();
        $this->assertSame('Write tests', $task->name);
        $this->assertSame(1, $task->priority);
        $this->assertNull($task->project_id);
        $this->assertNotNull($task->created_at);
        $this->assertNotNull($task->updated_at);
    }

    public function test_create_with_project_assigns_project(): void
    {
        $project = Project::factory()->create();

        $this->post(route('tasks.store'), ['name' => 'A', 'project_id' => $project->id]);

        $this->assertDatabaseHas('tasks', ['name' => 'A', 'project_id' => $project->id, 'priority' => 1]);
    }

    public function test_create_redirects_back_to_current_filter(): void
    {
        $this->post(route('tasks.store'), ['name' => 'A', 'filter' => 'none'])
            ->assertRedirect(route('tasks.index', ['project' => 'none']));
    }

    public function test_client_supplied_priority_is_ignored_on_create(): void
    {
        $this->post(route('tasks.store'), ['name' => 'A', 'priority' => 99]);

        $this->assertSame(1, Task::firstOrFail()->priority);
    }

    public function test_name_is_trimmed(): void
    {
        $this->post(route('tasks.store'), ['name' => '  Padded  ']);

        $this->assertDatabaseHas('tasks', ['name' => 'Padded']);
    }

    public function test_create_validation_errors(): void
    {
        $this->post(route('tasks.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('tasks.store'), ['name' => '   '])->assertSessionHasErrors('name');
        $this->post(route('tasks.store'), [])->assertSessionHasErrors('name');
        $this->post(route('tasks.store'), ['name' => str_repeat('a', 256)])->assertSessionHasErrors('name');
        $this->post(route('tasks.store'), ['name' => 'ok', 'project_id' => 9999])->assertSessionHasErrors('project_id');
        $this->post(route('tasks.store'), ['name' => 'ok', 'project_id' => 'abc'])->assertSessionHasErrors('project_id');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_create_accepts_name_of_exactly_255_chars(): void
    {
        $this->post(route('tasks.store'), ['name' => str_repeat('a', 255)])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_name_is_escaped_in_output(): void
    {
        $this->post(route('tasks.store'), ['name' => '<script>alert(1)</script>']);

        $this->get(route('tasks.index'))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_edit_page_renders_task(): void
    {
        $task = Task::factory()->create(['name' => 'Editable']);

        $this->get(route('tasks.edit', $task))->assertOk()->assertSee('Editable');
    }

    public function test_edit_page_404_for_missing_task(): void
    {
        $this->get(route('tasks.edit', 12345))->assertNotFound();
    }

    public function test_update_changes_name_and_keeps_priority(): void
    {
        $this->post(route('tasks.store'), ['name' => 'One']);
        $this->post(route('tasks.store'), ['name' => 'Two']);
        $one = Task::where('name', 'One')->first();

        $this->put(route('tasks.update', $one), ['name' => 'Uno'])
            ->assertRedirect(route('tasks.index'));

        $this->assertDatabaseHas('tasks', ['id' => $one->id, 'name' => 'Uno', 'priority' => 1]);
        $this->assertDatabaseHas('tasks', ['name' => 'Two', 'priority' => 2]);
    }

    public function test_update_validation_errors(): void
    {
        $task = Task::factory()->create(['name' => 'Keep']);

        $this->put(route('tasks.update', $task), ['name' => ''])->assertSessionHasErrors('name');
        $this->put(route('tasks.update', $task), ['name' => 'x', 'project_id' => 9999])->assertSessionHasErrors('project_id');

        $this->assertSame('Keep', $task->fresh()->name);
    }

    public function test_update_404_for_missing_task(): void
    {
        $this->put(route('tasks.update', 999), ['name' => 'x'])->assertNotFound();
    }

    public function test_destroy_deletes_task(): void
    {
        $task = Task::factory()->create();

        $this->delete(route('tasks.destroy', $task))->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_destroy_404_for_missing_task(): void
    {
        $this->delete(route('tasks.destroy', 999))->assertNotFound();
    }
}
