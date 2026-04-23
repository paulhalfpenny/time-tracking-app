<?php

namespace App\Livewire\Timesheet;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DayView extends Component
{
    public function render(): View
    {
        return view('livewire.timesheet.day-view');
    }
}
