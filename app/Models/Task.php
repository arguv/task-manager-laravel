<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'name', 'priority'];

    protected function casts(): array
    {
        return ['priority' => 'integer', 'project_id' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Tasks of one priority group; a null project id means the "No project" group. */
    public function scopeInGroup(Builder $query, ?int $projectId): Builder
    {
        return $projectId === null
            ? $query->whereNull('project_id')
            : $query->where('project_id', $projectId);
    }
}
