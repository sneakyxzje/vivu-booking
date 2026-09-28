<?php

namespace App\Console\Concerns;

use App\Models\TourSchedule;
use App\Services\DemoClock;

trait RunsWithDemoClock
{
    public function handle(): int
    {
        $clock = app(DemoClock::class);
        if (!DemoClock::enabled() || $clock->commandSchedule !== null) {
            return $this->handleForClock();
        }

        // The normal pass excludes demo departures. Each subsequent pass has an explicit scope.
        $result = $this->handleForClock();
        foreach (TourSchedule::query()->whereNotNull('demo_time')->get() as $schedule) {
            $result = max($result, $clock->within($schedule, fn () => $this->handleForClock()));
        }

        return $result;
    }
}
