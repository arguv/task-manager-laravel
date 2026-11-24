<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskPriorityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private TaskPriorityService $priorities) {}

    public function index(Request $request): View
    {
        $projects = Project::orderBy('name')->get();
        $filter = $this->resolveFilter($request->query('project'), $projects);

        // Groups to render: "No project" (null key) and/or projects, each with tasks by priority.
        $groups = collect();
        if ($filter === 'all' || $filter === 'none') {
            $groups->push(['project' => null, 'tasks' => Task::inGroup(null)->orderBy('priority')->get()]);
        }
        foreach ($projects as $project) {
            if ($filter === 'all' || (int) $filter === $project->id) {
                $groups->push(['project' => $project, 'tasks' => $project->tasks()->orderBy('priority')->get()]);
            }
        }

        return view('tasks.index', compact('projects', 'groups', 'filter'));
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $task = $this->priorities->create($request->validated());

        return redirect()
            ->route('tasks.index', ['project' => $request->input('filter', 'all')])
            ->with('status', "Task \"{$task->name}\" created.");
    }

    public function edit(Task $task): View
    {
        return view('tasks.edit', [
            'task' => $task,
            'projects' => Project::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->priorities->update($task, $request->validated());

        return redirect()->route('tasks.index')->with('status', 'Task updated.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->priorities->delete($task);

        return back()->with('status', 'Task deleted.');
    }

    public function reorder(ReorderTasksRequest $request): JsonResponse
    {
        $tasks = $this->priorities->reorder(
            $request->integer('project_id') ?: null,
            $request->validated('ids'),
        );

        return response()->json([
            'data' => $tasks->map->only(['id', 'priority'])->all(),
        ]);
    }

    /** "all" | "none" | existing project id; anything else falls back to "all". */
    private function resolveFilter(mixed $value, $projects): string
    {
        if ($value === 'none') {
            return 'none';
        }

        if (is_string($value) && ctype_digit($value) && $projects->contains('id', (int) $value)) {
            return $value;
        }

        return 'all';
    }
}
