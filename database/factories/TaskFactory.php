<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => null,
            'name' => fake()->sentence(3),
            'priority' => 1, // use forProject()/tests or TaskPriorityService for contiguous numbering
        ];
    }

    public function forProject(Project|int|null $project): static
    {
        return $this->state(fn () => ['project_id' => $project instanceof Project ? $project->id : $project]);
    }
}
