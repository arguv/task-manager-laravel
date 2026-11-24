# What was built and why

This document explains the application features and the reasoning behind each piece of code. It covers the product, not the Docker setup (see [README.md](README.md) for that).

## The goal

A small task manager where every task has a **priority** (#1 is the most important). Tasks can optionally belong to a **project**. The user changes priorities by dragging tasks, and can filter the list by project.

The one rule that shapes most of the code: **priorities are always 1, 2, 3 ... N with no gaps and no duplicates, separately inside each project** (tasks without a project form their own group).

## Data model (migrations)

| Migration | What it creates | Why |
| --- | --- | --- |
| `2025_01_01_000010_create_projects_table` | `projects` with `name` (unique) | Lets tasks be grouped. A unique name stops two identical projects showing up in the dropdown. |
| `2025_01_01_000020_create_tasks_table` | `tasks` with `name`, `priority`, nullable `project_id`, timestamps | `project_id` is nullable so a task can exist without a project. The foreign key uses `nullOnDelete`, so deleting a project never deletes tasks; they fall back to "No project". The timestamps give the created/updated dates shown for each task. |

The index on `(project_id, priority)` speeds up the main query: "give me this group's tasks in priority order".

The index is deliberately **not unique**. A unique index would fail during a reorder, because two rows briefly share a number while priorities are being shuffled. Correctness is kept by `TaskPriorityService` instead (see below).

The default Laravel `users`, `cache` and `jobs` migrations are still present because the framework expects them (sessions, cache and queue use the database). They are not part of the feature.

## Models

- **`Project`** has many tasks.
- **`Task`** belongs to a project. It has a `inGroup($projectId)` scope. A `null` id means "the No project group", which normally needs a different SQL condition (`IS NULL` instead of `= id`). Having it in one place stops that detail leaking into every query.

## The core: `TaskPriorityService`

All priority changes go through this class, so the 1..N rule lives in one place. Every method runs in a database transaction, so a failure part-way never leaves half-updated priorities.

- **`create`** puts a new task at the end of its group (max priority + 1). The group's rows are locked first so two people adding a task at the same moment don't both get the same number.
- **`update`** changes the name. If the project changes, the task leaves its old group (the gap it leaves is closed) and joins the end of the new group. If the project is unchanged, nothing moves. A string `"3"` from the form is compared as the integer `3`, so saving without changing the project is not treated as a move.
- **`delete`** removes the task and shifts every lower task up by one, so there is no hole (e.g. #1, #3 becomes #1, #2).
- **`reorder`** takes the list of task ids in their new order and rewrites priorities as 1..N. It first checks that the ids are exactly the tasks currently in the group. If someone else added or deleted a task meanwhile, it rejects the request with "The list is out of date. Reload the page" instead of silently corrupting the order. Only rows whose number actually changed are saved.

## Validation (Form Requests)

- `StoreTaskRequest` / `UpdateTaskRequest`: name required, at most 255 characters; project optional but must exist.
- `StoreProjectRequest`: name required, at most 255 characters, unique.
- `ReorderTasksRequest`: `ids` must be a non-empty list of distinct, existing task ids.

Priority is intentionally **not** accepted from the user on create or edit. It is computed by the service, so a user cannot create duplicates or gaps by typing a number.

## Controllers and routes

- **`TaskController`**
  - `index` renders the list, grouped by project. The filter value is checked (`all`, `none` or an existing project id); anything else falls back to `all` so a hand-edited URL never errors.
  - `store`, `update`, `destroy` are thin: validate, call the service, redirect with a status message.
  - `reorder` is the JSON endpoint used by drag and drop. It returns the new priorities so the page can update its numbers.
- **`ProjectController::store`** creates a project and redirects to the list filtered on it.
- **Routes** (`routes/web.php`): `/` redirects to `/tasks`. The `tasks/reorder` route is declared before the resource routes, otherwise `reorder` would be mistaken for a task id.

## The user interface

- **List grouped by project**: one list per project, plus a "No project" list. Each task shows its `#priority`, name and dates, with edit and delete actions. Delete asks for confirmation.
- **Project filter**: a dropdown with All / No project / each project. It submits on change (a button appears if JavaScript is off). The selected filter is remembered after creating a task, and the create form pre-selects the filtered project.
- **Create forms**: one for tasks (name plus optional project) and one for projects.
- **Edit page**: change the name and move the task to another project.
- **Drag and drop** (`resources/js/app.js`, using SortableJS): tasks are dragged by a handle. When dropped, the new order of ids is sent to `tasks/reorder`. On success the `#n` labels update with no page reload. On failure (for example, a stale list) an error is shown and the page reloads after a moment so the screen matches the database again. Dragging works only within one group, since priorities are per project.

## Demo data (seeders)

`ProjectSeeder` and `TaskSeeder` create two projects and eight tasks, some without a project. The task seeder uses `TaskPriorityService` rather than inserting rows directly, so the demo data also follows the 1..N rule. Run with `php artisan migrate --seed`.

## Tests

- **Feature tests**: task create/edit/delete, project creation, filtering, priority behaviour and the reorder endpoint (including a stale list being rejected).
- **Unit tests**: `TaskPriorityService` directly, covering gaps closing after delete, moving between groups and the no-project group.

They run against a separate MySQL database (`testing`) so they never touch real data.

## Known limitation

There is no login. Anyone who can open the site can change all tasks. See the note at the end of the README.
