<?php

namespace App\Livewire\Admin\Projects;

use App\Models\Client;
use App\Models\Project;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showArchived = false;

    public function toggleArchive(int $projectId): void
    {
        $project = Project::findOrFail($projectId);
        $project->update(['is_archived' => ! $project->is_archived]);
    }

    public function render(): View
    {
        $query = Project::with('client')
            ->orderBy('name');

        if (! $this->showArchived) {
            $query->where('is_archived', false);
        }

        return view('livewire.admin.projects.index', [
            'projects' => $query->get(),
            'clients' => Client::where('is_archived', false)->orderBy('name')->get(),
        ]);
    }
}
