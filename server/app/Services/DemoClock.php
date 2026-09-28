<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\TourSchedule;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** A business clock per departure. Never changes PHP/Carbon, queue, OTP or gateway time. */
class DemoClock
{
    public ?TourSchedule $commandSchedule = null;

    public static function enabled(): bool
    {
        return app()->environment(['local', 'testing']) && (bool) config('demo.enabled', false);
    }

    public static function schedule(?TourSchedule $schedule): Carbon
    {
        if (!self::enabled() || !$schedule) {
            return now();
        }

        // Some existing relations deliberately select only id/start_date.
        if (!array_key_exists('demo_time', $schedule->getAttributes()) && $schedule->exists) {
            $schedule = TourSchedule::query()->find($schedule->id, ['id', 'demo_time', 'demo_time_set_at']);
        }

        if (!$schedule?->demo_time || !$schedule->demo_time_set_at) {
            return now();
        }

        return Carbon::parse($schedule->demo_time)->addSeconds(
            Carbon::parse($schedule->demo_time_set_at)->diffInSeconds(now(), false),
        );
    }

    public static function booking(Booking $booking): Carbon
    {
        return self::enabled() ? self::schedule($booking->schedule) : now();
    }

    public static function commandNow(): Carbon
    {
        return self::schedule(app(self::class)->commandSchedule);
    }

    public function within(TourSchedule $schedule, Closure $callback): mixed
    {
        $previous = $this->commandSchedule;
        $this->commandSchedule = $schedule;
        try {
            return $callback();
        } finally {
            $this->commandSchedule = $previous;
        }
    }

    /** Explicitly applied only to roots of time-driven commands, never as a global scope. */
    public static function commandScope(Builder $query, bool $booking = false): Builder
    {
        if (!self::enabled()) {
            return $query;
        }

        $current = app(self::class)->commandSchedule;
        if ($current) {
            return $query->where($query->getModel()->qualifyColumn($booking ? 'tour_schedule_id' : 'id'), $current->id);
        }

        return $booking
            ? $query->whereDoesntHave('schedule', fn ($q) => $q->whereNotNull('demo_time'))
            : $query->whereNull('demo_time');
    }

    public static function metadata(TourSchedule $schedule): ?array
    {
        if (self::enabled() && !array_key_exists('demo_time', $schedule->getAttributes()) && $schedule->exists) {
            $schedule = TourSchedule::query()->find($schedule->id, ['id', 'demo_time', 'demo_time_set_at']) ?? $schedule;
        }
        if (!self::enabled() || !$schedule->demo_time) {
            return null;
        }

        return [
            'now' => self::schedule($schedule)->toIso8601String(),
            'real_now' => now()->toIso8601String(),
        ];
    }

    /** Apply the same deadline rule in SQL as in single-model business checks. */
    public static function whereScheduleTime(Builder $query, Closure $condition): Builder
    {
        if (!self::enabled()) {
            return $condition($query, now());
        }
        $demoSchedules = TourSchedule::query()->whereNotNull('demo_time')->get(['id', 'demo_time', 'demo_time_set_at']);
        return $query->where(function (Builder $times) use ($condition, $demoSchedules) {
            $times->where(function (Builder $normal) use ($condition) {
                $condition($normal->whereNull('demo_time'), now());
            });
            foreach ($demoSchedules as $schedule) {
                $times->orWhere(function (Builder $demo) use ($condition, $schedule) {
                    $condition($demo->whereKey($schedule->id), self::schedule($schedule));
                });
            }
        });
    }
}
