<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Projects</h1>
        <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input wire:model.live="showArchived" type="checkbox" class="rounded"> Show archived
            </label>
            <a href="{{ route('admin.projects.create') }}"
               class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                + New project
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Code</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Project</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Client</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Billing</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-600">Rate</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($projects as $project)
                    <tr class="{{ $project->is_archived ? 'opacity-50' : '' }}">
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $project->code }}</td>
                        <td class="px-4 py-3 font-medium">{{ $project->name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $project->client->name }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $project->billing_type->value === 'non_billable' ? 'bg-gray-100 text-gray-500' : 'bg-blue-50 text-blue-700' }}">
                                {{ str_replace('_', ' ', $project->billing_type->value) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-700">
                            {{ $project->default_hourly_rate !== null ? '£'.number_format((float)$project->default_hourly_rate, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <a href="{{ route('admin.projects.edit', $project) }}" class="text-sm text-blue-600 hover:underline">Edit</a>
                            <button wire:click="toggleArchive({{ $project->id }})" class="text-sm text-gray-400 hover:text-gray-600 hover:underline">
                                {{ $project->is_archived ? 'Unarchive' : 'Archive' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">No projects yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
