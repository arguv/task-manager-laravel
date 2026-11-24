<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tasks');

// Must be declared before the resource so "reorder" is not captured as {task}.
Route::patch('tasks/reorder', [TaskController::class, 'reorder'])->name('tasks.reorder');
Route::resource('tasks', TaskController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);

Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
