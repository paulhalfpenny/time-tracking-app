<?php

namespace App\Livewire\Reports;

use App\Domain\Reporting\TimeReportQuery;
use App\Domain\Reporting\TotalsDto;
use App\Enums\GroupBy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TimeReport extends Component
{
    public string $preset = 'this_month';

    public string $from = '';

    public string $to = '';

    public string $groupBy = 'client';

    public function mount(): void
    {
        $this->applyPreset($this->preset);
    }

    public function updatedPreset(string $value): void
    {
        if ($value !== 'custom') {
            $this->applyPreset($value);
        }
    }

    private function applyPreset(string $preset): void
    {
        $now = CarbonImmutable::now();

        match ($preset) {
            'this_week' => [$this->from, $this->to] = [$now->startOfWeek()->toDateString(), $now->endOfWeek()->toDateString()],
            'this_month' => [$this->from, $this->to] = [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()],
            'last_month' => [$this->from, $this->to] = [$now->subMonth()->startOfMonth()->toDateString(), $now->subMonth()->endOfMonth()->toDateString()],
            'last_3' => [$this->from, $this->to] = [$now->subMonths(3)->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()],
            'this_year' => [$this->from, $this->to] = [$now->startOfYear()->toDateString(), $now->endOfYear()->toDateString()],
            default => null,
        };
    }

    private function query(): TimeReportQuery
    {
        return new TimeReportQuery(
            from: CarbonImmutable::parse($this->from),
            to: CarbonImmutable::parse($this->to),
        );
    }

    public function totals(): TotalsDto
    {
        return $this->query()->totals();
    }

    /** @return Collection<int, \stdClass> */
    public function rows(): Collection
    {
        return $this->query()->groupBy(GroupBy::from($this->groupBy));
    }

    public function render(): View
    {
        return view('livewire.reports.time-report', [
            'totals' => $this->totals(),
            'rows' => $this->rows(),
        ]);
    }
}
