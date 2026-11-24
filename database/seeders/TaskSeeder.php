<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Services\TaskPriorityService;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(TaskPriorityService $priorities): void
    {
        $website = Project::where('name', 'Website redesign')->value('id');
        $mobile = Project::where('name', 'Mobile app')->value('id');

        $tasks = [
            [null, 'Buy groceries'],
            [null, 'Book dentist appointment'],
            [null, 'Pay electricity bill'],
            [$website, 'Draft new homepage layout'],
            [$website, 'Review brand colours'],
            [$website, 'Publish to staging'],
            [$mobile, 'Set up push notifications'],
            [$mobile, 'Fix login screen bug'],
        ];

        // Going through the service guarantees contiguous per-group priorities.
        foreach ($tasks as [$projectId, $name]) {
            $priorities->create(['name' => $name, 'project_id' => $projectId]);
        }
    }
}
