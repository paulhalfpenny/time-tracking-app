<?php

namespace App\Livewire\Admin\Clients;

use App\Models\Client;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public string $name = '';

    public string $code = '';

    public bool $showArchived = false;

    public ?int $editingId = null;

    public string $editName = '';

    public string $editCode = '';

    public function create(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20|unique:clients,code',
        ]);

        Client::create([
            'name' => $this->name,
            'code' => $this->code ?: null,
        ]);

        $this->name = '';
        $this->code = '';
    }

    public function edit(int $clientId): void
    {
        $client = Client::findOrFail($clientId);
        $this->editingId = $clientId;
        $this->editName = $client->name;
        $this->editCode = $client->code ?? '';
    }

    public function save(): void
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editCode' => 'nullable|string|max:20|unique:clients,code,'.$this->editingId,
        ]);

        Client::findOrFail((int) $this->editingId)->update([
            'name' => $this->editName,
            'code' => $this->editCode ?: null,
        ]);

        $this->editingId = null;
    }

    public function cancel(): void
    {
        $this->editingId = null;
    }

    public function toggleArchive(int $clientId): void
    {
        $client = Client::findOrFail($clientId);
        $client->update(['is_archived' => ! $client->is_archived]);
    }

    public function render(): View
    {
        $query = Client::orderBy('name');
        if (! $this->showArchived) {
            $query->where('is_archived', false);
        }

        return view('livewire.admin.clients.index', [
            'clients' => $query->get(),
        ]);
    }
}
