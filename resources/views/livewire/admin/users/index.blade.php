<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Users</h1>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Name</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Email</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Role</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Job Title</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-600">Rate (£/hr)</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-600">Capacity (hrs)</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">Active</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($users as $user)
                    @if($editingId === $user->id)
                        <tr class="bg-blue-50">
                            <td class="px-4 py-2" colspan="8">
                                <div class="grid grid-cols-6 gap-3 items-end">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                                        <input wire:model="editName" type="text" class="w-full border-gray-300 rounded text-sm px-2 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Role</label>
                                        <select wire:model="editRole" class="w-full border-gray-300 rounded text-sm px-2 py-1.5">
                                            @foreach($roles as $role)
                                                <option value="{{ $role->value }}">{{ ucfirst($role->value) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Job Title</label>
                                        <input wire:model="editRoleTitle" type="text" class="w-full border-gray-300 rounded text-sm px-2 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Rate (£/hr)</label>
                                        <input wire:model="editDefaultRate" type="number" step="0.01" min="0" class="w-full border-gray-300 rounded text-sm px-2 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Capacity (hrs)</label>
                                        <input wire:model="editWeeklyCapacity" type="number" step="0.5" min="0" max="168" class="w-full border-gray-300 rounded text-sm px-2 py-1.5">
                                    </div>
                                    <div class="flex items-center gap-3 pb-1">
                                        <label class="flex items-center gap-1.5 text-xs">
                                            <input wire:model="editIsActive" type="checkbox" class="rounded"> Active
                                        </label>
                                        <label class="flex items-center gap-1.5 text-xs">
                                            <input wire:model="editIsContractor" type="checkbox" class="rounded"> Contractor
                                        </label>
                                    </div>
                                </div>
                                @error('editName')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                                @error('editDefaultRate')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                                <div class="flex gap-2 mt-2">
                                    <button wire:click="save" class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">Save</button>
                                    <button wire:click="cancel" class="px-3 py-1.5 bg-white border border-gray-300 text-xs rounded hover:bg-gray-50">Cancel</button>
                                </div>
                            </td>
                        </tr>
                    @else
                        <tr class="{{ $user->is_active ? '' : 'opacity-50' }}">
                            <td class="px-4 py-3">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $user->role->value === 'admin' ? 'bg-red-100 text-red-700' : ($user->role->value === 'manager' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600') }}">
                                    {{ ucfirst($user->role->value) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $user->role_title ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $user->default_hourly_rate !== null ? '£'.number_format((float)$user->default_hourly_rate, 2) : '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $user->weekly_capacity_hours }}h</td>
                            <td class="px-4 py-3 text-center">
                                @if($user->is_active)
                                    <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
                                @else
                                    <span class="inline-block w-2 h-2 rounded-full bg-gray-300"></span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="edit({{ $user->id }})" class="text-sm text-blue-600 hover:underline">Edit</button>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>
