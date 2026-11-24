@extends('layouts.app')

@section('content')
    <section class="card">
        <form method="GET" action="{{ route('tasks.index') }}" class="inline-form">
            <label for="filter">Project</label>
            <select id="filter" name="project" onchange="this.form.submit()">
                <option value="all" @selected($filter === 'all')>All</option>
                <option value="none" @selected($filter === 'none')>No project</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected($filter === (string) $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
            <noscript><button class="btn">Filter</button></noscript>
        </form>
    </section>

    <div class="grid">
        <section class="card">
            <h2>New task</h2>
            <form method="POST" action="{{ route('tasks.store') }}">
                @csrf
                <input type="hidden" name="filter" value="{{ $filter }}">
                @include('tasks._form', [
                    'projects' => $projects,
                    'selectedProject' => ctype_digit($filter) ? $filter : '',
                    'submit' => 'Add task',
                ])
            </form>
        </section>

        <section class="card">
            <h2>New project</h2>
            <form method="POST" action="{{ route('projects.store') }}">
                @csrf
                <div class="field">
                    <label for="project_name">Name</label>
                    <input type="text" id="project_name" name="name" maxlength="255" required>
                </div>
                <button type="submit" class="btn btn-primary">Add project</button>
            </form>
        </section>
    </div>

    @foreach ($groups as $group)
        <section class="card">
            <h2>{{ $group['project']?->name ?? 'No project' }}</h2>

            <ul class="tasks sortable" data-project-id="{{ $group['project']?->id }}" data-url="{{ route('tasks.reorder') }}">
                @forelse ($group['tasks'] as $task)
                    <li data-id="{{ $task->id }}">
                        <span class="handle" title="Drag to reorder" aria-hidden="true">&#8942;&#8942;</span>
                        <span class="priority">#{{ $task->priority }}</span>
                        <span class="task-name">{{ $task->name }}</span>
                        <span class="actions">
                            <a href="{{ route('tasks.edit', $task) }}">Edit</a>
                            <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Delete this task?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="link-danger">Delete</button>
                            </form>
                        </span>
                    </li>
                @empty
                    <li class="empty" data-empty>No tasks yet.</li>
                @endforelse
            </ul>
        </section>
    @endforeach
@endsection
