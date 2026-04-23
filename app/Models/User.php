<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property Role $role
 * @property Collection<int, Project> $projects
 * @property Collection<int, TimeEntry> $timeEntries
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'google_sub',
        'email',
        'name',
        'role',
        'role_title',
        'is_contractor',
        'default_hourly_rate',
        'weekly_capacity_hours',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'is_contractor' => 'boolean',
            'is_active' => 'boolean',
            'default_hourly_rate' => 'decimal:2',
            'weekly_capacity_hours' => 'decimal:2',
            'last_login_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withPivot(['hourly_rate_override']);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isManager(): bool
    {
        return $this->role === Role::Manager || $this->role === Role::Admin;
    }
}
