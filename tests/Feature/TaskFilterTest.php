<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Services\TaskPriorityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskFilterTest extends TestCase
{
    use RefreshDatabase;

    private Project $alpha;

    private Project $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = Project::factory()->create(['name' => 'Alpha']);
        $this->beta = Project::factory()->create(['name' => 'Beta']);
        $s = app(TaskPriorityService::class);
        $s->create(['name' => 'none-1']);
        $s->create(['name' => 'none-2']);
        $s->create(['name' => 'alpha-1', 'project_id' => $this->alpha->id]);
        $s->create(['name' => 'alpha-2', 'project_id' => $this->alpha->id]);
        $s->create(['name' => 'beta-1', 'project_id' => $this->beta->id]);
    }

    public function test_default_and_all_show_every_task(): void
    {
        foreach (['/tasks', '/tasks?project=all'] as $url) {
            $this->get($url)->assertOk()
                ->assertSeeInOrder(['none-1', 'none-2', 'alpha-1', 'alpha-2', 'beta-1']);
        }
    }

    public function test_none_shows_only_tasks_without_project(): void
    {
        $this->get('/tasks?project=none')->assertOk()
            ->assertSee('none-1')->assertSee('none-2')
            ->assertDontSee('alpha-1')->assertDontSee('beta-1');
    }

    public function test_project_id_shows_only_that_project(): void
    {
        $this->get('/tasks?project='.$this->alpha->id)->assertOk()
            ->assertSee('alpha-1')->assertSee('alpha-2')
            ->assertDontSee('none-1')->assertDontSee('beta-1');
    }

    public function test_tasks_are_ordered_by_priority_within_group(): void
    {
        Task::where('name', 'alpha-1')->update(['priority' => 2]);
        Task::where('name', 'alpha-2')->update(['priority' => 1]);

        $this->get('/tasks?project='.$this->alpha->id)->assertSeeInOrder(['alpha-2', 'alpha-1']);
    }

    public function test_priority_badges_are_per_group(): void
    {
        $html = $this->get('/tasks?project='.$this->beta->id)->getContent();

        $this->assertStringContainsString('#1', $html);
        $this->assertStringNotContainsString('#2', $html);
    }

    public function test_invalid_filters_fall_back_to_all(): void
    {
        foreach (['abc', '99999', '-1', '1.5', '', '1;DROP'] as $value) {
            $this->get('/tasks?project='.urlencode($value))->assertOk()
                ->assertSee('none-1')->assertSee('alpha-1')->assertSee('beta-1');
        }
        $this->get('/tasks?project[]=1')->assertOk()->assertSee('none-1')->assertSee('beta-1');
    }

    public function test_dropdown_lists_all_none_and_projects_with_selection(): void
    {
        $this->get('/tasks?project='.$this->beta->id)->assertOk()
            ->assertSee('All')->assertSee('No project')->assertSee('Alpha')
            ->assertSee('<option value="'.$this->beta->id.'" selected', false);
        $this->get('/tasks?project=none')->assertSee('<option value="none" selected', false);
    }

    public function test_empty_project_group_shows_empty_state(): void
    {
        $empty = Project::factory()->create(['name' => 'Empty']);

        $this->get('/tasks?project='.$empty->id)->assertOk()->assertSee('No tasks yet.');
    }
}
