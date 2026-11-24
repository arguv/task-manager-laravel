<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keeps priorities contiguous (1..N) inside each group (= project_id, NULL included).
 */
class TaskPriorityService
{
    /** Create a task at the end of its group. */
    public function create(array $data): Task
    {
        return DB::transaction(function () use ($data) {
            $projectId = $this->normalizeProjectId($data['project_id'] ?? null);

            return Task::create([
                'name' => $data['name'],
                'project_id' => $projectId,
                'priority' => $this->nextPriority($projectId),
            ]);
        });
    }

    /** Update the name; when the project changes, move the task to the end of the new group. */
    public function update(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $task->name = $data['name'];

            // Form input arrives as a string; compare normalized ids (int|null) so an unchanged project is a no-op.
            $newProjectId = $this->normalizeProjectId($data['project_id'] ?? null);

            if (array_key_exists('project_id', $data) && $newProjectId !== $task->project_id) {
                $this->closeGap($task->project_id, $task->priority);
                $task->project_id = $newProjectId;
                $task->priority = $this->nextPriority($newProjectId);
            }

            $task->save();

            return $task;
        });
    }

    public function delete(Task $task): void
    {
        DB::transaction(function () use ($task) {
            $task->delete();
            $this->closeGap($task->project_id, $task->priority);
        });
    }

    /**
     * Rewrite the group's priorities to match $ids (top first).
     * $ids must be exactly the set of task ids in the group.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Task>
     */
    public function reorder(?int $projectId, array $ids): Collection
    {
        return DB::transaction(function () use ($projectId, $ids) {
            $tasks = Task::inGroup($projectId)->orderBy('priority')->lockForUpdate()->get()->keyBy('id');

            if ($tasks->count() !== count($ids) || $tasks->keys()->diff($ids)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'ids' => 'The list is out of date. Reload the page and try again.',
                ]);
            }

            foreach (array_values($ids) as $index => $id) {
                $task = $tasks[$id];
                $task->priority = $index + 1;
                if ($task->isDirty('priority')) {
                    $task->save();
                }
            }

            return $tasks->sortBy('priority')->values();
        });
    }

    private function normalizeProjectId(int|string|null $projectId): ?int
    {
        return ($projectId === null || $projectId === '') ? null : (int) $projectId;
    }

    private function nextPriority(?int $projectId): int
    {
        // Lock the group's rows so concurrent creates serialize.
        Task::inGroup($projectId)->lockForUpdate()->pluck('id');

        return (int) Task::inGroup($projectId)->max('priority') + 1;
    }

    private function closeGap(?int $projectId, int $removedPriority): void
    {
        Task::inGroup($projectId)->where('priority', '>', $removedPriority)->decrement('priority');
    }
}
