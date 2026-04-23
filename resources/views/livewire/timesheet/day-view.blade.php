<div
    class="max-w-4xl mx-auto px-4 py-6"
    x-data="{}"
    @keydown.n.window="$wire.openNewModal()"
>
    {{-- Day header --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <button
                wire:click="previousWeek"
                class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 transition text-gray-500 hover:text-gray-800 shadow-sm"
                title="Previous week"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <button
                wire:click="nextWeek"
                class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 transition text-gray-500 hover:text-gray-800 shadow-sm"
                title="Next week"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
            <h2 class="text-lg font-semibold text-gray-800">
                {{ \Carbon\Carbon::parse($selectedDate)->format('l, j F Y') }}
            </h2>
        </div>
        <button
            wire:click="openNewModal"
            class="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition"
            title="New entry (N)"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Track time
        </button>
    </div>

    {{-- Week strip --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
        <div class="grid grid-cols-7 divide-x divide-gray-100">
            @foreach ($weekDays as $day)
                @php
                    $dateStr = $day->toDateString();
                    $isToday = $day->isToday();
                    $isSelected = $dateStr === $selectedDate;
                    $total = $dayTotals[$dateStr] ?? 0;
                @endphp
                <button
                    wire:click="selectDate('{{ $dateStr }}')"
                    class="flex flex-col items-center py-3 px-2 hover:bg-gray-50 transition {{ $isSelected ? 'bg-green-50' : '' }}"
                >
                    <span class="text-xs text-gray-500 uppercase tracking-wide">{{ $day->format('D') }}</span>
                    <span class="mt-1 w-8 h-8 flex items-center justify-center rounded-full text-sm font-medium
                        {{ $isToday ? 'bg-green-600 text-white' : ($isSelected ? 'text-green-700 font-semibold' : 'text-gray-700') }}">
                        {{ $day->format('j') }}
                    </span>
                    <span class="mt-1 text-xs {{ $total > 0 ? 'text-gray-600' : 'text-gray-300' }}">
                        {{ $total > 0 ? number_format($total, 1) : '–' }}
                    </span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Entries list --}}
    @if ($dayEntries->isEmpty())
        <div class="rounded-xl bg-gray-100 flex items-center justify-center px-8 text-center" style="min-height: 350px">
            <div>
                <p class="text-lg italic text-gray-500">"{{ $emptySong['song_name'] }}"</p>
                <p class="mt-2 text-sm text-gray-400">{{ $emptySong['album'] }} &middot; {{ $emptySong['year'] }} &middot; Depeche Mode</p>
            </div>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($dayEntries as $entry)
                <div class="bg-white rounded-lg border border-gray-200 px-4 py-3 flex items-start gap-4">
                    {{-- Colour band --}}
                    <div class="mt-1 w-1 self-stretch rounded-full flex-shrink-0" style="background-color: {{ $entry->task->colour }}"></div>

                    {{-- Main content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-baseline gap-2 flex-wrap">
                            <span class="font-semibold text-gray-900">{{ $entry->project->name }}</span>
                            <span class="text-gray-400 text-sm">{{ $entry->project->client->name }}</span>
                        </div>
                        <div class="text-sm text-gray-600 mt-0.5">{{ $entry->task->name }}</div>
                        @if ($entry->notes)
                            <div class="text-xs text-gray-400 mt-1">{{ $entry->notes }}</div>
                        @endif
                    </div>

                    {{-- Hours + running indicator --}}
                    <div class="flex items-center gap-3 flex-shrink-0">
                        @if ($entry->is_running)
                            <span class="inline-flex items-center gap-1 text-green-600 text-sm font-medium">
                                <span class="animate-pulse w-2 h-2 bg-green-500 rounded-full"></span>
                                Running
                            </span>
                        @endif
                        <span class="text-gray-700 font-medium tabular-nums">{{ number_format((float) $entry->hours, 2) }}h</span>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-1 flex-shrink-0">
                        @if ($entry->is_running)
                            <button
                                wire:click="stopTimer({{ $entry->id }})"
                                class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition"
                                title="Stop timer"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M6 6h12v12H6z"/>
                                </svg>
                            </button>
                        @else
                            <button
                                wire:click="startTimer({{ $entry->id }})"
                                class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition"
                                title="Start timer"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </button>
                        @endif
                        <button
                            wire:click="openEditModal({{ $entry->id }})"
                            class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition"
                            title="Edit"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button
                            wire:click="deleteEntry({{ $entry->id }})"
                            wire:confirm="Delete this time entry?"
                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition"
                            title="Delete"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Day / week totals --}}
    <div class="mt-4 flex justify-end gap-6 text-sm text-gray-500">
        <span>Day: <strong class="text-gray-800">{{ number_format($dayTotal, 2) }}h</strong></span>
        <span>Week: <strong class="text-gray-800">{{ number_format($weekTotal, 2) }}h</strong></span>
    </div>


    {{-- ============================================================
         Entry modal
    ============================================================ --}}
    @if ($showModal)

        {{-- ============================================================
             Calendar sidebar — slides in from the left over the page
        ============================================================ --}}
        <div
            x-data="{ open: @entangle('showCalendarPanel') }"
            x-show="open"
            x-transition:enter="transition-transform ease-out duration-300"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition-transform ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="fixed inset-y-0 left-0 z-[60] w-80 bg-white shadow-2xl flex flex-col"
            style="display:none"
        >
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <div class="text-xs text-gray-400 uppercase tracking-wide">Today's events from</div>
                    <div class="font-semibold text-gray-900 text-sm mt-0.5">Default Calendar</div>
                </div>
                <button wire:click="closeCalendarPanel" class="text-gray-400 hover:text-gray-600 p-1 rounded transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="overflow-y-auto flex-1 p-4 space-y-2">
                @if ($calendarError === 'no_token')
                    <div class="text-center py-8 px-3">
                        <svg class="w-8 h-8 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-sm text-gray-500 mb-3">Calendar access requires signing in with Google.</p>
                        <a href="{{ route('auth.google') }}" class="text-sm text-blue-600 hover:underline">Re-sign in to grant access →</a>
                    </div>
                @elseif ($calendarError === 'empty' || empty($calendarEvents))
                    <div class="text-center py-8">
                        <p class="text-sm text-gray-400">No events today.</p>
                    </div>
                @else
                    @foreach ($calendarEvents as $event)
                        @php
                            $used = in_array(strtolower($event['title']), $usedEventTitles, true);
                            $hoursLabel = $event['hours'] == 1.0 ? '1 hour'
                                : number_format($event['hours'], 2) . ' hours';
                        @endphp
                        <button
                            @if (! $used) wire:click="pullFromCalendarEvent('{{ addslashes($event['title']) }}', {{ $event['hours'] }})" @endif
                            {{ $used ? 'disabled' : '' }}
                            class="w-full text-left p-3 rounded-lg border transition
                                {{ $used
                                    ? 'border-gray-100 bg-gray-50 cursor-default'
                                    : 'border-gray-200 bg-white hover:border-green-300 hover:bg-green-50 cursor-pointer' }}"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="font-semibold text-sm {{ $used ? 'text-gray-400' : 'text-gray-900' }} leading-snug">
                                    {{ $event['title'] }}
                                </div>
                                @if ($used)
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @endif
                            </div>
                            <div class="text-xs {{ $used ? 'text-gray-400' : 'text-gray-500' }} mt-0.5">
                                {{ $event['start_formatted'] }} – {{ $event['end_formatted'] }}
                            </div>
                            <div class="text-xs {{ $used ? 'text-gray-400' : 'text-gray-500' }}">
                                {{ $hoursLabel }}
                            </div>
                        </button>
                    @endforeach
                @endif
            </div>
        </div>

        {{-- Modal backdrop + centred dialog --}}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            @keydown.escape.window="$wire.closeModal()"
        >
            <div
                class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4"
                x-data="{
                    projectOpen: false,
                    taskOpen: false,
                    get isTimerMode() {
                        const h = $wire.hoursInput ?? '';
                        return h.trim() === '';
                    }
                }"
                @click.stop
            >
                {{-- Modal header --}}
                <div class="px-6 py-4 border-b border-gray-100 text-center relative">
                    <h3 class="font-semibold text-gray-900 text-base">
                        @if ($editingEntryId)
                            Edit entry
                        @else
                            New time entry for {{ \Carbon\Carbon::parse($selectedDate)->format('l, j M') }}
                        @endif
                    </h3>
                </div>

                <div class="px-6 py-5 space-y-3">

                    {{-- Project / Task label --}}
                    <div class="text-sm font-semibold text-gray-700">Project / Task</div>

                    {{-- Project dropdown --}}
                    <div class="relative z-20">
                        <button
                            type="button"
                            @click="projectOpen = !projectOpen; taskOpen = false"
                            class="w-full flex items-center justify-between border border-gray-300 rounded-lg px-4 py-3 text-left bg-white hover:border-gray-400 transition focus:outline-none focus:ring-2 focus:ring-green-500"
                        >
                            @if ($selectedProject)
                                <div class="min-w-0">
                                    <div class="text-xs text-gray-500 leading-none mb-0.5">{{ $selectedProject->client->name }}</div>
                                    <div class="font-semibold text-gray-900 text-sm leading-none">{{ $selectedProject->name }}</div>
                                </div>
                            @else
                                <span class="text-gray-400 text-sm">Select a project…</span>
                            @endif
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div
                            x-show="projectOpen"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            @click.outside="projectOpen = false"
                            class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg"
                            style="display: none"
                        >
                            <div class="p-2 border-b border-gray-100">
                                <input
                                    type="text"
                                    wire:model.live="projectSearch"
                                    placeholder="Search projects…"
                                    class="w-full text-sm px-3 py-2 border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                                    x-init="$el.focus()"
                                />
                            </div>
                            <div class="max-h-60 overflow-y-auto py-1">
                                @php
                                    $grouped = $projectsForPicker
                                        ->when($projectSearch !== '', fn ($c) => $c->filter(
                                            fn ($p) => str_contains(strtolower($p->name), strtolower($projectSearch))
                                                || str_contains(strtolower($p->client->name), strtolower($projectSearch))
                                        ))
                                        ->groupBy(fn ($p) => $p->client->name)
                                        ->sortKeys();
                                @endphp
                                @forelse ($grouped as $clientName => $projects)
                                    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide px-3 py-1.5 mt-1 first:mt-0">{{ $clientName }}</div>
                                    @foreach ($projects as $project)
                                        <button
                                            type="button"
                                            wire:click="selectProject({{ $project->id }})"
                                            @click="projectOpen = false; taskOpen = true"
                                            class="w-full text-left px-4 py-2 text-sm text-gray-800 hover:bg-green-50 hover:text-green-700 transition"
                                        >{{ $project->name }}</button>
                                    @endforeach
                                @empty
                                    <p class="text-sm text-gray-400 px-3 py-4 text-center">No projects found.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- Task dropdown --}}
                    @php $chosenTask = $selectedProject?->tasks->firstWhere('id', $selectedTaskId); @endphp
                    <div class="relative z-10">
                        <button
                            type="button"
                            @click="if ({{ $selectedProjectId ? 'true' : 'false' }}) { taskOpen = !taskOpen; projectOpen = false; }"
                            class="w-full flex items-center justify-between border rounded-lg px-4 py-3 text-left transition focus:outline-none focus:ring-2 focus:ring-green-500
                                {{ $selectedProjectId ? 'border-gray-300 bg-white hover:border-gray-400' : 'border-gray-200 bg-gray-50 cursor-not-allowed' }}"
                        >
                            @if ($chosenTask)
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background: {{ $chosenTask->colour }}"></span>
                                    <span class="font-medium text-gray-900 text-sm">{{ $chosenTask->name }}</span>
                                </div>
                            @else
                                <span class="text-sm {{ $selectedProjectId ? 'text-gray-400' : 'text-gray-300' }}">Select a task…</span>
                            @endif
                            <svg class="w-4 h-4 flex-shrink-0 ml-2 {{ $selectedProjectId ? 'text-gray-400' : 'text-gray-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div
                            x-show="taskOpen"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            @click.outside="taskOpen = false"
                            class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg"
                            style="display: none"
                        >
                            <div class="max-h-60 overflow-y-auto py-1">
                                @if ($selectedProject)
                                    @php
                                        $assignedTasks = $selectedProject->tasks ?? collect();
                                        $billableTasks = $assignedTasks->filter(fn ($t) => (bool) $t->pivot->getAttribute('is_billable'));
                                        $nonBillableTasks = $assignedTasks->reject(fn ($t) => (bool) $t->pivot->getAttribute('is_billable'));
                                    @endphp
                                    @if ($billableTasks->isNotEmpty())
                                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide px-3 py-1.5">Billable</div>
                                        @foreach ($billableTasks->sortBy('name') as $task)
                                            <button
                                                type="button"
                                                wire:click="selectTask({{ $task->id }})"
                                                @click="taskOpen = false"
                                                class="w-full text-left px-4 py-2 text-sm text-gray-800 hover:bg-green-50 hover:text-green-700 transition flex items-center gap-2"
                                            >
                                                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background: {{ $task->colour }}"></span>
                                                {{ $task->name }}
                                            </button>
                                        @endforeach
                                    @endif
                                    @if ($nonBillableTasks->isNotEmpty())
                                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide px-3 py-1.5 {{ $billableTasks->isNotEmpty() ? 'mt-1' : '' }}">Non-billable</div>
                                        @foreach ($nonBillableTasks->sortBy('name') as $task)
                                            <button
                                                type="button"
                                                wire:click="selectTask({{ $task->id }})"
                                                @click="taskOpen = false"
                                                class="w-full text-left px-4 py-2 text-sm text-gray-800 hover:bg-gray-50 hover:text-gray-700 transition flex items-center gap-2"
                                            >
                                                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background: {{ $task->colour }}"></span>
                                                {{ $task->name }}
                                            </button>
                                        @endforeach
                                    @endif
                                    @if ($billableTasks->isEmpty() && $nonBillableTasks->isEmpty())
                                        <p class="text-sm text-gray-400 px-3 py-4 text-center">No tasks assigned.</p>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Notes + Time row --}}
                    <div class="flex gap-3 items-start">
                        <textarea
                            wire:model="notes"
                            rows="3"
                            placeholder="Notes (optional)"
                            class="flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-none placeholder-gray-400"
                        ></textarea>
                        <div class="flex-shrink-0 w-24">
                            <input
                                type="text"
                                wire:model="hoursInput"
                                placeholder="0.00"
                                class="w-full border {{ $hoursError ? 'border-red-400' : 'border-gray-300' }} rounded-lg px-3 py-2.5 text-sm text-center tabular-nums focus:outline-none focus:ring-2 focus:ring-green-500 placeholder-gray-400"
                            />
                            @if ($hoursError)
                                <p class="text-red-500 text-xs mt-1 text-center">{{ $hoursError }}</p>
                            @endif
                        </div>
                    </div>

                </div>

                {{-- Modal footer --}}
                <div class="flex items-center px-6 py-4 border-t border-gray-100">
                    @if ($editingEntryId)
                        <button
                            wire:click="save"
                            class="px-5 py-2 text-sm font-semibold bg-green-600 hover:bg-green-700 text-white rounded-full transition"
                        >Save entry</button>
                    @else
                        <button
                            @click="isTimerMode ? $wire.startTimerFromModal() : $wire.save()"
                            class="px-5 py-2 text-sm font-semibold bg-green-600 hover:bg-green-700 text-white rounded-full transition"
                            x-text="isTimerMode ? 'Start timer' : 'Save entry'"
                        ></button>
                    @endif
                    <button
                        wire:click="closeModal"
                        class="ml-3 px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-full transition"
                    >Cancel</button>
                    <div class="ml-auto">
                        <button
                            wire:click="openCalendarPanel"
                            class="flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-700 transition"
                        >
                            {{-- Google Calendar icon --}}
                            <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <rect x="3" y="4" width="18" height="18" rx="2" fill="white" stroke="#dadce0" stroke-width="1.2"/>
                                <rect x="3" y="9" width="18" height="1.2" fill="#4285F4"/>
                                <rect x="7.5" y="2" width="1.5" height="4" rx="0.75" fill="#4285F4"/>
                                <rect x="15" y="2" width="1.5" height="4" rx="0.75" fill="#4285F4"/>
                                <text x="12" y="18" text-anchor="middle" font-size="7" font-weight="bold" fill="#4285F4" font-family="sans-serif">{{ now()->format('j') }}</text>
                            </svg>
                            Pull in a calendar event
                        </button>
                    </div>
                </div>

            </div>

        </div>{{-- end modal backdrop --}}
    @endif

    {{-- 60-second poll for running timers --}}
    <div wire:poll.60000ms="refreshForTimer" class="hidden"></div>
</div>
