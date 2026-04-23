<?php

namespace App\Livewire\Admin\Projects;

use App\Enums\BillingType;
use App\Models\Client;
use App\Models\Project;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public int|string $clientId = '';

    public string $code = '';

    public string $name = '';

    public string $billingType = 'hourly';

    public string $defaultRate = '';

    public function save(): void
    {
        $this->validate([
            'clientId' => 'required|exists:clients,id',
            'code' => 'required|string|max:50|unique:projects,code',
            'name' => 'required|string|max:255',
            'billingType' => 'required|in:hourly,fixed_fee,non_billable',
            'defaultRate' => 'nullable|numeric|min:0',
        ]);

        $project = Project::create([
            'client_id' => (int) $this->clientId,
            'code' => $this->code,
            'name' => $this->name,
            'billing_type' => BillingType::from($this->billingType),
            'default_hourly_rate' => $this->defaultRate !== '' ? (float) $this->defaultRate : null,
        ]);

        $this->redirect(route('admin.projects.edit', $project));
    }

    public function render(): View
    {
        return view('livewire.admin.projects.create', [
            'clients' => Client::where('is_archived', false)->orderBy('name')->get(),
            'billingTypes' => BillingType::cases(),
        ]);
    }
}
