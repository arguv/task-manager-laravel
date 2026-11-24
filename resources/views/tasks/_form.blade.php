{{-- Expects $projects, optional $task, $selectedProject, $submit --}}
<div class="field">
    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="{{ old('name', $task->name ?? '') }}" maxlength="255" required>
</div>
<div class="field">
    <label for="project_id">Project</label>
    <select id="project_id" name="project_id">
        <option value="">No project</option>
        @foreach ($projects as $project)
            <option value="{{ $project->id }}" @selected((string) old('project_id', $selectedProject ?? '') === (string) $project->id)>{{ $project->name }}</option>
        @endforeach
    </select>
</div>
<button type="submit" class="btn btn-primary">{{ $submit }}</button>
