<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property bool $is_archived
 * @property Collection<int, Project> $projects
 */
class Client extends Model
{
    protected $fillable = ['name', 'code', 'is_archived'];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<Project, $this> */
    public function activeProjects(): HasMany
    {
        return $this->hasMany(Project::class)->where('is_archived', false);
    }
}
