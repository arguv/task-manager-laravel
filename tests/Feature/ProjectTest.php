<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_project_and_redirect_to_its_filter(): void
    {
        $response = $this->post(route('projects.store'), ['name' => 'Website']);

        $project = Project::firstOrFail();
        $this->assertSame('Website', $project->name);
        $this->assertNotNull($project->created_at);
        $response->assertRedirect(route('tasks.index', ['project' => $project->id]));
    }

    public function test_project_name_validation(): void
    {
        Project::factory()->create(['name' => 'Taken']);

        $this->post(route('projects.store'), [])->assertSessionHasErrors('name');
        $this->post(route('projects.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('projects.store'), ['name' => str_repeat('a', 256)])->assertSessionHasErrors('name');
        $this->post(route('projects.store'), ['name' => 'Taken'])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('projects', 1);
    }

    public function test_new_project_appears_in_dropdown_and_task_form(): void
    {
        $this->post(route('projects.store'), ['name' => 'Visible']);

        $this->get(route('tasks.index'))->assertSee('Visible');
        $this->get(route('tasks.edit', Task::factory()->create()))->assertSee('Visible');
    }

    public function test_task_project_is_optional_and_relation_works(): void
    {
        $project = Project::factory()->create();
        $with = Task::factory()->forProject($project)->create();
        $without = Task::factory()->create();

        $this->assertTrue($with->project->is($project));
        $this->assertNull($without->project);
        $this->assertTrue($project->tasks->contains($with));
        $this->assertFalse($project->tasks->contains($without));
    }

    public function test_deleting_project_nulls_task_project(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->forProject($project)->create();

        $project->delete();

        $this->assertNull($task->fresh()->project_id);
    }
}
