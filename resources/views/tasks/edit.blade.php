@extends('layouts.app')

@section('title', 'Edit task')

@section('content')
    <section class="card">
        <h2>Edit task</h2>
        <p class="muted">Changing the project moves the task to the end of the new project's list.</p>
        <form method="POST" action="{{ route('tasks.update', $task) }}">
            @csrf
            @method('PUT')
            @include('tasks._form', [
                'projects' => $projects,
                'selectedProject' => $task->project_id,
                'submit' => 'Save',
            ])
            <a href="{{ route('tasks.index') }}" class="btn">Cancel</a>
        </form>
    </section>
@endsection
